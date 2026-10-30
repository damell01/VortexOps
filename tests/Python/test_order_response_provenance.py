import base64
import importlib.util
import unittest

spec=importlib.util.spec_from_file_location('provenance','scripts/whatnot-orders-hardened.py')
helper=importlib.util.module_from_spec(spec)
spec.loader.exec_module(helper)

class ProvenanceTest(unittest.TestCase):
    def entry(self, live='show'):
        return {'operationName':'SellerHubGetMyOrders','variables':{'salesChannels':[{'id':live}]}}
    def body(self):
        return {'data':{'me':{'orders':{'edges':[{'node':{'id':base64.b64encode(b'OrderNode:123').decode(),'buyer':{'id':'999'}}},{'node':{'id':'456'}}]}}}}
    def test_only_filtered_order_nodes_supply_evidence(self):
        self.assertEqual({'123','456'},helper.filtered_response_order_ids(self.entry(),self.body(),'show'))
    def test_other_show_and_unfiltered_requests_do_not_supply_evidence(self):
        self.assertEqual(set(),helper.filtered_response_order_ids(self.entry('other'),self.body(),'show'))
        entry=self.entry();entry['variables']['salesChannels']=[]
        self.assertEqual(set(),helper.filtered_response_order_ids(entry,self.body(),'show'))
    def test_errors_and_multiple_show_requests_are_unverified(self):
        body=self.body();body['errors']=[{'message':'partial'}]
        self.assertEqual(set(),helper.filtered_response_order_ids(self.entry(),body,'show'))
        entry=self.entry();entry['variables']['salesChannels'].append({'id':'other'})
        self.assertEqual(set(),helper.filtered_response_order_ids(entry,self.body(),'show'))
    def test_buyer_relay_ids_cannot_be_used_as_order_evidence(self):
        body={'data':{'me':{'orders':{'edges':[{'node':{'id':base64.b64encode(b'UserNode:999').decode()}}]}}}}
        self.assertEqual(set(),helper.filtered_response_order_ids(self.entry(),body,'show'))
