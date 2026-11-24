<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfflineSync extends Model
{
    public $timestamps = false;

    protected $fillable = ['uuid', 'user_id', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];
}
