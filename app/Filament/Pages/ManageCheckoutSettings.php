<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PlanResource;
use App\Models\Plan;
use App\Models\Setting;
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

    protected static ?string $navigationLabel = 'إعدادات الدفع';

    protected static ?string $title = 'إعدادات صفحة الدفع';

    protected static string|\UnitEnum|null $navigationGroup = PlanResource::NAVIGATION_GROUP;

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
                CheckboxList::make('checkout_plans')->label('الخطط المعروضة في صفحة الدفع')
                    ->options(fn (): array => Plan::where('monthly_price', '>', 0)->orderBy('sort')->get()
                        ->mapWithKeys(fn (Plan $plan): array => [$plan->id => $plan->name])->all())
                    ->required()
                    ->helperText('الخطط التي يتنقّل بينها العميل في صفحة الدفع، بترتيب الخطط.'),
                TextInput::make('trial_reminder_days')->label('يوم تذكير التجربة المجانية')
                    ->numeric()->integer()->minValue(1)->maxValue(fn (): int => self::shortestTrial() - 1)->required()
                    ->helperText(fn (): string => 'في أي يوم من التجربة المجانية نرسل بريد التذكير. يجب أن يسبق نهاية أقصر تجربة ('.self::shortestTrial().' يوماً).'),
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
                        Action::make('save')->label('حفظ')->submit('save')->keyBindings(['mod+s']),
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
        $prices = 'الأسعار تُرسل من جدول الخطط مباشرة (السنوي = السعر الشهري للخطة السنوية × 12)، والقسيمة تُخصم من أول دفعة.';

        if (! StripeClient::isConfigured()) {
            return Callout::make('الدفع بالبطاقة (Stripe) غير مُفعّل')->warning()
                ->description("أضف STRIPE_SECRET وSTRIPE_WEBHOOK_SECRET إلى متغيرات البيئة. حتى ذلك الحين، الخطط المدفوعة ترد بـ «الدفع غير متاح» والتجربة المجانية تعمل كالمعتاد. عنوان الـ webhook: {$webhook}");
        }

        $mode = str_starts_with((string) config('services.stripe.secret'), 'sk_live_') ? 'الوضع الحقيقي (live)' : 'وضع الاختبار (test)';

        return Callout::make("الدفع بالبطاقة (Stripe) مُفعّل — {$mode}")->info()
            ->description("{$prices} في Stripe أضف webhook إلى {$webhook} للأحداث: checkout.session.completed وcheckout.session.expired وinvoice.paid وcustomer.subscription.deleted."
                .(filled(config('services.stripe.webhook_secret')) ? '' : ' تنبيه: STRIPE_WEBHOOK_SECRET فارغ، فلن تُقبل أحداث Stripe.'));
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

        Notification::make()->title('تم حفظ إعدادات الدفع')->success()->send();
    }
}
