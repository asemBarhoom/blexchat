<?php

namespace App\Services\Referrals;

use App\Models\HomeBlock;
use App\Models\Plan;
use App\Models\ReferralCommission;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Number;

/**
 * The referral program: an owner shares a link, a new account that signs up through it is theirs,
 * and that customer's payments earn them a commission. Every rule is an admin setting.
 */
class ReferralProgram
{
    /** Only the customer's first payment earns a commission. */
    public const FIRST_PAYMENT = 'first_payment';

    /** Every payment earns one, for REFERRAL_MONTHS after the first (or for as long as they pay). */
    public const RECURRING = 'recurring';

    public const COMMISSION_TYPES = [self::FIRST_PAYMENT, self::RECURRING];

    /** The HomeBlock holding the program page's copy and the payout text, edited on the admin's referral page. */
    public const BLOCK = 'referral_program';

    /** Where the program's page lives on the site; links to it are hidden while the program is closed. */
    public const PAGE_URL = '/referral';

    public function isEnabled(): bool
    {
        return (bool) Setting::get(Setting::REFERRAL_ENABLED);
    }

    public function rate(): float
    {
        return (float) Setting::get(Setting::REFERRAL_RATE);
    }

    public function commissionType(): string
    {
        $type = Setting::get(Setting::REFERRAL_COMMISSION);

        return in_array($type, self::COMMISSION_TYPES, true) ? $type : self::FIRST_PAYMENT;
    }

    /**
     * With recurring commission, how many months after the first payment count; 0 for as long as the customer pays.
     */
    public function recurringMonths(): int
    {
        return max(0, (int) Setting::get(Setting::REFERRAL_MONTHS));
    }

    public function cookieDays(): int
    {
        return max(1, (int) Setting::get(Setting::REFERRAL_COOKIE_DAYS));
    }

    public function minPayout(): float
    {
        return max(0, (float) Setting::get(Setting::REFERRAL_MIN_PAYOUT));
    }

    public function currency(): string
    {
        return strtolower((string) config('services.stripe.currency'));
    }

    /**
     * Which payments earn, for a screen to show: months is null with first-payment commission and 0 for lifetime.
     *
     * @return array{type: string, months: int|null}
     */
    public function commission(): array
    {
        return ['type' => $this->commissionType(), 'months' => $this->commissionType() === self::RECURRING ? $this->recurringMonths() : null];
    }

    /**
     * What a referrer earns, as one phrase in the current language: "20% of their first payment".
     */
    public function terms(): string
    {
        $rate = $this->formattedNumber();

        return match (true) {
            $this->commissionType() === self::FIRST_PAYMENT => __('referrals.terms_first', ['rate' => $rate]),
            $this->recurringMonths() === 0 => __('referrals.terms_lifetime', ['rate' => $rate]),
            default => __('referrals.terms_recurring', ['rate' => $rate, 'months' => trans_choice('referrals.months', $this->recurringMonths())]),
        };
    }

    /**
     * What one sale of each plan on the payment page earns at today's rate: on a monthly payment,
     * and on a yearly one (twelve months at the yearly price), in the currency's units.
     *
     * @return list<array{plan: string, name: string, monthly: float, annual: float}>
     */
    public function earnings(): array
    {
        $rate = $this->rate();

        return Plan::where('active', true)->where('in_checkout', true)->where('monthly_price', '>', 0)->orderBy('sort')->get()
            ->map(fn (Plan $plan): array => [
                'plan' => $plan->slug,
                'name' => $plan->name,
                'monthly' => round($plan->monthly_price * $rate / 100, 2),
                'annual' => round($plan->annual_price * 12 * $rate / 100, 2),
            ])->values()->all();
    }

    /**
     * The values the admin can write into the program's texts: {rate}, {terms}, {months}, {cookie_days},
     * {min_payout}, and {up_to} (the most one sale earns, with its currency).
     *
     * @return array<string, string>
     */
    public function placeholders(): array
    {
        $upTo = (float) collect($this->earnings())->max('annual');

        return [
            '{up_to}' => (string) Number::currency($upTo, in: strtoupper($this->currency()), locale: 'en'),
            '{rate}' => $this->formattedNumber(),
            '{terms}' => $this->terms(),
            '{months}' => (string) $this->recurringMonths(),
            '{cookie_days}' => (string) $this->cookieDays(),
            '{min_payout}' => $this->formattedNumber($this->minPayout()),
        ];
    }

