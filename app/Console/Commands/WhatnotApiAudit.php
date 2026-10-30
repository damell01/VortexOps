<?php

namespace App\Console\Commands;

use App\Models\WhatnotApiConnection;
use App\Models\WhatnotChannel;
use App\Services\WhatnotSellerApiClient;
use Illuminate\Console\Command;
use Throwable;

final class WhatnotApiAudit extends Command
{
    protected $signature = 'whatnot:api-audit {--channel= : Existing Whatnot channel ID} {--live : Query Whatnot read-only}';
    protected $description = 'Audit Whatnot Seller API configuration; optional read-only API probe. Never imports data.';

    public function handle(WhatnotSellerApiClient $client): int
    {
        $query = WhatnotChannel::query()->orderBy('id');
        if ($this->option('channel')) {
            $query->whereKey((int) $this->option('channel'));
        }

        foreach ($query->get() as $channel) {
            $connection = WhatnotApiConnection::where('whatnot_channel_id', $channel->id)->first();
            $configured = $connection && filled($connection->access_token);
            $this->line($channel->name.': '.($configured ? 'credentials stored' : 'credentials pending').'; sync disabled by default');
            if (! $this->option('live') || ! $configured) {
                continue;
            }
            try {
                $response = $client->get($connection, 'shows');
                $payload = $response->json();
                $this->line('  HTTP '.$response->status().'; JSON '.(is_array($payload) ? 'valid' : 'unavailable'));
                if ($response->successful() && is_array($payload)) {
                    $this->line('  Top-level fields: '.implode(', ', array_slice(array_keys($payload), 0, 15)));
                }
            } catch (Throwable $e) {
                $this->warn('  Read-only probe failed ('.get_class($e).')');
            }
        }

        return self::SUCCESS;
    }
}
