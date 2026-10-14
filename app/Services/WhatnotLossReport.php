<?php

namespace App\Services;

use App\Models\Show;
use App\Notifications\VortexAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WhatnotLossReport
{
    public function rows(Carbon $from, Carbon $to, ?int $channel = null, ?int $business = null): Collection
    {
        return Show::query()->where('is_operational', true)->where('status', '!=', 'cancelled')
            ->whereBetween('show_date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->whereDate('show_date', '<=', \App\Support\BusinessTime::local()->toDateString())
            ->whereNotNull('whatnot_net')->where('whatnot_net', '<', 0)
            ->when($channel, fn ($q) => $q->where('whatnot_channel_id', $channel))
            ->when($business, fn($q)=>$q->whereHas('streamers',fn($s)=>$s->where('business_id',$business)))
            ->with(['channel:id,name', 'streamers:id,name'])->orderBy('whatnot_net')->get();
    }

    public function notification(string $event, Carbon $from, Carbon $to, ?int $channel = null): ?VortexAlert
    {
        $rows = $this->rows($from, $to, $channel);
        if ($rows->isEmpty()) return null;
        $label = $from->format('M j').' – '.$to->format('M j, Y');
        $body = $rows->count().' shows with negative Whatnot estimated earnings. Total: -$'.number_format(abs($rows->sum('whatnot_net')), 2).".\n";
        foreach ($rows as $show) {
            $body .= ($show->title ?: 'Show #'.$show->id).' · '.$show->show_date->format('M j').' · '.($show->channel?->name ?? 'No channel').' · '.$show->streamers->pluck('name')->join(', ').' · -$'.number_format(abs((float) $show->whatnot_net), 2)."\n";
        }
        $body .= 'These are Whatnot estimates and may change. Business costs and payroll are not included.';
        return new VortexAlert($event, 'Whatnot Loss Summary — '.$label, $body, 'warning', [
            'View Whatnot Losses' => \App\Filament\Pages\WhatnotLosses::getUrl(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'channel' => $channel ?: '']),
        ]);
    }
}
