<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
final class FulfillmentVerificationService {
 public function verify(int $runId,int $buyerId,string $barcode,User $actor): int {
  $barcode=trim($barcode);
  if ($barcode==='' || mb_strlen($barcode)>128) throw ValidationException::withMessages(['barcode'=>'Scan a valid barcode.']);
  return DB::transaction(function()use($runId,$buyerId,$barcode,$actor) {
   $run=DB::table('fulfillment_runs')->where('id',$runId)->lockForUpdate()->first();
   $buyer=DB::table('fulfillment_run_buyers')->where('id',$buyerId)->where('run_id',$runId)->first();
   abort_unless($run && $run->status==='packing' && $buyer,404);
   $manager=$actor->isOwner()||$actor->isAdmin()||$actor->isFulfillmentAdmin();
   abort_unless($manager || DB::table('fulfillment_run_packers')->where('run_id',$runId)->where('user_id',$actor->id)->exists(),403);
   if ($buyer->status==='held' || DB::table('fulfillment_flags')->where('buyer_id',$buyerId)->where('status','open')->exists())
    throw ValidationException::withMessages(['barcode'=>'Buyer is on hold. Resolve issues first.']);
   $ids=json_decode($buyer->order_ids ?: '[]',true);
   if (!is_array($ids)||!$ids) throw ValidationException::withMessages(['barcode'=>'Buyer has no verified order IDs.']);
   $matches=DB::table('whatnot_show_orders')->where('show_id',$run->show_id)->whereIn('whatnot_order_id',array_map('strval',$ids))
    ->where(function($q)use($barcode){$q->whereHas('inventoryItem',fn($i)=>$i->where('barcode',$barcode));})->exists();
   // The order-to-inventory barcode relationship must be checked against the actual schema.
   $result=$matches?'matched':'unmatched';
   return DB::table('fulfillment_verifications')->insertGetId([
    'run_id'=>$runId,'buyer_id'=>$buyerId,'verified_by'=>$actor->id,'barcode'=>$barcode,
    'result'=>$result,'quantity'=>1,'created_at'=>now(),'updated_at'=>now()
   ]);
  });
 }
}
