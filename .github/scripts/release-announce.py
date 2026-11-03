#!/usr/bin/env python3
"""Announce a published Marko release in Slack.

Runs from .github/workflows/release-announce.yml once the tag's package split has finished.
Every check fails loudly (non-zero exit, red run) and nothing is posted unless all of them pass:

  1. VERSION is a plain X.Y.Z tag with a published GitHub Release.
  2. The latest `Split Packages` run for the tag completed successfully.
  3. Packagist serves the version for marko/core (polled until PACKAGIST_TIMEOUT).
  4. CHANGELOG.md at the tag has a section for the version.
  5. Claude writes a plain-language summary paragraph that passes validation.

Each target channel is checked for an earlier post by this bot for the same version, so a re-run
never posts twice. #releases gets an @channel mention; #general gets the same post without it.

Environment:
  VERSION                 release tag, e.g. 0.13.0
  TARGET                  releases | general | both
  PREVIEW                 "true" prints the Slack payloads instead of posting
  GITHUB_TOKEN, GITHUB_REPOSITORY
  SLACK_BOT_TOKEN, SLACK_RELEASES_CHANNEL, SLACK_GENERAL_CHANNEL
  ANTHROPIC_API_KEY
  PACKAGIST_TIMEOUT       seconds to wait for Packagist (default 1800)
"""

from __future__ import annotations

import json
import os
import re
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

import anthropic

MODEL = "claude-opus-5-5"
SEMVER = re.compile(r"^\d+\.\d+\.\d+$")
URL = re.compile(r"https?://[^\s)>\]]+")
PRE_CHUNK = 2500

STYLE_EXAMPLE = (
    "Marko now has a built-in SQLite driver, so you can start an app or run tests without "
    "setting up a separate database server. Remember-me sign-ins now work reliably: the "
    "remember cookie used to get dropped. You can also turn on \"keep me signed in\" in one "
    "step when someone logs in, and an app can check that a password reset link is still "
    "valid before asking for a new password. Queues are easier to tune and monitor, with each "
    "queue able to set its own time limits. Large file uploads now get a clear \"too large\" "
    "response instead of crashing the request, and commands no longer quietly ignore options "
    "they don't recognize: --help now shows help instead of running the command. A few of "
    "these changes need small code updates in existing apps, listed under Breaking Changes "
    "below."
)

SYSTEM_PROMPT = f"""You write the opening paragraph of the release announcement for Marko, a modular PHP framework, posted in the project's Slack community.

Readers are PHP developers who use or follow Marko. They want to know, in plain language, what this release lets them do and what got fixed, without reading the changelog. The full changelog is posted directly below your paragraph.

Write exactly one paragraph of 3 to 6 sentences:
- Lead with the most useful additions, then the fixes people would notice. Group related changes rather than listing every PR.
- Describe effects for app developers, not implementation details. Avoid class names, method names and jargon unless a reader needs the name to act on it.
- If the changelog has a Breaking Changes section, end with one sentence saying some changes need small code updates in existing apps and are listed under Breaking Changes below. If it has none, do not mention breaking changes.
- Only state what the changelog and PR descriptions support. Do not invent features, numbers or dates.
- Plain text only: no markdown, no backticks, no bullet points, no headings, no emoji, no greeting and no sign-off. Do not repeat the version number or say "this release" more than once.

This paragraph, written for 0.13.0, shows the tone and length the maintainer approved:

{STYLE_EXAMPLE}

Reply with the paragraph and nothing else."""


def fail(message: str) -> None:
    print(f"::error::{message}", file=sys.stderr)
    sys.exit(1)


def env(name: str, default: str | None = None) -> str:
    value = os.environ.get(name, default)
    if value is None or value == "":
        fail(f"Missing required environment variable {name}")
    return value


def http_json(url: str, *, headers: dict[str, str] | None = None, data: dict | None = None) -> dict:
    body = json.dumps(data).encode() if data is not None else None
    request = urllib.request.Request(url, data=body, headers=headers or {})
    if body is not None:
        request.add_header("Content-Type", "application/json; charset=utf-8")
    last_error: Exception | None = None
    for attempt in range(4):
        try:
            with urllib.request.urlopen(request, timeout=30) as response:
                return json.loads(response.read().decode())
        except urllib.error.HTTPError as error:
            if error.code == 404:
                raise
            if error.code < 500 and error.code != 429:
                fail(f"{url} returned HTTP {error.code}: {error.read().decode()[:500]}")
            last_error = error
        except (urllib.error.URLError, TimeoutError) as error:
            last_error = error
        time.sleep(5 * (attempt + 1))
    fail(f"{url} failed after retries: {last_error}")
    raise AssertionError  # unreachable


