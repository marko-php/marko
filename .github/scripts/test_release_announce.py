import importlib.util
import sys
import types
import unittest
from pathlib import Path

MARKDOWN_PROBLEM = "it contains markdown"


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


if __name__ == "__main__":
    unittest.main()
