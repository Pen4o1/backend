<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    protected $table = 'password_reset_tokens';

    protected $fillable = [ 'email', 'token', 'expires_at' ];

    public $timestamps = true;

    public function isExpired()
    {
        return now()->greaterThan($this->expires_at);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }
}
