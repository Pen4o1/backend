<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailVerification extends Model
{
    protected $table = 'email_verifications';
    
    protected $fillable = ['email', 'verification_code', 'expires_at'];

    public $timestamps = true;

    public function isExpired()
    {
        return now()->greaterThan($this->expires_at);
    }
}
