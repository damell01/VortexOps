import ast
import re
import unittest
from datetime import date, timedelta
from pathlib import Path
from typing import Any

SOURCE = Path(__file__).with_name("whatnot-analytics-lives.py")
tree = ast.parse(SOURCE.read_text())
selected = [node for node in tree.body if isinstance(node, ast.FunctionDef) and node.name in {"recent_scan", "dated_batch_before_cutoff", "scan_selected_tab_index"}]
ns = {"Any": Any, "re": re, "clean": lambda value: str(value or "").strip()}
exec(compile(ast.Module(body=selected, type_ignores=[]), str(SOURCE), "exec"), ns)

class Page:
    def __init__(self, batches):
        self.batches = batches
        self.index = 0
    def wait_for_timeout(self, _):
        self.index = min(self.index + 1, len(self.batches) - 1)

class Module:
    def check_login(self, page): pass
    def info(self, message): pass

def rows(day, start):
    return [{"live_id": str(start+i), "show_date": day} for i in range(3)]

class ScanRangeTests(unittest.TestCase):
    def setUp(self):
        ns["select_tab"] = lambda *args: True
        ns["extract_show_rows"] = lambda page: page.batches[page.index]
        ns["scroll_to_bottom"] = lambda page: None

    def test_recent_budget_does_not_change_historical_requests(self):
        self.assertTrue(ns["recent_scan"]((date.today()-timedelta(days=7)).isoformat()))
        self.assertFalse(ns["recent_scan"]((date.today()-timedelta(days=30)).isoformat()))
        self.assertFalse(ns["recent_scan"](None))

    def test_stops_after_two_new_old_batches_without_claiming_exhaustion(self):
        page = Page([rows("2026-10-08", 0), rows("2026-10-02", 10), rows("2026-10-01", 20), rows("2026-09-01", 30)])
        seen, selected, exhausted = ns["scan_selected_tab_index"](Module(), page, "Past", 100, 5, "2026-10-03")
        self.assertTrue(selected)
        self.assertFalse(exhausted)
        self.assertEqual(page.index, 2)
        self.assertEqual(len(seen), 9)

    def test_unknown_mixed_or_unsorted_dates_do_not_prove_cutoff(self):
        check = ns["dated_batch_before_cutoff"]
        self.assertFalse(check(rows(None, 0), "2026-10-03"))
        self.assertFalse(check([{"show_date":"2026-10-01"},{"show_date":"2026-10-08"}], "2026-10-03"))
        self.assertFalse(check([{"show_date":"2026-10-01"},{"show_date":"2026-10-02"}], "2026-10-03"))
        self.assertFalse(check(rows("2026-10-03", 0), "2026-10-03"))

    def test_full_history_mode_still_walks_until_list_stabilizes(self):
        page = Page([rows("2026-10-08", 0), rows("2026-10-02", 10), rows("2026-10-01", 20), rows("2026-09-01", 30)])
        seen, selected, exhausted = ns["scan_selected_tab_index"](Module(), page, "Past", 100, 2)
        self.assertEqual(len(seen), 12)
        self.assertTrue(exhausted)

    def test_pass_limit_is_not_evidence_of_missing_shows(self):
        page = Page([rows(None, i*10) for i in range(20)])
        seen, _, exhausted = ns["scan_selected_tab_index"](Module(), page, "Past", 12, 5, "2026-10-03")
        self.assertEqual(len(seen), 36)
        self.assertFalse(exhausted)

if __name__ == "__main__":
    unittest.main()
