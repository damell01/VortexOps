from __future__ import annotations

"""Run the existing Whatnot Scrapling extractor through StealthySession.

This adapter deliberately keeps Cloudflare challenge solving disabled. In production
it can attach to the same persistent Chrome that VortexOps already owns over CDP,
so it does not compete for the shared user-data directory or create a second Chrome.
"""

import importlib.util
import os
from pathlib import Path
from typing import Any

from scrapling.fetchers import StealthySession

HERE = Path(__file__).resolve().parent
BASE_SCRIPT = HERE / "whatnot-scrapling.py"
CDP_URL = os.getenv("WHATNOT_SCRAPLING_CDP_URL", os.getenv("WHATNOT_ATTACH_CDP_URL", "http://127.0.0.1:9222")).strip()
USE_CDP = os.getenv("WHATNOT_SCRAPLING_USE_CDP", "1").strip() != "0"


def load_base_module():
    spec = importlib.util.spec_from_file_location("vortexops_whatnot_scrapling", BASE_SCRIPT)
    if spec is None or spec.loader is None:
        raise RuntimeError(f"Unable to load {BASE_SCRIPT}")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def stealthy_session(**kwargs: Any):
    # Keep the existing extractor's normal browser settings where they make
    # sense, but switch its browser engine to Scrapling's StealthySession.
    # Challenge solving remains explicitly OFF.
    options: dict[str, Any] = {
        "headless": kwargs.get("headless", True),
        "disable_resources": kwargs.get("disable_resources", False),
        "google_search": False,
        "locale": kwargs.get("locale", "en-US"),
        "solve_cloudflare": False,
        "block_webrtc": False,
        "hide_canvas": False,
        "allow_webgl": True,
    }

    if USE_CDP and CDP_URL:
        options["cdp_url"] = CDP_URL
        print(
            f"[whatnot:scrapling] engine=StealthySession cdp={CDP_URL} solve_cloudflare=false",
            file=__import__("sys").stderr,
            flush=True,
        )
    else:
        # Dev/non-CDP fallback: use the same installed Chrome/profile settings
        # the existing DynamicSession backend already receives.
        options["real_chrome"] = kwargs.get("real_chrome", True)
        if kwargs.get("executable_path"):
            options["executable_path"] = kwargs["executable_path"]
        if kwargs.get("user_data_dir"):
            options["user_data_dir"] = kwargs["user_data_dir"]
        print(
            "[whatnot:scrapling] engine=StealthySession local-chrome solve_cloudflare=false",
            file=__import__("sys").stderr,
            flush=True,
        )

    return StealthySession(**options)


def main() -> None:
    module = load_base_module()
    # The existing extractor calls DynamicSession from its module global. Swap
    # that constructor only; analytics/order/shipment/ledger extraction stays in
    # one source of truth instead of being duplicated in this adapter.
    module.DynamicSession = stealthy_session
    module.main()


if __name__ == "__main__":
    main()
