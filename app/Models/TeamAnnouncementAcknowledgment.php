<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamAnnouncementAcknowledgment extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['acknowledged_at' => 'datetime'];

    public function announcement(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TeamAnnouncement::class, 'announcement_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
