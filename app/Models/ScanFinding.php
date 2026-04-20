<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanFinding extends Model
{
    public const SOURCE_ZAP = 'zap';

    public const SOURCE_NIKTO = 'nikto';

    public const SOURCE_NUCLEI = 'nuclei';

    protected $fillable = [
        'scan_id',
        'source',
        'tool_rule_id',
        'title',
        'severity',
        'confidence',
        'normalized_severity',
        'description',
        'evidence',
        'remediation',
        'endpoint',
        'raw_metadata',
    ];

    protected function casts(): array
    {
        return [
            'raw_metadata' => 'array',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }
}
