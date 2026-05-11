<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Dispute extends Model
{
  protected $fillable = [
    'user_id',
    'user_type',        // ✅ Must be here
    'full_name',
    'email',
    'phone',
    'employer_name',
    'candidate_name',   // ✅ Must be here
    'dispute_category',
    'subject',
    'description',
    'priority',
    'status',
    'attachments',
];

    protected $casts = [
        'attachments' => 'array',
    ];
}

