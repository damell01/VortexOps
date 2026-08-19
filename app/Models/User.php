<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'timezone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, LogsActivity, TwoFactorAuthenticatable;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('user');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // Guided tours this person has finished or dismissed, so a tour
            // introduces itself once rather than on every visit.
            'completed_tours' => 'array',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function streamer(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Streamer::class);
    }

    /** Shows this user is assigned to work in the Fulfillment Center. */
    public function assignedFulfillmentShows(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Show::class, 'show_fulfillment_user')->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->isOwner() || $this->hasRole('super_admin');
    }

    public function isAdmin(): bool
    {
        return $this->isOwner() || $this->hasAnyRole(['admin', 'super_admin']);
    }

    public function isStreamer(): bool
    {
        return $this->hasRole('streamer') && ! $this->isAdmin();
    }

    public function isFulfillment(): bool
    {
        return $this->hasRole('fulfillment') && ! $this->isAdmin();
    }

    /** Elevated fulfillment role: sees every channel's fulfillment work, not just their own assigned shows. */
    public function isFulfillmentAdmin(): bool
    {
        return $this->hasRole('fulfillment_admin') && ! $this->isAdmin();
    }

    /** Roles allowed to pick a specific channel (or "All Channels") via the topbar switcher. */
    public function canSwitchChannels(): bool
    {
        return $this->isAdmin() || $this->isFulfillmentAdmin();
    }

    /**
     * The feedback "receiver": triages tickets (status, priority, assignment,
     * deletion). Deliberately narrower than isAdmin() — a plain 'admin' role
     * holder is just another submitter/commenter here, same as any other role.
     */
    public function canManageFeedback(): bool
    {
        return $this->isOwner() || $this->hasRole('super_admin');
    }

    public function isOwner(): bool
    {
        // `?:` not `??`, and not config()'s default argument: APP_OWNER_EMAIL
        // ships blank, so the key exists holding '' and only a falsy check
        // reaches the fallback. With `??` every email was compared against ''
        // and isOwner() returned false for everyone — which silently hid the
        // module toggles, the balances widget, and every owner-only page.
        $ownerEmail = config('app.owner_email') ?: 'dbellcreations@gmail.com';

        return $this->email === $ownerEmail;
    }

    public function managedStreamers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            Streamer::class,
            'user_streamer_managers',
            'manager_id',
            'streamer_id'
        );
    }

    public function profitSharePacketsToReview(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProfitSharePacket::class, 'manager_id')
            ->where('status', 'submitted')
            ->orderBy('created_at', 'desc');
    }

    public function isManagerFor(Streamer $streamer): bool
    {
        return $this->managedStreamers()->where('streamer_id', $streamer->id)->exists();
    }
}
