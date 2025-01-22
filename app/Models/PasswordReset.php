<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    protected $table = 'password_reset_tokens';

    protected $fillable = [ 'email', 'token', 'created_at', 'expires_at' ];

    public $timestamps = false;

    protected $primaryKey = 'email'; // Set 'email' as the primary key
    public $incrementing = false; // Disable auto-incrementing (email is not an integer)
    protected $keyType = 'string'; // Specify the primary key type

    public function isExpired()
    {
        return now()->greaterThan($this->expires_at);
    }
}
