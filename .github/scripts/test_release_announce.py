import importlib.util
import sys
import types
import unittest
from pathlib import Path
from unittest import mock

MARKDOWN_PROBLEM = "it contains markdown"
LAYOUT_PROBLEM = "paragraphs must be separated by exactly one blank line, with no line breaks inside a paragraph"


def load_release_announce():
    sys.modules.setdefault("anthropic", types.ModuleType("anthropic"))
    path = Path(__file__).with_name("release-announce.py")
    spec = importlib.util.spec_from_file_location("release_announce", path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


release_announce = load_release_announce()


def paragraph(sentence: str) -> str:
    """Build a one-line summary of valid length around the given sentence."""
    filler = "This release makes the framework easier to extend and safer to run in production. "
    return sentence + " " + filler * 3


class ValidParagraphMarkdownTest(unittest.TestCase):
    def problem(self, sentence: str):
        return release_announce.valid_paragraph(paragraph(sentence))

    def test_accepts_php_attribute_names_and_pr_references(self):
        sentence = "Entities now declare #[ForeignKey] and #[Index] attributes, see #709."

        self.assertIsNone(self.problem(sentence))

    def test_rejects_a_heading(self):
        self.assertEqual(MARKDOWN_PROBLEM, self.problem("# Release notes"))
        self.assertEqual(MARKDOWN_PROBLEM, self.problem("## Release notes"))

    def test_rejects_bold(self):
        self.assertEqual(MARKDOWN_PROBLEM, self.problem("A **breaking** change landed."))

    def test_rejects_emphasis(self):
        self.assertEqual(MARKDOWN_PROBLEM, self.problem("A *breaking* change landed."))

    def test_rejects_backticks(self):
        self.assertEqual(MARKDOWN_PROBLEM, self.problem("The `Index` attribute landed."))

    def test_rejects_a_bullet_line(self):
        self.assertEqual(MARKDOWN_PROBLEM, self.problem("- A change landed."))
        self.assertEqual(MARKDOWN_PROBLEM, self.problem("• A change landed."))


class ValidParagraphLayoutTest(unittest.TestCase):
    def test_accepts_two_or_three_paragraphs_separated_by_a_blank_line(self):
        first = paragraph("Marko has a new SQLite driver.")
        second = "Remember-me sign-ins now work reliably."
        third = "Some changes need small code updates, listed under Breaking Changes below."

        self.assertIsNone(release_announce.valid_paragraph(f"{first}\n\n{second}"))
        self.assertIsNone(release_announce.valid_paragraph(f"{first}\n\n{second}\n\n{third}"))

    def test_rejects_more_than_three_paragraphs(self):
        text = "\n\n".join([paragraph("One."), "Two.", "Three.", "Four."])

        self.assertEqual("it is more than three paragraphs", release_announce.valid_paragraph(text))

    def test_rejects_a_single_newline_inside_a_paragraph(self):
        text = paragraph("One.") + "\nStill the first paragraph."

        self.assertEqual(LAYOUT_PROBLEM, release_announce.valid_paragraph(text))

    def test_rejects_three_consecutive_newlines(self):
        text = paragraph("One.") + "\n\n\nTwo."

        self.assertEqual(LAYOUT_PROBLEM, release_announce.valid_paragraph(text))

    def test_rejects_markdown_on_a_later_line(self):
        for opening in ("# Fixes", "- A fix", "• A fix"):
            with self.subTest(opening=opening):
                text = paragraph("One.") + "\n\n" + opening

                self.assertEqual(MARKDOWN_PROBLEM, release_announce.valid_paragraph(text))


class MessageTest(unittest.TestCase):
    def test_message_leaves_a_blank_line_between_summary_and_changelog(self):
        payload = release_announce.message("0.14.0", "First.\n\nSecond.", "## [0.14.0]\n* A change", False)

        elements = payload["blocks"][0]["elements"]
        summary = elements[1]["elements"][0]["text"]

        self.assertTrue(summary.endswith("Second.\n\n"))
        self.assertEqual("rich_text_preformatted", elements[2]["type"])
        self.assertTrue(payload["text"].endswith("First.\n\nSecond."))

    def test_message_posts_as_the_marko_knight(self):
        payload = release_announce.message("0.14.0", "First.", "## [0.14.0]\n* A change", False)

        self.assertEqual("The Marko Knight", payload["username"])

    def test_message_keeps_the_other_post_options(self):
        payload = release_announce.message("0.14.0", "First.", "## [0.14.0]\n* A change", True)

        self.assertEqual(
            {"text", "blocks", "unfurl_links", "unfurl_media", "username"},
            set(payload),
        )
        self.assertEqual("<!channel> Marko 0.14.0 release\n\nFirst.", payload["text"])
        self.assertEqual("rich_text", payload["blocks"][0]["type"])
        self.assertIs(False, payload["unfurl_links"])
        self.assertIs(False, payload["unfurl_media"])


class PreformattedChunksTest(unittest.TestCase):
    def changelog(self, sections: int, lines: int = 12) -> str:
        parts = ["## [0.14.0] - 2026-11-18"]
        for number in range(sections):
            parts.append(f"### Section {number}")
            parts.extend(f"* change {number}.{line} " + "x" * 40 for line in range(lines))
        return "\n".join(parts)

    def test_chunks_break_only_between_sections(self):
        section = self.changelog(sections=8)
        self.assertGreater(len(section), release_announce.PRE_CHUNK)

        chunks = release_announce.preformatted_chunks(section)

        self.assertGreater(len(chunks), 1)
        for chunk in chunks[1:]:
            self.assertTrue(chunk.startswith("### "), chunk[:60])

    def test_chunks_keep_a_short_changelog_in_one_chunk(self):
        section = self.changelog(sections=2, lines=2)

        self.assertEqual([section], release_announce.preformatted_chunks(section))

    def test_chunks_split_an_oversized_section_by_line(self):
        section = self.changelog(sections=1, lines=120)
        self.assertGreater(len(section), release_announce.PRE_CHUNK)

        chunks = release_announce.preformatted_chunks(section)

        self.assertGreater(len(chunks), 1)
        for chunk in chunks:
            self.assertLessEqual(len(chunk), release_announce.PRE_CHUNK)

    def test_chunks_reassemble_to_the_original_changelog(self):
        for section in (self.changelog(sections=8), self.changelog(sections=1, lines=120)):
            with self.subTest(length=len(section)):
                self.assertEqual(section, "\n".join(release_announce.preformatted_chunks(section)))

    def test_chunks_stay_within_the_size_limit(self):
        section = self.changelog(sections=9, lines=7)

        for chunk in release_announce.preformatted_chunks(section):
            self.assertLessEqual(len(chunk), release_announce.PRE_CHUNK)


class SystemPromptTest(unittest.TestCase):
    def test_prompt_asks_for_short_paragraphs(self):
        self.assertNotIn("exactly one paragraph", release_announce.SYSTEM_PROMPT)
        self.assertIn("\n\n", release_announce.STYLE_EXAMPLE)


class SummarizeTest(unittest.TestCase):
    def summarize(self, text: str) -> str:
        block = types.SimpleNamespace(type="text", text=text)
        response = types.SimpleNamespace(stop_reason="end_turn", content=[block])
        client = types.SimpleNamespace(
            beta=types.SimpleNamespace(messages=types.SimpleNamespace(create=lambda **_: response))
        )
        with mock.patch.object(release_announce.anthropic, "Anthropic", lambda **_: client, create=True):
            return release_announce.summarize("0.14.0", "## [0.14.0]", "")

    def test_keeps_paragraph_breaks_and_normalises_windows_newlines(self):
        first = paragraph("Marko has a new SQLite driver.").strip()

        summary = self.summarize(f"  {first}\r\n\r\nSecond paragraph.  \n")

        self.assertEqual(f"{first}\n\nSecond paragraph.", summary)


if __name__ == "__main__":
    unittest.main()
