<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'name',
        'description',
        'status',
        'priority',
        'color',
        'owner_id',
        'target_date',
    ];

    protected $casts = [
        'target_date' => 'date',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('sort_order');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ProjectUpdate::class)->latest();
    }

    public function milestoneProgress(): array
    {
        $total = $this->milestones->count();
        $done  = $this->milestones->where('status', 'completed')->count();

        return [
            'total' => $total,
            'done'  => $done,
            'pct'   => $total > 0 ? (int) round($done / $total * 100) : 0,
        ];
    }

    public static function statusLabels(): array
    {
        return [
            'planning'  => 'Planning',
            'active'    => 'Active',
            'on_hold'   => 'On Hold',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
    }

    public static function priorityLabels(): array
    {
        return [
            'low'      => 'Low',
            'medium'   => 'Medium',
            'high'     => 'High',
            'critical' => 'Critical',
        ];
    }
}
