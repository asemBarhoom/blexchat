<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PlanResource;
use App\Models\Plan;
use App\Models\Setting;
use App\Services\Payments\FakeGateway;
use App\Services\Stripe\StripeClient;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;

/**
 * The checkout modal's settings: which plans its tier switcher offers, and the trial reminder day.
 * Coupons have their own screen; card payments are set in the server's environment (Stripe).
 */
class ManageCheckoutSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    public static function getNavigationLabel(): string
    {
        return __('admin/checkout.nav');
    }

    public function getTitle(): string
    {
        return __('admin/checkout.title');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return PlanResource::signupBillingGroup();
    }

    protected static ?int $navigationSort = 15;

    protected static ?string $slug = 'checkout-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'checkout_plans' => Plan::where('in_checkout', true)->pluck('id')->all(),
            'trial_reminder_days' => Setting::get(Setting::TRIAL_REMINDER_DAYS),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                CheckboxList::make('checkout_plans')->label(__('admin/checkout.plans'))
                    ->options(fn (): array => Plan::where('monthly_price', '>', 0)->orderBy('sort')->get()
                        ->mapWithKeys(fn (Plan $plan): array => [$plan->id => $plan->name])->all())
                    ->required()
                    ->helperText(__('admin/checkout.plans_help')),
                TextInput::make('trial_reminder_days')->label(__('admin/checkout.trial_reminder_days'))
                    ->numeric()->integer()->minValue(1)->maxValue(fn (): int => self::shortestTrial() - 1)->required()
                    ->helperText(fn (): string => __('admin/checkout.trial_reminder_help', ['days' => self::shortestTrial()])),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            self::stripeNote(),
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label(__('admin/common.save'))->submit('save')->keyBindings(['mod+s']),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    /**
     * Whether card payments work, and where they are set: keys live in the environment, not here.
     */
    public static function stripeNote(): Callout
    {
        $webhook = route('webhooks.stripe');
        $prices = __('admin/checkout.prices_note');

        if (config('services.payments.driver') === 'fake') {
            return FakeGateway::allowed()
                ? Callout::make(__('admin/checkout.fake_on'))->warning()
                    ->description(__('admin/checkout.fake_on_note', ['prices' => $prices]))
                : Callout::make(__('admin/checkout.fake_in_production'))->danger()
                    ->description(__('admin/checkout.fake_in_production_note'));
        }

        if (! StripeClient::isConfigured()) {
            return Callout::make(__('admin/checkout.stripe_off'))->warning()
                ->description(__('admin/checkout.stripe_off_note', ['webhook' => $webhook]));
        }

        $mode = str_starts_with((string) config('services.stripe.secret'), 'sk_live_') ? __('admin/checkout.live') : __('admin/checkout.test');

        return Callout::make(__('admin/checkout.stripe_on', ['mode' => $mode]))->info()
            ->description(__('admin/checkout.stripe_on_note', ['prices' => $prices, 'webhook' => $webhook])
                .(filled(config('services.stripe.webhook_secret')) ? '' : ' '.__('admin/checkout.webhook_secret_missing')));
    }

    /**
     * The shortest free trial on sale, in days: the reminder has to go out before it ends.
     */
    public static function shortestTrial(): int
    {
        return (int) (Plan::where('active', true)->where('trial_days', '>', 0)->min('trial_days') ?? 366);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        Plan::query()->update(['in_checkout' => false]);
        Plan::whereIn('id', $state['checkout_plans'])->update(['in_checkout' => true]);
        Setting::put(Setting::TRIAL_REMINDER_DAYS, (int) $state['trial_reminder_days']);

        Notification::make()->title(__('admin/checkout.saved'))->success()->send();
    }
}