def github(path: str) -> dict:
    return http_json(
        f"https://api.github.com/repos/{env('GITHUB_REPOSITORY')}{path}",
        headers={
            "Authorization": f"Bearer {env('GITHUB_TOKEN')}",
            "Accept": "application/vnd.github+json",
            "X-GitHub-Api-Version": "2022-11-28",
        },
    )


def slack(method: str, payload: dict) -> dict:
    result = http_json(
        f"https://slack.com/api/{method}",
        headers={"Authorization": f"Bearer {env('SLACK_BOT_TOKEN')}"},
        data=payload,
    )
    if not result.get("ok"):
        fail(f"Slack {method} failed: {result.get('error')} {result.get('needed', '')}".strip())
    return result


def verify_release(version: str) -> None:
    try:
        release = github(f"/releases/tags/{version}")
    except urllib.error.HTTPError:
        fail(f"No GitHub Release exists for tag {version}")
    if release.get("draft"):
        fail(f"GitHub Release {version} is still a draft")


def verify_split(version: str) -> None:
    query = urllib.parse.urlencode({"branch": version, "event": "push", "per_page": 5})
    runs = github(f"/actions/workflows/split.yml/runs?{query}").get("workflow_runs", [])
    if not runs:
        fail(f"No Split Packages run found for tag {version}")
    latest = runs[0]
    if latest["status"] != "completed" or latest["conclusion"] != "success":
        fail(
            f"Split Packages for {version} is {latest['status']}/{latest['conclusion']}: "
            f"{latest['html_url']}"
        )
    print(f"Split Packages for {version} succeeded: {latest['html_url']}")


def wait_for_packagist(version: str) -> None:
    deadline = time.time() + int(env("PACKAGIST_TIMEOUT", "1800"))
    while True:
        try:
            data = http_json(f"https://repo.packagist.org/p2/marko/core.json?ts={int(time.time())}")
            versions = {entry.get("version") for entry in data["packages"]["marko/core"]}
            if version in versions:
                print(f"Packagist serves marko/core {version}")
                return
        except (urllib.error.HTTPError, KeyError):
            pass
        if time.time() > deadline:
            fail(f"Packagist did not list marko/core {version} before the timeout")
        print("Waiting for Packagist...")
        time.sleep(30)


def changelog_section(version: str) -> str:
    shown = subprocess.run(
        ["git", "show", f"refs/tags/{version}:CHANGELOG.md"], capture_output=True, text=True
    )
    if shown.returncode != 0:
        fail(f"Could not read CHANGELOG.md at tag {version}: {shown.stderr.strip()}")
    lines = shown.stdout.splitlines()
    start = next((i for i, line in enumerate(lines) if line.startswith(f"## [{version}]")), None)
    if start is None:
        fail(f"CHANGELOG.md has no section for {version}")
    end = next((i for i in range(start + 1, len(lines)) if lines[i].startswith("## [")), len(lines))
    section = "\n".join(lines[start:end]).strip()
    if "* " not in section:
        fail(f"CHANGELOG.md section for {version} lists no changes")
    return section


def pr_context(section: str) -> str:
    parts = []
    for number in sorted(set(re.findall(r"/pull/(\d+)", section)), key=int):
        pr = github(f"/pulls/{number}")
        body = re.sub(r"\s+\n", "\n", (pr.get("body") or "").strip())[:1500]
        parts.append(f"PR #{number}: {pr['title']}\n{body}")
    return "\n\n---\n\n".join(parts)


def valid_paragraph(text: str) -> str | None:
    if not 200 <= len(text) <= 1500:
        return f"length {len(text)} is outside 200-1500 characters"
    if "\n" in text:
        return "it is more than one paragraph"
    if re.search(r"[`*#]|^\s*[-•]", text):
        return "it contains markdown"
    return None


