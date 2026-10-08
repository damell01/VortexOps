<?php

namespace App\Services;

use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerAlias;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AutomaticShowAssignment
{
    public function assign(Show $target): array
    {
        $name = app(ShowHostDetection::class)->name($target->title);
        if (! $name) return ['status' => 'manual'];
        return DB::transaction(function () use ($target, $name) {
            $show = Show::query()->lockForUpdate()->find($target->id);
            if (! $show || $show->streamers()->exists() || in_array($show->status, ['closed', 'cancelled'])) return ['status' => 'unchanged'];
            $key = StreamerAlias::normalize($name);
            // Match explicit host text against complete names/aliases, never substrings (Ty != Tyler).
            $matches = Streamer::query()->streamers()->with('aliases')->get()->filter(function ($profile) use ($key) {
                return collect([$profile->name])->merge($profile->aliases->pluck('alias'))->contains(function ($identity) use ($key) {
                    $needle = StreamerAlias::normalize($identity);
                    return $needle !== '' && preg_match('/(?:^| )'.preg_quote($needle, '/').'(?: |$)/', $key) === 1;
                });
            });
            if ($matches->count() > 1 || ($matches->count() === 1 && $matches->first()->status !== 'active')) return ['status' => 'manual'];
            $profile = $matches->first();
            if (StreamerAlias::where('normalized_alias', $key)->when($profile, fn ($q) => $q->where('streamer_id', '!=', $profile->id))->exists()) return ['status' => 'manual'];
            $created = ! $profile;
            $profile ??= Streamer::create(['name' => $name, 'status' => 'active', 'member_type' => 'streamer', 'streamer_type' => 'in_house']);
            $accountCreated = false;
            if (! $profile->user_id) {
                $base = substr(Str::slug($profile->name, ''), 0, 50) ?: 'streamer'.$profile->id;
                $email = $base.'@vortexops.tech';
                for ($suffix = 2; User::where('email', $email)->exists(); $suffix++) $email = $base.$suffix.'@vortexops.tech';
                $user = User::create(['name' => $profile->name, 'email' => $email, 'password' => 'password123!']);
                $user->forceFill(['must_change_password' => true])->save();
                $user->assignRole(Role::findOrCreate('streamer', 'web'));
                $profile->update(['user_id' => $user->id, 'email' => $email]);
                $accountCreated = true;
            }
            $profile->aliases()->firstOrCreate(['normalized_alias' => $key], ['alias' => $name, 'source' => 'show_title']);
            $show->streamers()->sync([$profile->id => ['is_primary' => true]]);
            return ['status' => 'assigned', 'profile_created' => $created, 'account_created' => $accountCreated, 'streamer' => $profile->name];
        });
    }
}
