import base64
import importlib.util
import json
import os
from pathlib import Path
import tempfile
from types import SimpleNamespace
from urllib.parse import urlsplit, parse_qs
import unittest
from unittest.mock import patch

spec=importlib.util.spec_from_file_location('order_helper','scripts/whatnot-orders-hardened.py')
helper=importlib.util.module_from_spec(spec)
spec.loader.exec_module(helper)

class RoutingTest(unittest.TestCase):
    def run_batch(self, probe, collision=False):
        urls=[]
        class Locator:
            @property
            def first(self): return self
            def count(self): return 0
        class Page:
            url = 'https://www.whatnot.com/dashboard/orders'
            def on(self,*args):pass
            def goto(self,url,**kwargs):
                self.url=url
                urls.append(url)
            def wait_for_timeout(self,*args):pass
            def locator(self,*args):return Locator()
            def evaluate(self,script):return [] if 'querySelectorAll' in script else None
        class Session:
            def fetch(self,*args,page_action,**kwargs):page_action(Page())
        def fail(message,code=1):raise RuntimeError(message)
        module=SimpleNamespace(batch=lambda *args:[],BASE='https://www.whatnot.com',prepare=lambda page:None,
            check_login=lambda page:None,extract_orders=lambda page:[{'order_id':'same' if collision else str(len(urls))}],
            page_signature=lambda *args:str(len(urls)),info=lambda *args:None,fail=fail)
        helper.install(module)
        with tempfile.TemporaryDirectory() as directory:
            source=Path(directory)/'sources.json'
            source.write_text(json.dumps([{'live_id':'first','show_key':1},{'live_id':'second','show_key':2}]))
            with patch.dict(os.environ,{'WHATNOT_ORDER_SOURCES_FILE':str(source),'WHATNOT_ORDER_PROBE_MAX_PAGES':str(probe)}):
                result=module.batch(Session())
        return urls,result

    def assert_native_show_filters(self, urls):
        for url, live_id in zip(urls, ['first', 'second']):
            query = parse_qs(urlsplit(url).query)
            self.assertNotIn('source', query)
            self.assertNotIn('channel', query)
            self.assertEqual({'salesChannel': {'type': 'single', 'value': live_id}},
                             json.loads(base64.b64decode(query['filters'][0])))
            self.assertEqual(['100'], query['first'])

    def test_probe_uses_native_single_show_filter(self):
        urls,rows=self.run_batch(2)
        self.assert_native_show_filters(urls)
        self.assertTrue(all(row['probe_sample'] and not row['pagination_complete'] for row in rows))

    def test_normal_import_uses_same_native_filter(self):
        urls,rows=self.run_batch(0)
        self.assert_native_show_filters(urls)
        self.assertTrue(all(row['pagination_complete'] for row in rows))

    def test_filter_matches_url_copied_from_seller_hub(self):
        live_id = '5bf0aa4a-5747-4770-938f-c4b5d8b4ee8a'
        url = helper.order_dashboard_url('https://www.whatnot.com', live_id)
        self.assertEqual(
            'eyJzYWxlc0NoYW5uZWwiOnsidHlwZSI6InNpbmdsZSIsInZhbHVlIjoiNWJmMGFhNGEtNTc0Ny00NzcwLTkzOGYtYzRiNWQ4YjRlZThhIn19',
            parse_qs(urlsplit(url).query)['filters'][0])

    def test_source_collision_still_aborts_without_returning_payload(self):
        with self.assertRaisesRegex(RuntimeError,'ORDER_SOURCE_FILTER_COLLISION'):
            self.run_batch(2,collision=True)

if __name__=='__main__':unittest.main()
