<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Affiliate extends Model
{
    protected $fillable = [
        'user_id',
        'affiliate_id',
        'referral_code',
        'status',
        'terms_accepted_at',
        'notes',
        'total_referrals',
        'total_conversions',
        'total_commission_earned',
        'total_commission_paid',
    ];

    protected $casts = [
        'terms_accepted_at'       => 'datetime',
        'total_commission_earned' => 'decimal:2',
        'total_commission_paid'   => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getReferralLinkAttribute(): string
    {
        $base = rtrim(env('FRONTEND_URL', 'https://workason.com'), '/');
        return $base . '/?ref=' . $this->referral_code;
    }

    public function getPendingCommissionAttribute(): string
    {
        return number_format(
            $this->commissions()->where('status', 'pending')->sum('amount'),
            2
        );
    }
}
