<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    protected $fillable = ['key', 'label', 'description', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];
}
