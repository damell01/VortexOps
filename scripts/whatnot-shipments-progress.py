from __future__ import annotations

import importlib.util
import json
import os
import re
import sys
import time
from pathlib import Path

HERE = Path(__file__).resolve().parent
HARDENED_SCRIPT = HERE / "whatnot-shipments-hardened.py"
SOURCE_FILE = os.getenv("WHATNOT_ORDER_SOURCES_FILE", "").strip()


def load_hardened():
    spec = importlib.util.spec_from_file_location("vortexops_whatnot_shipments_hardened_progress", HARDENED_SCRIPT)
    if spec is None or spec.loader is None:
        raise RuntimeError(f"Unable to load {HARDENED_SCRIPT}")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def format_duration(seconds: float | None) -> str:
    if seconds is None or seconds < 0:
        return "--"
    total = int(round(seconds))
    hours, rem = divmod(total, 3600)
    minutes, secs = divmod(rem, 60)
    if hours:
        return f"{hours}h {minutes:02d}m"
    if minutes:
        return f"{minutes}m {secs:02d}s"
    return f"{secs}s"


def source_count() -> int:
    if not SOURCE_FILE:
        return 0
    try:
        data = json.loads(Path(SOURCE_FILE).read_text())
        return len(data) if isinstance(data, list) else 0
    except Exception:
        return 0


def main() -> None:
    hardened = load_hardened()
    original_info = hardened.info
    started_at = time.monotonic()
    total = source_count()
    completed = 0

    original_info(f"SHIPMENT_PROGRESS queued={total}")

    completion_pattern = re.compile(
        r"^shipments-batch:\s*\[(\d+)/(\d+)\]\s+(.+?)\s+->\s+(\d+)\s+row\(s\)\s+across\s+(\d+)\s+page\(s\)$"
    )

    def progress_info(message: str) -> None:
        nonlocal completed, total
        original_info(message)

        match = completion_pattern.match(message.strip())
        if not match:
            return

        current = int(match.group(1))
        reported_total = int(match.group(2))
        show_key = match.group(3)
        rows = int(match.group(4))
        pages = int(match.group(5))

        if reported_total > 0:
            total = reported_total
        completed = max(completed, current)

        elapsed = time.monotonic() - started_at
        average = elapsed / completed if completed else 0.0
        remaining = max(total - completed, 0)
        eta = average * remaining if completed else None
        percent = (completed / total * 100.0) if total else 0.0

        original_info(
            f"SHIPMENT_PROGRESS [{completed}/{total}] {percent:.1f}% "
            f"show={show_key} rows={rows} pages={pages} "
            f"elapsed={format_duration(elapsed)} eta={format_duration(eta)} remaining={remaining}"
        )

    hardened.info = progress_info

    try:
        hardened.main()
    finally:
        elapsed = time.monotonic() - started_at
        original_info(
            f"SHIPMENT_PROGRESS finished={completed}/{total} elapsed={format_duration(elapsed)}"
        )


if __name__ == "__main__":
    main()
