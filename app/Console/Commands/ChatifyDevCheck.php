<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class ChatifyDevCheck extends Command
{
    protected $signature = 'vortex:chatify-check';
    protected $description = 'Read-only readiness checks for the Dev Chatify integration';

    public function handle(): int
    {
        $this->info('Chatify v2 readiness (read-only)');
        $this->line('Environment: '.app()->environment());
        $this->line('Preview enabled: '.(config('chatify-preview.enabled') ? 'YES' : 'NO'));
        $this->line('Chatify class installed: '.(class_exists(\Chatify\ChatifyServiceProvider::class) ? 'YES' : 'CHECK PACKAGE'));
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'chatify') || str_starts_with($route->uri(), 'api/chatify'));
        $this->line('Chatify routes: '.$routes->count());
        $unprotected = $routes->filter(function ($route) {
            $middlewares = $route->gatherMiddleware();
            return ! collect($middlewares)->contains(fn ($m) => str_contains((string) $m, 'auth'));
        });
        $this->line('Routes without explicit auth middleware: '.$unprotected->count().' (review API/session middleware before enablement)');
        if ($unprotected->isNotEmpty()) {
            $this->warn('Review routes: '.$unprotected->pluck('uri')->take(12)->implode(', '));
        }
        try {
            DB::connection()->getPdo();
            $this->line('Database reachable: YES');
            foreach (['users', 'team_conversations', 'team_messages', 'team_message_attachments'] as $table) {
                $this->line($table.': '.(Schema::hasTable($table) ? DB::table($table)->count().' rows' : 'MISSING'));
            }
            $chatTables = collect(DB::select('SHOW TABLES'))->flatMap(fn ($row) => array_values((array) $row))
                ->filter(fn ($table) => str_contains(strtolower((string) $table), 'chatify'));
            $this->line('Tables containing chatify: '.$chatTables->implode(', '));
        } catch (\Throwable $e) {
            $this->error('Database check failed: '.$e->getMessage());
        }
        $this->line('Livewire temp writable: '.(is_writable(storage_path('app/livewire-tmp')) ? 'YES' : 'NO / DIRECTORY MISSING'));
        $this->line('Storage app writable: '.(is_writable(storage_path('app')) ? 'YES' : 'NO'));
        $this->line('Chatify asset directory: '.(is_dir(public_path('vendor/chatify')) ? 'PRESENT' : 'MISSING'));
        $this->warn('This command does not verify browser auth, file uploads, data migration or WebSocket delivery.');
        return self::SUCCESS;
    }
}
