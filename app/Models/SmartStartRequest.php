// app/Models/SmartStartRequest.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmartStartRequest extends Model
{
    protected $fillable = [
        'employer_id', 'project_type', 'title', 'description',
        'budget_min', 'budget_max', 'deadline', 'urgency',
        'file_urls', 'ref_links', 'extra_notes', 'status',
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
}