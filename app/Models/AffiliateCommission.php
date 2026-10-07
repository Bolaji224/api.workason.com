<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommission extends Model
{
    protected $fillable = [
        'affiliate_id',
        'referral_id',
        'transaction_id',
        'amount',
        'currency',
        'rate',
        'status',
        'approved_at',
        'paid_at',
        'approved_by',
        'paid_by',
        'notes',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'rate'        => 'decimal:4',
        'approved_at' => 'datetime',
        'paid_at'     => 'datetime',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(EmployerPayment::class, 'transaction_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