    /**
     * The program's copy in the current language (English for any text not translated), with the
     * placeholders filled in, and whether English stood in. Null until the copy is seeded.
     *
     * @return array{texts: array<string, mixed>, fallback: bool}|null
     */
    public function copy(): ?array
    {
        $block = HomeBlock::where('key', self::BLOCK)->first();

        if ($block === null) {
            return null;
        }

        $fallbackLocale = (string) config('app.fallback_locale');
        $locale = App::getLocale();
        $all = $block->getTranslations('texts');
        $filled = fn (array $texts): array => array_filter(Arr::dot($texts), fn (mixed $value): bool => is_string($value) && $value !== '');
        $english = $filled($all[$fallbackLocale] ?? []);
        $translated = $locale === $fallbackLocale ? [] : $filled($all[$locale] ?? []);

        $texts = array_replace_recursive($block->settings ?? [], Arr::undot($english), Arr::undot($translated));
        $placeholders = $this->placeholders();
        $placeholders['{payout_method}'] = strtr((string) ($texts['payout_method'] ?? ''), $placeholders);

        array_walk_recursive($texts, function (mixed &$value) use ($placeholders): void {
            if (is_string($value)) {
                $value = strtr($value, $placeholders);
            }
        });

        return ['texts' => $texts, 'fallback' => $locale !== $fallbackLocale && array_diff_key($english, $translated) !== []];
    }

    /**
     * The link an owner shares: the site's home page carrying their code.
     */
    public function link(User $owner): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/?ref='.$owner->referralCode();
    }

    /**
     * Gives a new account to the owner of the code it signed up with. Changes nothing while the program
     * is closed, for an unknown code, for one's own code, or for an account that already has a referrer.
     */
    public function attribute(User $user, ?string $code): bool
    {
        if (! $this->isEnabled() || $user->referred_by_user_id !== null) {
            return false;
        }

        $referrer = User::findByReferralCode($code);

        if ($referrer === null || $referrer->is($user)) {
            return false;
        }

        $user->forceFill(['referred_by_user_id' => $referrer->id, 'referred_at' => now()])->save();

        return true;
    }

    /**
     * Records what a referred customer's payment earns their referrer, once per payment (`source`),
     * at today's rate. Nothing while the program is closed, for a customer nobody referred, for a
     * payment of nothing, or for a payment the commission rules leave out.
     */
    public function recordPayment(User $customer, int $paidCents, string $currency, string $source): ?ReferralCommission
    {
        if (! $this->isEnabled() || $paidCents <= 0 || $customer->referred_by_user_id === null) {
            return null;
        }

        if (ReferralCommission::where('source', $source)->exists() || ! $this->paymentCounts($customer)) {
            return null;
        }

        $rate = $this->rate();
        $amountCents = (int) round($paidCents * $rate / 100);

        if ($amountCents <= 0) {
            return null;
        }

        try {
            return ReferralCommission::create([
                'referrer_id' => $customer->referred_by_user_id,
                'referred_user_id' => $customer->id,
                'source' => $source,
                'payment_cents' => $paidCents,
                'rate' => $rate,
                'amount_cents' => $amountCents,
                'currency' => strtolower($currency),
                'status' => ReferralCommission::PENDING,
            ]);
        } catch (UniqueConstraintViolationException) {
            // The same payment reported twice at once (the webhook and the success page): counted by the other.
            return null;
        }
    }

    /**
     * An owner's referral figures, money in the currency's units.
     *
     * @return array{signed_up: int, subscribed: int, earned: float, pending: float, paid_out: float}
     */
    public function summary(User $referrer): array
    {
        $totals = $referrer->referralCommissions()->selectRaw('status, sum(amount_cents) as cents')->groupBy('status')->pluck('cents', 'status');
        $pending = (int) ($totals[ReferralCommission::PENDING] ?? 0);
        $paid = (int) ($totals[ReferralCommission::PAID] ?? 0);

        return [
            'signed_up' => $referrer->referrals()->count(),
            'subscribed' => $referrer->referrals()->whereHas('subscription', fn ($query) => $query->where('status', 'active')->whereNotNull('stripe_subscription_id'))->count(),
            'earned' => ($pending + $paid) / 100,
            'pending' => $pending / 100,
            'paid_out' => $paid / 100,
        ];
    }

    /**
     * The first payment always counts. After it, only with recurring commission, and only within its months.
     */
    private function paymentCounts(User $customer): bool
    {
        $first = ReferralCommission::where('referred_user_id', $customer->id)->oldest('id')->first();

        if ($first === null) {
            return true;
        }

        if ($this->commissionType() === self::FIRST_PAYMENT) {
            return false;
        }

        $months = $this->recurringMonths();

        return $months === 0 || now()->lt($first->created_at->copy()->addMonths($months));
    }

    /**
     * 20 → "20", 12.5 → "12.5".
     */
    private function formattedNumber(?float $value = null): string
    {
        $value ??= $this->rate();

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