def summarize(version: str, section: str, context: str) -> str:
    client = anthropic.Anthropic(max_retries=4)
    prompt = (
        f"Changelog section for Marko {version}:\n\n{section}\n\n"
        f"Descriptions of the pull requests it lists:\n\n{context}"
    )
    problem = "no attempt made"
    for _ in range(3):
        response = client.beta.messages.create(
            model=MODEL,
            max_tokens=16000,
            betas=["server-side-fallback-2026-07-01"],
            fallbacks="default",
            output_config={"effort": "high"},
            system=SYSTEM_PROMPT,
            messages=[{"role": "user", "content": prompt}],
        )
        if response.stop_reason == "refusal":
            problem = "the model declined the request"
            continue
        if response.stop_reason != "end_turn":
            problem = f"generation stopped with {response.stop_reason}"
            continue
        text = " ".join(
            block.text.strip() for block in response.content if block.type == "text"
        ).strip()
        problem = valid_paragraph(text) or ""
        if not problem:
            return text
        print(f"Rejected summary ({problem}), retrying")
    fail(f"Could not get a usable summary from Claude: {problem}")
    raise AssertionError  # unreachable


def linkified(text: str) -> list[dict]:
    elements: list[dict] = []
    position = 0
    for match in URL.finditer(text):
        if match.start() > position:
            elements.append({"type": "text", "text": text[position:match.start()]})
        elements.append({"type": "link", "url": match.group(0), "text": match.group(0)})
        position = match.end()
    if position < len(text):
        elements.append({"type": "text", "text": text[position:]})
    return elements


def preformatted_chunks(section: str) -> list[str]:
    chunks: list[str] = []
    current = ""
    for line in section.splitlines():
        if current and len(current) + len(line) + 1 > PRE_CHUNK:
            chunks.append(current)
            current = ""
        current = f"{current}\n{line}" if current else line
    if current:
        chunks.append(current)
    return chunks


def headline(version: str) -> str:
    return f"Marko {version} release"


def message(version: str, summary: str, section: str, mention: bool) -> dict:
    heading: list[dict] = []
    if mention:
        heading.append({"type": "broadcast", "range": "channel"})
        heading.append({"type": "text", "text": " "})
    heading.append({"type": "text", "text": headline(version)})

    chunks = preformatted_chunks(section)
    blocks = [
        {
            "type": "rich_text",
            "elements": [
                {"type": "rich_text_section", "elements": heading},
                {"type": "rich_text_section", "elements": [{"type": "text", "text": f"\n{summary}\n"}]},
                {"type": "rich_text_preformatted", "elements": linkified(chunks[0])},
            ],
        }
    ]
    for chunk in chunks[1:]:
        blocks.append(
            {"type": "rich_text", "elements": [{"type": "rich_text_preformatted", "elements": linkified(chunk)}]}
        )

    fallback = f"{'<!channel> ' if mention else ''}{headline(version)}\n\n{summary}"
    return {"text": fallback, "blocks": blocks, "unfurl_links": False, "unfurl_media": False}


def already_posted(channel: str, bot_user: str, version: str) -> bool:
    history = slack("conversations.history", {"channel": channel, "limit": 200})
    for item in history.get("messages", []):
        if item.get("user") == bot_user and headline(version) in item.get("text", ""):
            return True
    return False


def main() -> None:
    version = env("VERSION").strip()
    if not SEMVER.match(version):
        fail(f"VERSION {version!r} is not a plain X.Y.Z release tag")
    target = env("TARGET", "both")
    if target not in ("releases", "general", "both"):
        fail(f"TARGET {target!r} must be releases, general or both")
    preview = os.environ.get("PREVIEW", "false") == "true"

    channels = []
    if target in ("releases", "both"):
        channels.append(("releases", env("SLACK_RELEASES_CHANNEL"), True))
    if target in ("general", "both"):
        channels.append(("general", env("SLACK_GENERAL_CHANNEL"), False))

    verify_release(version)
    verify_split(version)
    wait_for_packagist(version)
    section = changelog_section(version)
    summary = summarize(version, section, pr_context(section))
    print(f"Summary:\n{summary}\n")

    if preview:
        for name, _, mention in channels:
            print(f"--- #{name} payload ---")
            print(json.dumps(message(version, summary, section, mention), indent=2))
        return

    bot_user = slack("auth.test", {})["user_id"]
    for name, channel, mention in channels:
        if already_posted(channel, bot_user, version):
            print(f"#{name} already has the {version} announcement, skipping")
            continue
        slack("chat.postMessage", {"channel": channel, **message(version, summary, section, mention)})
        print(f"Posted the {version} announcement to #{name}")


if __name__ == "__main__":
    main()
