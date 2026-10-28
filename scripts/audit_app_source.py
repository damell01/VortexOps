#!/usr/bin/env python3
"""Read-only whole-repository inventory and heuristic audit.

Run from a complete Dev checkout: python3 scripts/audit_app_source.py
Outputs JSON; findings are review candidates, not proof of a defect.
"""
import json
import re
from collections import Counter
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
EXTENSIONS = {".php", ".blade.php", ".js", ".ts", ".tsx", ".css"}
SKIP = {"vendor", "node_modules", ".git", "storage", "bootstrap/cache", "public/build", "VortexOps-Dev"}
RULES = {
    "numeric_coercion": re.compile(r"\(float\)\s*\$|parseFloat\("),
    "unsafe_unload_interception": re.compile(r"stopImmediatePropagation\s*\(\s*\)"),
    "unbounded_eager_load": re.compile(r"->get\s*\(\s*\)"),
    "raw_sql": re.compile(r"whereRaw\s*\(|orderByRaw\s*\("),
    "dynamic_sql": re.compile(r"DB::raw\s*\("),
    "navigation_override": re.compile(r"function\s+shouldRegisterNavigation"),
    "authorization": re.compile(r"authorize\s*\(|abort_unless\s*\(|canAccess\s*\("),
    "livewire_polling": re.compile(r"wire:poll|->poll\s*\("),
    "db_transaction": re.compile(r"DB::transaction\s*\("),
}
def eligible(path):
    relative = path.relative_to(ROOT)
    if any(part in SKIP for part in relative.parts):
        return False
    return path.name.endswith(".blade.php") or path.suffix in EXTENSIONS

def main():
    files = []
    totals = Counter()
    matches = []
    for path in ROOT.rglob("*"):
        if not path.is_file() or not eligible(path):
            continue
        rel = path.relative_to(ROOT).as_posix()
        try:
            content = path.read_text(encoding="utf-8")
        except (UnicodeError, OSError):
            continue
        lines = content.splitlines()
        files.append({"path": rel, "lines": len(lines), "bytes": path.stat().st_size})
        for line_no, line in enumerate(lines, 1):
            for rule, pattern in RULES.items():
                if pattern.search(line):
                    totals[rule] += 1
                    matches.append({"path": rel, "line": line_no, "rule": rule})
    result = {"scope": "all eligible source files in current checkout", "files_scanned": len(files),
              "lines_scanned": sum(f["lines"] for f in files), "rule_counts": dict(totals),
              "largest_files": sorted(files, key=lambda f: f["lines"], reverse=True)[:40],
              "review_candidates": matches}
    output = ROOT / "source-audit.json"
    output.write_text(json.dumps(result, indent=2) + "\n", encoding="utf-8")
    print(f"Scanned {result['files_scanned']} files / {result['lines_scanned']} lines. Report: {output}")
    print("Heuristics require manual review; this is not a security or runtime test.")

if __name__ == "__main__":
    main()
