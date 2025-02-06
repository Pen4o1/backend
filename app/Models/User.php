<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Models\UserGoal; 
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\MealPlans;
use App\Models\DailyCal;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\EmailVerification;

class User extends Authenticatable implements MustVerifyEmail, JWTSubject 
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'birthdate',
        'kilos',
        'height',
        'google_id',
        'avatar', 
        'compleated',
        'profile_picture',
        'gender',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'compleated' => 'boolean',
            'gender' => 'string', 
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey(); // Usually returns the 'id' column
    }

    /**
     * Get custom claims that will be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'email' => $this->email,
            'google_id' => $this->google_id,
        ];
    }

    public function emailVerification()
    {
        return $this->hasOne(EmailVerification::class, 'email', 'email');
    }

    public function goal(): HasOne
    {
        return $this->hasOne(UserGoal::class); // If each user has one goal
    }

    public function meal_plan(): HasOne
    {
        return $this->hasOne(MealPlans::class); 
    }

    public function shopping_list(): HasOne
    {
        return $this->hasOne(ShoppingLists::class);
    }

    public function daily_macros(): HasMany
    {
        return $this->HasMany(DailyCal::class);
    }

    public function hasVerifiedEmail()
    {
        $verification = $this->emailVerification;

        // Check if there's an existing code and if it has expired
        if ($verification && $verification->expires_at > now()) {
            return true;
        }

        return false;
    }

    public function passwordReset(): HasOne
    {
        return $this->hasOne(PasswordReset::class, 'email', 'email');
    }
}
