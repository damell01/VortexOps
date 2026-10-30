import ast
import json
from pathlib import Path
import unittest

source=ast.parse(Path('scripts/whatnot-orders-hardened.py').read_text())
function=next(n for n in source.body if isinstance(n,ast.FunctionDef) and n.name=='summarize_order_source_evidence')
namespace={'json':json}
exec(compile(ast.Module(body=[function],type_ignores=[]),'<evidence>','exec'),namespace)
summarize=namespace['summarize_order_source_evidence']

class EvidenceTest(unittest.TestCase):
    def test_identifies_missing_and_matching_source_filters(self):
        result=summarize({'sourceId':None,'filters':{'livestreamId':'expected'}},{},'expected')
        self.assertIn({'path':'sourceId','type':'null','matches_expected_live':False},result['variables'])
        self.assertIn({'path':'filters.livestreamId','type':'string','matches_expected_live':True},result['variables'])

    def test_exposes_schema_without_customer_values_or_credentials(self):
        result=summarize({'password':'secret','accessToken':'secret'},{'data':{'orders':[{'buyer':{'name':'Private Customer'},'live':{'id':'expected'},'orderId':'123'}]}},'expected')
        text=json.dumps(result)
        for private in ['secret','Private Customer','expected','123']:
            self.assertNotIn('"'+private+'"',text)
        self.assertTrue(any(r.get('matches_expected_live') for r in result['response']))
        self.assertEqual([],result['variables'])

    def test_limits_large_responses_and_rejects_dynamic_keys(self):
        result=summarize({}, {'orders':[{'uuid-value': 'private','id':str(i)} for i in range(10000)]},'show')
        self.assertLess(len(result['response']),10)
        self.assertNotIn('uuid-value',json.dumps(result))

if __name__=='__main__':unittest.main()
