<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmartStartRequest extends Model
{
    protected $table = 'smartstart_requests';

    protected $fillable = [
        'employer_id', 'project_type', 'title', 'description',
        'budget_min', 'budget_max', 'deadline', 'urgency',
        'file_urls', 'ref_links', 'extra_notes', 'status',
        'selected_freelancer_id',
        'payment_reference', 'payment_status', 'paid_at',
    ];

    protected $casts = [
        'file_urls' => 'array',
        'paid_at'   => 'datetime',
    ];

    public function employer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employer_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SmartStartAssignment::class, 'smartstart_request_id');
    }

    public function selectedFreelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_freelancer_id');
    }
}