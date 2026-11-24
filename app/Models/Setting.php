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

    /** Whether the site's "coming soon" note page is live and linked. */
    public const PREVIEW_NOTE_ENABLED = 'site.preview_note_enabled';

    /** Whether the referral program is open: its page and links are shown, and sign-ups and payments count. */
    public const REFERRAL_ENABLED = 'referral.enabled';

    /** The percentage of each counted payment the referrer earns. */
    public const REFERRAL_RATE = 'referral.rate_percent';

    /** Which payments earn a commission: ReferralProgram::FIRST_PAYMENT or ReferralProgram::RECURRING. */
    public const REFERRAL_COMMISSION = 'referral.commission';

    /** With recurring commission: the months after the first payment that count; 0 for as long as the customer pays. */
    public const REFERRAL_MONTHS = 'referral.recurring_months';

    /** How long the site remembers the referral link a visitor arrived through. */
    public const REFERRAL_COOKIE_DAYS = 'referral.cookie_days';

    /** The least a referrer must have earned before a payout, in the currency's units; 0 for none. */
    public const REFERRAL_MIN_PAYOUT = 'referral.min_payout';

    /** @var array<string, mixed> */
    public const DEFAULTS = [
        self::TRIAL_REMINDER_DAYS => 7,
        self::HELP_EMAIL => null,
        self::HELP_RESPONSE_TIME => 24,
        self::PREVIEW_NOTE_ENABLED => true,
        self::REFERRAL_ENABLED => false,
        self::REFERRAL_RATE => 20,
        self::REFERRAL_COMMISSION => 'first_payment',
        self::REFERRAL_MONTHS => 12,
        self::REFERRAL_COOKIE_DAYS => 30,
        self::REFERRAL_MIN_PAYOUT => 0,
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
