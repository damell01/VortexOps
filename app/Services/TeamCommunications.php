<?php

namespace App\Services;

use App\Models\TeamAnnouncement;
use App\Models\TeamConversation;
use App\Models\TeamMessage;
use App\Models\User;
use App\Support\BusinessTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Owner-only development sandbox. Preview records never notify or reach other accounts. */
class TeamCommunications
{
    public static function canAccess(?User $user): bool { return $user?->isOwner() ?? false; }
    public function authorize(User $user): void { abort_unless(self::canAccess($user), 403); }

    public function conversations(User $user): Builder
    {
        $this->authorize($user);
        return TeamConversation::where('is_preview', true)->whereHas('members', fn ($q) => $q->where('users.id', $user->id));
    }

    public function inbox(User $user, string $search = '', string $filter = 'all'): Builder
    {
        $query = $this->conversations($user)->with(['members:id,name', 'latestMessage' => fn ($q) => $q->select('team_messages.id', 'team_messages.conversation_id', 'team_messages.body', 'team_messages.created_at')])->withCount('messages');
        $unread = fn ($q) => $q->where(fn ($sender) => $sender->where('sender_id', '!=', $user->id)->orWhereNull('sender_id'))
            ->whereRaw('team_messages.id > COALESCE((SELECT last_read_message_id FROM team_conversation_members WHERE conversation_id = team_messages.conversation_id AND user_id = ?), 0)', [$user->id]);
        $query->withCount(['messages as unread_count' => $unread]);
        if ($filter === 'unread') $query->whereHas('messages', $unread);
        if ($filter === 'groups') $query->where('kind', 'group');
        $search = mb_substr(trim($search), 0, 100);
        if ($search !== '') $query->where(fn ($q) => $q->where('title', 'like', '%'.$search.'%')
            ->orWhereHas('members', fn ($m) => $m->where('name', 'like', '%'.$search.'%')->where('users.id', '!=', $user->id))
            ->orWhereHas('messages', fn ($m) => $m->where('body', 'like', '%'.$search.'%')));
        return $query;
    }

    public function conversation(User $user, int $id): TeamConversation
    {
        return $this->conversations($user)->with('members:id,name')->findOrFail($id);
    }

