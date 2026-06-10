<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'first_name',
        'last_name',
        'role',
        'phone_no',
        'address',
        'language',
        'status',
        'newsletter',
        'avatar',
        'company_name',
        'industry',
        'founded',
        'company_size',
        'head_office',
        'about_company',
        'bio',
        'country',
        'city',
        'zip_code',
        'state',
        'qualification',
        'gender',
        'expected_salary',
        'experience',
        'skills',
        'education',
        'cv',
        'smartcv',
        'google_id',    // Add this
        'picture',      // Add this
        'is_approved',
        'is_suspended',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'created_at',
        'updated_at',
        'email_verified_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_approved' => 'boolean',
        'is_suspended' => 'boolean',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function Socials () {
        return $this->hasMany(SocialMedia::class);
    }
    
    public function Role () {
        return $this->hasOne(Role::class, "id", "role");
    }
    
    public function UserCountry() {
        return $this->hasOne(Country::class, "code", "country");
    }

    public function smartGuide()
    {
        return $this->hasOne(\App\Models\SmartGuide::class);
    }

    public function skillstamps() {
        return $this->hasMany(UserSkillstamp::class);
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function receivedReviews()
    {
        return $this->hasMany(Review::class, 'freelancer_id');
    }

    public function givenReviews()
    {
        return $this->hasMany(Review::class, 'client_id');
    }
}