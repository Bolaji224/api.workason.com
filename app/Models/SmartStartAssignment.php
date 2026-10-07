<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmartStartAssignment extends Model
{
    protected $table = 'smartstart_assignments';

    protected $fillable = [
        'smartstart_request_id',
        'freelancer_id',
        'assigned_by',
        'status',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(SmartStartRequest::class, 'smartstart_request_id');
    }

    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
