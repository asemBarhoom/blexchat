<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'google_id', 'password', 'business_name', 'inbox_accepting_new', 'is_admin', 'stripe_customer_id'])]
#[Hidden(['password', 'remember_token', 'google_id', 'stripe_customer_id'])]
class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $attributes = [
        'inbox_accepting_new' => true,
    ];

    /**
     * Shop owners sign up through the API; only staff marked as admins may use the panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    /**
     * The account for an address proven by an emailed link or Google, created on first sign-in.
     * Either proof verifies the email.
     *
     * @return array{0: self, 1: bool} The user, and whether the account was just created.
     */
    public static function signInWithEmail(string $email, ?string $name = null): array
    {
        $user = self::firstOrNew(['email' => Str::lower($email)]);
        $isNew = ! $user->exists;

        $user->name ??= $name;
        $user->email_verified_at ??= now();
        $user->save();

        return [$user, $isNew];
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'survey_completed_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'inbox_accepting_new' => 'boolean',
        ];
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    public function automations(): HasMany
    {
        return $this->hasMany(Automation::class);
    }

    public function automationFolders(): HasMany
    {
        return $this->hasMany(AutomationFolder::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * The tier in force today: the subscription's (Free again once a trial ends), or Free with none.
     */
    public function currentPlan(): Plan
    {
        return $this->subscription?->effectivePlan() ?? Plan::free();
    }

    /**
     * The sidebar's contacts meter: everyone saved against the plan's limit. People who message
     * are always saved, so an account can be over its limit, for instance after a trial ends.
     *
     * @return array{used: int, limit: int, plan: string, over_limit: bool}
     */
    public function contactsUsage(): array
    {
        $used = $this->contacts()->count();
        $plan = $this->currentPlan();

        return ['used' => $used, 'limit' => $plan->contacts_limit, 'plan' => $plan->slug, 'over_limit' => $used > $plan->contacts_limit];
    }

    /**
     * Adding someone by hand stops at the limit.
     */
    public function canAddContact(): bool
    {
        $usage = $this->contactsUsage();

        return $usage['used'] < $usage['limit'];
    }

    public function checkoutSessions(): HasMany
    {
        return $this->hasMany(CheckoutSession::class);
    }

    /**
     * Whether choosing this plan starts a free trial: the plan has one and this account has not
     * had its one trial, or is trying this very plan now.
     */
    public function canStartTrialOn(Plan $plan): bool
    {
        if (! $plan->hasTrial()) {
            return false;
        }

        $current = $this->subscription()->first();

        return $current?->trial_used_at === null || ($current->onTrial() && $current->plan_id === $plan->id);
    }

    public function surveyAnswers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class);
    }

    /**
     * The first onboarding step not done yet, in the frontend's order: connect a channel (W05–W07),
     * the survey (W08), a plan (W09). `done` sends the owner on to the first automation or the app.
     */
    public function onboardingStep(): string
    {
        return match (true) {
            ! $this->channels()->exists() => 'channel',
            $this->survey_completed_at === null => 'survey',
            ! $this->subscription()->exists() => 'plan',
            default => 'done',
        };
    }
}
