<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
final class FulfillmentVerificationService {
 /**
  * Returns whether a buyer's order can be certified from item-level scan records.
  * Unmapped orders are not treated as verified.
  */
 public function coverage(int $runId, int $buyerId): array {
  $run=DB::table('fulfillment_runs')->where('id',$runId)->first();
  $buyer=DB::table('fulfillment_run_buyers')->where('id',$buyerId)->where('run_id',$runId)->first();
  abort_unless($run && $buyer,404);
  $ids=json_decode($buyer->order_ids ?: '[]',true);
  if (!is_array($ids) || !$ids) return ['complete'=>false,'supported'=>false,'expected'=>0,'scanned'=>0,'missing'=>[]];
  $orders=\App\Models\WhatnotShowOrder::query()->where('show_id',$run->show_id)
   ->whereIn('whatnot_order_id',array_map('strval',$ids))->get(['inventory_item_id','quantity']);
  if ($orders->isEmpty() || $orders->contains(fn($order)=>!$order->inventory_item_id || (int)$order->quantity<1))
   return ['complete'=>false,'supported'=>false,'expected'=>0,'scanned'=>0,'missing'=>[]];
  $expected=$orders->groupBy('inventory_item_id')->map(fn($group)=>(int)$group->sum('quantity'));
  $scans=DB::table('fulfillment_verifications')->where('run_id',$runId)->where('buyer_id',$buyerId)
   ->where('result','matched')->whereNotNull('product_id')->select('product_id')
   ->selectRaw('SUM(quantity) as total')->groupBy('product_id')->pluck('total','product_id');
  $missing=[];
  foreach($expected as $productId=>$quantity) {
   $left=max(0,$quantity-(int)($scans[$productId]??0));
   if($left>0) $missing[(int)$productId]=$left;
  }
  $verified = 0;
  foreach ($expected as $productId => $quantity) {
   $verified += min((int) $quantity, (int) ($scans[$productId] ?? 0));
  }
  return ['complete'=>!$missing,'supported'=>true,'expected'=>(int)$expected->sum(),
   'scanned'=>$verified,'missing'=>$missing];
 }

 public function verify(int $runId,int $buyerId,string $barcode,User $actor): int {
  $barcode=trim($barcode);
  if ($barcode==='' || mb_strlen($barcode)>128) throw ValidationException::withMessages(['barcode'=>'Scan a valid barcode.']);
  $verificationId = DB::transaction(function()use($runId,$buyerId,$barcode,$actor) {
   $run=DB::table('fulfillment_runs')->where('id',$runId)->lockForUpdate()->first();
   $buyer=DB::table('fulfillment_run_buyers')->where('id',$buyerId)->where('run_id',$runId)->first();
   abort_unless($run && $run->status==='packing' && $buyer,404);
   $manager=$actor->isOwner()||$actor->isAdmin()||$actor->isFulfillmentAdmin();
   abort_unless($manager || DB::table('fulfillment_run_packers')->where('run_id',$runId)->where('user_id',$actor->id)->exists(),403);
   if ($buyer->status==='packed') throw ValidationException::withMessages(['barcode'=>'This buyer is already marked packed. Reopen the buyer before scanning more items.']);
   if ($buyer->status==='held' || DB::table('fulfillment_flags')->where('buyer_id',$buyerId)->where('status','open')->exists())
    throw ValidationException::withMessages(['barcode'=>'Buyer is on hold. Resolve issues first.']);
   $ids=json_decode($buyer->order_ids ?: '[]',true);
   if (!is_array($ids)||!$ids) throw ValidationException::withMessages(['barcode'=>'Buyer has no verified order IDs.']);
   $product=\App\Models\InventoryItem::findByScan($barcode);
   $matches=$product && \App\Models\WhatnotShowOrder::query()->where('show_id',$run->show_id)
    ->whereIn('whatnot_order_id',array_map('strval',$ids))
    ->where('inventory_item_id',$product->id)->exists();
   $result=$matches?'matched':'unmatched';
   if (!$matches) {
    DB::table('fulfillment_verifications')->insert([
     'run_id'=>$runId,'buyer_id'=>$buyerId,'product_id'=>$product?->id,
     'verified_by'=>$actor->id,'barcode'=>$barcode,'result'=>'unmatched',
     'quantity'=>0,'created_at'=>now(),'updated_at'=>now(),
    ]);
    return 0;
   }
   if ($matches) {
    $expected=(int) \App\Models\WhatnotShowOrder::query()->where('show_id',$run->show_id)
     ->whereIn('whatnot_order_id',array_map('strval',$ids))->where('inventory_item_id',$product->id)->sum('quantity');
    $scanned=DB::table('fulfillment_verifications')->where('run_id',$runId)->where('buyer_id',$buyerId)
     ->where('result','matched')->where('product_id',$product->id)->sum('quantity');
    if ($scanned >= $expected) throw ValidationException::withMessages(['barcode'=>'Expected quantity already scanned for this barcode.']);
   }
   return DB::table('fulfillment_verifications')->insertGetId([
    'run_id'=>$runId,'buyer_id'=>$buyerId,'product_id'=>$product?->id,'verified_by'=>$actor->id,'barcode'=>$barcode,
    'result'=>$result,'quantity'=>1,'created_at'=>now(),'updated_at'=>now()
   ]);
  });
  if ($verificationId === 0) throw ValidationException::withMessages(['barcode'=>'Wrong item for this buyer. Scan rejected and recorded for review.']);
  return $verificationId;
 }
}
