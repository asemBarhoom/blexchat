<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One admin-managed value, by dotted key.
 *
 * @property string $key
 * @property mixed $value
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /** The day of a free trial on which the reminder email goes out. */
    public const TRIAL_REMINDER_DAYS = 'checkout.trial_reminder_days';

    public const HELP_EMAIL = 'help.email';

    public const HELP_RESPONSE_TIME = 'help.response_time_hours';

    /** @var array<string, mixed> */
    public const DEFAULTS = [
        self::TRIAL_REMINDER_DAYS => 7,
        self::HELP_EMAIL => null,
        self::HELP_RESPONSE_TIME => 24,
    ];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function get(string $key): mixed
    {
        return self::where('key', $key)->first()?->value ?? self::DEFAULTS[$key] ?? null;
    }

    public static function put(string $key, mixed $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
