<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Referral extends Model
{
    protected $fillable = [
        'affiliate_id',
        'referred_user_id',
        'referral_code',
        'tracking_token',
        'source',
        'landing_page',
        'ip_hash',
        'status',
        'registered_at',
        'converted_at',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'converted_at'  => 'datetime',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function commission(): HasOne
    {
        return $this->hasOne(AffiliateCommission::class);
    }
}