    public function start(User $user, array $data): TeamConversation
    {
        $this->authorize($user);
        if (isset($data['title']) && is_string($data['title'])) $data['title'] = trim($data['title']);
        $data = Validator::make($data, [
            'kind' => ['required', Rule::in(['direct', 'group'])],
            'title' => ['nullable', 'string', 'max:120', 'required_if:kind,group'],
            'members' => ['required', 'array', 'min:1', 'max:50'],
            'members.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')],
        ])->validate();
        $ids = collect($data['members'])->map(fn ($id) => (int) $id)->reject(fn ($id) => $id === $user->id)->unique()->values();
        if ($ids->isEmpty() || ($data['kind'] === 'direct' && $ids->count() !== 1)) {
            throw ValidationException::withMessages(['members' => 'Choose one other person for a direct chat, or one or more people for a group.']);
        }
        $ids->push($user->id);
        $key = $data['kind'] === 'direct' ? 'preview:'.$ids->sort()->implode(':') : null;
        return DB::transaction(function () use ($data, $ids, $user, $key) {
            if ($key && ($existing = $this->conversations($user)->where('direct_key', $key)->first())) return $existing;
            // createOrFirst also handles two tabs starting the same direct chat together.
            $thread = $key ? TeamConversation::firstOrCreate(['direct_key' => $key], ['kind' => 'direct', 'created_by' => $user->id, 'is_preview' => true])
                : TeamConversation::create(['kind' => 'group', 'title' => trim($data['title']), 'created_by' => $user->id, 'is_preview' => true]);
            $thread->members()->syncWithoutDetaching($ids->all());
            return $thread;
        });
    }

    public function send(User $user, int $conversation, string $body, string $clientKey, array $uploads = []): TeamMessage
    {
        $this->authorize($user);
        $this->conversation($user, $conversation);
        $data = Validator::make(['body' => trim($body), 'client_key' => $clientKey, 'uploads' => $uploads], [
            'body' => 'required_without:uploads|string|max:5000', 'client_key' => 'required|uuid',
            'uploads' => 'array|max:3', 'uploads.*' => 'file|max:10240|mimes:pdf,png,jpg,jpeg,webp,txt,csv|extensions:pdf,png,jpg,jpeg,webp,txt,csv',
        ])->validate();
        $stored = [];
        try {
            return DB::transaction(function () use ($user, $conversation, $data, $uploads, &$stored) {
                $thread = $this->conversations($user)->lockForUpdate()->findOrFail($conversation);
                if ($existing = $thread->messages()->where('client_key', $data['client_key'])->first()) return $existing;
                $message = $thread->messages()->create(['client_key' => $data['client_key'], 'sender_id' => $user->id, 'body' => $data['body']]);
                foreach ($uploads as $upload) {
                    $path = $upload->store('communications/'.$conversation, 'local');
                    if (! is_string($path) || $path === '') throw new \RuntimeException('The attachment could not be stored. Please try again.');
                    $stored[] = $path;
                    $name = mb_substr(preg_replace('/[\x00-\x1F\x7F]/', '', basename(str_replace('\\', '/', $upload->getClientOriginalName()))), 0, 180) ?: 'attachment';
                    $message->attachments()->create(['path' => $path, 'name' => $name, 'mime' => $upload->getMimeType() ?: 'application/octet-stream', 'size' => $upload->getSize()]);
                }
                $thread->update(['last_message_at' => now()]);
                $thread->members()->updateExistingPivot($user->id, ['last_read_message_id' => $message->id]);
                // Preview creates no recipient alerts, email, push or broadcasts.
                return $message;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($stored);
            throw $e;
        }
    }

    public function saveAnnouncement(User $user, array $data, ?int $id = null): TeamAnnouncement
    {
        $this->authorize($user);
        $data += ['in_app_requested' => true, 'dismissible' => true];
        $data['title'] = trim($data['title'] ?? '');
        $data['body'] = trim($data['body'] ?? '');
        $data = Validator::make($data, [
            'title' => 'required|string|max:160', 'body' => 'required|string|max:10000',
            'tone' => ['required', Rule::in(['info', 'success', 'warning', 'danger'])],
            'audience' => ['required', Rule::in(['all', 'roles', 'users'])],
            'role_ids' => 'present|array|max:50', 'role_ids.*' => ['integer', 'distinct', Rule::exists('roles', 'id')->where('guard_name', 'web')],
            'user_ids' => 'present|array|max:500', 'user_ids.*' => 'integer|distinct|exists:users,id',
            'in_app_requested' => 'required|boolean', 'dismissible' => 'required|boolean', 'show_banner' => 'required|boolean', 'email_requested' => 'required|boolean', 'push_requested' => 'required|boolean',
            'starts_at' => 'required|date_format:Y-m-d\TH:i', 'ends_at' => 'required|date_format:Y-m-d\TH:i|after:starts_at',
        ])->validate();
        if ($data['audience'] !== 'roles') $data['role_ids'] = [];
        if ($data['audience'] !== 'users') $data['user_ids'] = [];
        if ($data['audience'] !== 'all' && ! $data[$data['audience'] === 'roles' ? 'role_ids' : 'user_ids']) {
            throw ValidationException::withMessages(['audience' => 'Choose at least one recipient or role.']);
        }
        foreach (['starts_at', 'ends_at'] as $field) $data[$field] = Carbon::createFromFormat('!Y-m-d\TH:i', $data[$field], BusinessTime::zone())->utc();
        $record = $id ? TeamAnnouncement::where('is_preview', true)->findOrFail($id) : new TeamAnnouncement(['created_by' => $user->id, 'is_preview' => true]);
        // Editing an active preview makes it a draft again, requiring an explicit preview action.
        $record->fill($data + ['status' => 'draft', 'published_at' => null])->save();
        return $record;
    }

    public function publishPreview(User $user, int $id): void
    {
        $this->authorize($user);
        $record = TeamAnnouncement::where('is_preview', true)->findOrFail($id);
        if ($record->ends_at->lte(now())) throw ValidationException::withMessages(['announcement' => 'Choose an end time in the future before activating this preview.']);
        $record->update(['status' => 'published', 'published_at' => now()]);
        session()->forget('communications.dismissed.'.$id);
    }

    public function archive(User $user, int $id): void
    {
        $this->authorize($user);
        TeamAnnouncement::where('is_preview', true)->findOrFail($id)->update(['status' => 'archived']);
    }

    public function recipientCount(User $user, TeamAnnouncement $announcement): int
    {
        $this->authorize($user);
        return User::query()->when($announcement->audience === 'users', fn ($q) => $q->whereIn('id', $announcement->user_ids ?? []))
            ->when($announcement->audience === 'roles', fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereIn('roles.id', $announcement->role_ids ?? [])->where('guard_name', 'web')))->count();
    }

    public function banners(User $user)
    {
        $this->authorize($user);
        return TeamAnnouncement::where('is_preview', true)->where('status', 'published')->where('show_banner', true)
            ->where('starts_at', '<=', now())->where('ends_at', '>', now())->latest('published_at')->limit(3)->get();
    }
}
