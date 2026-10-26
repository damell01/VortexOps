<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\App\Models\Setting::set('enabled_admin_modules', json_encode(array_keys(\App\Support\AdminModules::definitions())));
\App\Models\Setting::set('demo_mode', false);
$owner = \App\Models\User::factory()->create(['email'=>config('app.owner_email'), 'password'=>\Illuminate\Support\Facades\Hash::make('MockupTest123!'), 'must_change_password'=>false]);
$owner->assignRole(\Spatie\Permission\Models\Role::findOrCreate('super_admin', 'web'));
$user = \App\Models\User::factory()->create(['name'=>'Brandon', 'buddy'=>'pikachu', 'email'=>'mockup-streamer@example.test', 'password'=>\Illuminate\Support\Facades\Hash::make('MockupTest123!'), 'must_change_password'=>false]);
$user->assignRole(\Spatie\Permission\Models\Role::findOrCreate('streamer', 'web'));
$streamer=\App\Models\Streamer::create(['name'=>'Brandon', 'user_id'=>$user->id, 'status'=>'active']);
$channel=\App\Models\WhatnotChannel::create(['name'=>'Vortex Breaks', 'status'=>'active']);
foreach ([-1,0,2] as $offset) {
    $show=\App\Models\Show::create(['title'=>$offset===2?'Friday Claims w/ Brandon':'Claims w/ Brandon', 'show_date'=>today()->addDays($offset), 'status'=>'draft', 'is_operational'=>true, 'created_by'=>$owner->id, 'whatnot_channel_id'=>$channel->id, 'gross_revenue'=>1500]);
    $show->streamers()->attach($streamer->id);
    if ($offset===-1) {
        $entry=\App\Models\StreamerLogEntry::create(['show_id'=>$show->id,'streamer_id'=>$streamer->id,'status'=>'pending']);
        \App\Models\StreamerLogItem::create(['streamer_log_entry_id'=>$entry->id,'item_name'=>'Gem 3 Box','quantity'=>2,'disposition'=>'sold']);
    }
}
file_put_contents(__DIR__.'/../../storage/mockup-owner.txt', $owner->email);

$assistant = \App\Models\User::factory()->create(['name'=>'Assistant Test', 'email'=>'mockup-assistant@example.test', 'password'=>\Illuminate\Support\Facades\Hash::make('MockupTest123!'), 'must_change_password'=>false]);
$assistant->assignRole(\Spatie\Permission\Models\Role::findOrCreate('streamer_assistant', 'web'));
$assistantProfile = \App\Models\Streamer::create(['name'=>'Assistant Test', 'user_id'=>$assistant->id, 'member_type'=>'assistant', 'status'=>'active']);
$paidRun = \App\Models\WeeklyPayoutBatch::create(['week_start'=>today()->subWeek()->startOfWeek(), 'week_end'=>today()->subWeek()->endOfWeek(), 'status'=>'paid', 'created_by'=>$owner->id]);
foreach ([[$assistantProfile,125.25],[$streamer,9999.22]] as [$payee,$amount]) {
    \App\Models\Payout::create(['streamer_id'=>$payee->id, 'weekly_payout_batch_id'=>$paidRun->id, 'payout_type'=>'hourly', 'status'=>'paid', 'calculated_payout'=>$amount]);
}
