from __future__ import annotations

"""Run the existing Whatnot Scrapling extractor through StealthySession.

In production this adapter can attach to the same persistent Chrome that VortexOps
already owns over CDP, so it does not compete for the shared user-data directory
or create a second Chrome. Browser behavior is configured through environment
variables so production settings can be changed without editing this script.
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


def env_bool(name: str, default: bool) -> bool:
    """Read a conventional boolean environment variable with a safe default."""
    value = os.getenv(name)
    if value is None or not value.strip():
        return default
    return value.strip().lower() in {"1", "true", "yes", "on"}


SCRAPLING_SOLVE_CLOUDFLARE = env_bool("WHATNOT_SCRAPLING_SOLVE_CLOUDFLARE", False)
SCRAPLING_BLOCK_WEBRTC = env_bool("WHATNOT_SCRAPLING_BLOCK_WEBRTC", False)
SCRAPLING_HIDE_CANVAS = env_bool("WHATNOT_SCRAPLING_HIDE_CANVAS", False)
SCRAPLING_ALLOW_WEBGL = env_bool("WHATNOT_SCRAPLING_ALLOW_WEBGL", True)


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
    options: dict[str, Any] = {
        "headless": kwargs.get("headless", True),
        "disable_resources": kwargs.get("disable_resources", False),
        "google_search": False,
        "locale": kwargs.get("locale", "en-US"),
        "solve_cloudflare": SCRAPLING_SOLVE_CLOUDFLARE,
        "block_webrtc": SCRAPLING_BLOCK_WEBRTC,
        "hide_canvas": SCRAPLING_HIDE_CANVAS,
        "allow_webgl": SCRAPLING_ALLOW_WEBGL,
    }

    option_summary = (
        f"solve_cloudflare={str(SCRAPLING_SOLVE_CLOUDFLARE).lower()} "
        f"block_webrtc={str(SCRAPLING_BLOCK_WEBRTC).lower()} "
        f"hide_canvas={str(SCRAPLING_HIDE_CANVAS).lower()} "
        f"allow_webgl={str(SCRAPLING_ALLOW_WEBGL).lower()}"
    )

    if USE_CDP and CDP_URL:
        options["cdp_url"] = CDP_URL
        print(
            f"[whatnot:scrapling] engine=StealthySession cdp={CDP_URL} {option_summary}",
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
            f"[whatnot:scrapling] engine=StealthySession local-chrome {option_summary}",
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
