<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ScheduledMessage extends Model
{
    public const string STATUS_PENDING = 'pending';
    public const string STATUS_SENT = 'sent';
    public const string STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'text',
        'url',
        'chat_id',
        'raw',
        'scheduled_for',
    ];

    protected function casts(): array
    {
        return [
            'raw' => 'boolean',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeDue(Builder $query, CarbonInterface $now): Builder
    {
        // scheduled_for is stored in UTC; a non-UTC $now would be compared by
        // its wall-clock digits, so normalize before binding
        return $query->pending()->where('scheduled_for', '<=', $now->avoidMutation()->utc());
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /** Delivered within the grace period of its scheduled time — always sendable. */
    public function isOnTimeAt(CarbonInterface $now): bool
    {
        return $this->scheduled_for->diffInMinutes($now) <= config('telegram.on_time_grace_minutes');
    }

    /**
     * A late message may still be sent while it is the same day as its
     * scheduled time and before the cutoff hour ($now must already be in the
     * schedule timezone).
     */
    public function isStillSendableAt(CarbonInterface $now): bool
    {
        return $this->scheduled_for->setTimezone($now->getTimezone())->isSameDay($now)
            && $now->hour < config('telegram.late_cutoff_hour');
    }

    // forceFill: these fields are set by the dispatcher, not user input, and
    // are deliberately kept out of $fillable
    public function markSent(int $telegramMessageId): void
    {
        $this->forceFill([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'telegram_message_id' => $telegramMessageId,
        ])->save();
    }

    public function markCancelled(string $reason): void
    {
        $this->forceFill([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ])->save();
    }

    public function recordFailedAttempt(string $error): void
    {
        $this->forceFill([
            'attempts' => $this->attempts + 1,
            'last_error' => $error,
        ])->save();
    }
}
