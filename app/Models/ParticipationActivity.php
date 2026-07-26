<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipationActivity extends Model
{
    public const TYPE_DAILY_LOGIN = 'daily_login';

    public const TYPE_TOPIC_CREATED = 'topic_created';

    public const TYPE_REPLY_POSTED = 'reply_posted';

    public const TYPE_QUIZ_COMPLETED = 'quiz_completed';

    /** @var list<string> Ordered from highest to lowest weight. */
    public const TYPES = [
        self::TYPE_QUIZ_COMPLETED,
        self::TYPE_TOPIC_CREATED,
        self::TYPE_REPLY_POSTED,
        self::TYPE_DAILY_LOGIN,
    ];

    protected $fillable = [
        'user_id',
        'activity_type',
        'points',
        'subject_type',
        'subject_id',
        'activity_date',
    ];

    protected $casts = [
        'points' => 'decimal:2',
        'activity_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Human-readable label for an activity type.
     */
    public static function label(string $type): string
    {
        return match ($type) {
            self::TYPE_QUIZ_COMPLETED => 'Quizzes completed',
            self::TYPE_TOPIC_CREATED => 'Topics created',
            self::TYPE_REPLY_POSTED => 'Replies posted',
            self::TYPE_DAILY_LOGIN => 'Active days',
            default => $type,
        };
    }
}
