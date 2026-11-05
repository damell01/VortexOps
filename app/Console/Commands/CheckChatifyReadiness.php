<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only readiness check. Never sends messages or changes data.
 */
class CheckChatifyReadiness extends Command
{
    protected $signature = 'chatify:check-readiness';
    protected $description = 'Check Chatify v2 messaging notification prerequisites without sending alerts';

    public function handle(): int
    {
        $checks = [
            'Chatify v2 message model installed' => class_exists(\Chatify\Models\Message::class),
            'Chatify v2 conversation model installed' => class_exists(\Chatify\Models\Conversation::class),
            'Chatify v2 participant model installed' => class_exists(\Chatify\Models\ConversationParticipant::class),
            'Messages table exists' => Schema::hasTable('ch_messages'),
            'Conversations table exists' => Schema::hasTable('ch_conversations'),
            'Participants table exists' => Schema::hasTable('ch_conversation_participants'),
            'Web push subscriptions table exists' => Schema::hasTable('web_push_subscriptions'),
        ];
        foreach ([
            'ch_messages' => ['id', 'conversation_id', 'user_id', 'body', 'deleted_at'],
            'ch_conversation_participants' => ['conversation_id', 'user_id', 'last_read_at'],
        ] as $table => $columns) {
            if (! Schema::hasTable($table)) continue;
            foreach ($columns as $column) {
                $checks[$table.'.'.$column] = Schema::hasColumn($table, $column);
            }
        }
        foreach ($checks as $label => $passed) {
            $this->line(($passed ? '<info>PASS</info>' : '<error>FAIL</error>').' '.$label);
        }
        $this->line('Team messaging enabled: '.(config('chatify-preview.team_enabled') ? 'yes' : 'no'));
        $this->line('Message alerts enabled: '.(config('chatify-preview.message_notifications_enabled') ? 'yes' : 'no'));
        $this->warn('This checks prerequisites only. It does not verify end-to-end message, email, or push delivery.');
        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
