#!/usr/bin/env python3
"""Remove duplicate session flash alert blocks from Blade views (handled globally in layout)."""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent / "resources" / "views"

SESSION_KEYS = ("success", "error", "warning", "info", "status")

# Single-line session flash alerts.
INLINE_SESSION = re.compile(
    r"[ \t]*@if\(session\('(?:success|error|warning|info|status)'\)\)"
    r"<div class=\"alert alert-[^\"]+\"[^>]*>\{\{ session\('(?:success|error|warning|info|status)'\) \}\}</div>@endif\s*\n?",
    re.MULTILINE,
)

# Multiline @if(session('key')) ... @endif blocks (common variants).
SESSION_BLOCK = re.compile(
    r"[ \t]*@if\(session\('(?P<key>" + "|".join(SESSION_KEYS) + r")'\)\)\s*\n"
    r"(?:[ \t]*.*\n)*?"
    r"[ \t]*@endif\s*\n?",
    re.MULTILINE,
)

# Top-level validation summary alerts (field @error directives are kept).
VALIDATION_BLOCK = re.compile(
    r"[ \t]*@if\(\$errors->any\(\)\)\s*\n"
    r"[ \t]*<div class=\"alert alert-danger[^\"]*\"[^>]*>\s*\n"
    r"(?:[ \t]*.*\n)*?"
    r"[ \t]*</div>\s*\n"
    r"[ \t]*@endif\s*\n?",
    re.MULTILINE,
)


def clean(content: str) -> str:
    previous = None
    while previous != content:
        previous = content
        content = SESSION_BLOCK.sub("", content)
        content = INLINE_SESSION.sub("", content)
        content = VALIDATION_BLOCK.sub("", content)
    return content


def main() -> None:
    changed = 0
    for path in ROOT.rglob("*.blade.php"):
        if "layouts/partials/flash-toast" in str(path).replace("\\", "/"):
            continue

        original = path.read_text(encoding="utf-8")
        updated = clean(original)
        if updated != original:
            path.write_text(updated, encoding="utf-8")
            changed += 1
            print(f"updated: {path.relative_to(ROOT.parent.parent)}")

    print(f"done ({changed} files)")


if __name__ == "__main__":
    main()
