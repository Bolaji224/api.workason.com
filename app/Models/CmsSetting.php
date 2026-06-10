<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsSetting extends Model
{
    use HasFactory;

    protected $table = 'cms_settings';

    protected $fillable = [
        'module',
        'content',
        'version',
        'updated_by',
    ];

    /**
     * 'content' is cast to array so Eloquent handles JSON encode/decode
     * automatically. No manual json_encode/json_decode needed anywhere.
     */
    protected $casts = [
        'content' => 'array',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    /**
     * The admin who last saved this module.
     * Returns null when the record was saved via API-key auth.
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
