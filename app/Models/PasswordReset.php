<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    protected $table = 'password_reset_tokens';

    protected $fillable = [ 'email', 'token', 'created_at', 'expires_at' ];

    public $timestamps = false;

    public function isExpired()
    {
        return now()->greaterThan($this->expires_at);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }
}
