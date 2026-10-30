import ast
import json
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch


class OrderProbeLimitTest(unittest.TestCase):
    def run_batch(self, cap, shipments=False):
        tree = ast.parse(Path('scripts/whatnot-scrapling.py').read_text())
        function = next(n for n in tree.body if isinstance(n, ast.FunctionDef) and n.name == 'batch')
        class Page:
            number = 1
            def goto(self, *args, **kwargs): self.number = 1
            def wait_for_timeout(self, *args): pass
            def locator(self, *args): raise RuntimeError('no expand control')
        class Session:
            def fetch(self, *args, page_action, **kwargs): page_action(Page())
        def advance(page, *args):
            if page.number == 5: return False, str(page.number)
            page.number += 1
            return True, str(page.number)
        namespace = dict(DynamicSession=Session, os=os, Path=Path, json=json, BASE='https://example.test',
                         prepare=lambda p: None, check_login=lambda p: None, info=lambda s: None,
                         extract_orders=lambda p: [{'order_id': str(p.number)}],
                         extract_shipments=lambda p: [{'order_id': str(p.number)}],
                         page_signature=lambda p, shipments: str(p.number), advance_next_page=advance,
                         fail=lambda s: self.fail(s))
        exec(compile(ast.Module(body=[function], type_ignores=[]), '<batch>', 'exec'), namespace)
        with tempfile.TemporaryDirectory() as directory:
            source = Path(directory) / 'sources.json'
            source.write_text(json.dumps([{'live_id':'first', 'show_key':1}, {'live_id':'second', 'show_key':2}]))
            with patch.dict(os.environ, {'WHATNOT_ORDER_SOURCES_FILE': str(source), 'WHATNOT_ORDER_PROBE_MAX_PAGES': str(cap)}):
                return namespace['batch'](Session(), shipments)

    def test_probe_is_limited_for_each_show(self):
        self.assertEqual([2, 2], [r['order_count'] for r in self.run_batch(2)])

    def test_normal_backfill_is_not_limited_by_the_probe_default(self):
        self.assertEqual([5, 5], [r['order_count'] for r in self.run_batch(0)])

    def test_shipment_import_is_not_limited_by_order_probe_setting(self):
        self.assertEqual([5, 5], [r['order_count'] for r in self.run_batch(2, shipments=True)])


if __name__ == '__main__': unittest.main()
