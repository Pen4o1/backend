<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class UserGoal extends Model
{
    protected $table = 'user_goals';
    protected $fillable = [
        'user_id', 
        'activity_level', 
        'goal', 
        'caloric_target',
        'target_weight'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
