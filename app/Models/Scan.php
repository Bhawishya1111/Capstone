<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scan extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const PHASE_STARTING = 'Starting';

    public const PHASE_ZAP_SPIDER = 'ZAP Spidering';

    public const PHASE_ZAP_ACTIVE = 'ZAP Active Scan';

    public const PHASE_NIKTO = 'Nikto Scan';

    public const PHASE_AI = 'AI Analysis';

    public const PHASE_COMPLETED = 'Completed';

    public const PHASE_FAILED = 'Failed';

    protected $fillable = [
        'user_id',
        'website_id',
        'url',
        'status',
        'progress',
        'phase',
        'zap_raw_output_path',
        'nikto_raw_output_path',
        'combined_raw_output_path',
        'ai_summary',
        'report_path',
        'error_message',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(ScanFinding::class);
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }

    /**
     * Non-admins: own scans, scans for assigned websites, or ad-hoc (no website) scans they started.
     */
    public function scopeVisibleToUser(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $assignedIds = $user->assignedWebsites()->pluck('websites.id');

        return $query->where(function (Builder $q) use ($user, $assignedIds) {
            $q->where('user_id', $user->id)
                ->orWhereIn('website_id', $assignedIds)
                ->orWhere(function (Builder $q2) use ($user) {
                    $q2->whereNull('website_id')->where('user_id', $user->id);
                });
        });
    }
}
