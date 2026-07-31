<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['actor_id', 'subject_type', 'subject_id', 'event', 'description', 'old_values', 'new_values', 'ip_address', 'user_agent'];

    protected $casts = ['old_values' => 'array', 'new_values' => 'array'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject()
    {
        return $this->morphTo();
    }
}
