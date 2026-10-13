<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayRunType extends Model
{
    protected $fillable = ['name', 'kind', 'active', 'schedule_enabled', 'frequency', 'anchor', 'auto_create'];

    protected $casts = ['active' => 'boolean', 'schedule_enabled' => 'boolean', 'auto_create' => 'boolean', 'anchor' => 'date'];

    public function scheduleConfig(): array
    {
        return ['frequency' => $this->frequency, 'anchor' => $this->anchor?->toDateString()];
    }
}
