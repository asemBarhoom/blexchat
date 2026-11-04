<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\Plan;
use App\Models\Subscription;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionResource extends Resource
{
    /** @var array<string, string> */
    public const STATUS_LABELS = ['active' => 'فعّال', 'trialing' => 'فترة تجريبية', 'trial_ended' => 'انتهت التجربة (مجانية)'];

    protected static ?string $model = Subscription::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'الاشتراكات';

    protected static ?string $modelLabel = 'اشتراك';

    protected static ?string $pluralModelLabel = 'الاشتراكات';

    protected static string|\UnitEnum|null $navigationGroup = PlanResource::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label('المستخدم')->relationship('user', 'email')->searchable()->preload()->required()
                ->unique(ignoreRecord: true)
                ->validationMessages(['unique' => 'لهذا المستخدم اشتراك بالفعل؛ عدّله بدل إضافة آخر.']),
            Select::make('plan_id')->label('الخطة')->required()
                ->relationship('plan', 'slug', fn (Builder $query): Builder => $query->orderBy('sort'))
                ->getOptionLabelFromRecordUsing(fn (Plan $record): string => $record->name),
            Select::make('billing_period')->label('الفوترة')->options(['monthly' => 'شهري', 'annual' => 'سنوي'])->required()->default('monthly'),
            Select::make('status')->label('الحالة')->options(array_intersect_key(self::STATUS_LABELS, array_flip(Subscription::STATUSES)))
                ->required()->default('active')->live(),
            DateTimePicker::make('trial_ends_at')->label('نهاية التجربة')
                ->required(fn (Get $get): bool => $get('status') === 'trialing')
                ->visible(fn (Get $get): bool => $get('status') === 'trialing'),
            DateTimePicker::make('trial_used_at')->label('تاريخ استخدام التجربة')
                ->helperText('لكل حساب تجربة واحدة. امسحه ليسمح للمستخدم بتجربة جديدة.'),
            // Stripe sets these by webhook; editing them here would not change what Stripe charges.
            TextInput::make('stripe_subscription_id')->label('معرّف اشتراك Stripe')->disabled()->dehydrated(false)->placeholder('— بدون دفع بالبطاقة')
                ->helperText('يُملأ تلقائيًا عند الدفع في Stripe. الإلغاء والاسترداد من لوحة Stripe.'),
            DateTimePicker::make('current_period_end')->label('نهاية الفترة المدفوعة')->disabled()->dehydrated(false)
                ->visible(fn (?Subscription $record): bool => $record?->paidByCard() === true),
            TextInput::make('stripe_customer_id')->label('معرّف عميل Stripe')->disabled()->dehydrated(false)
                ->formatStateUsing(fn (?Subscription $record): ?string => $record?->user?->stripe_customer_id)
                ->visibleOn(['edit', 'view']),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'plan']))
            ->columns([
                TextColumn::make('user.email')->label('المستخدم')->searchable(),
                TextColumn::make('plan.name')->label('الخطة')->badge(),
                TextColumn::make('billing_period')->label('الفوترة')->formatStateUsing(fn (string $state): string => $state === 'annual' ? 'سنوي' : 'شهري'),
                TextColumn::make('status')->label('الحالة')->badge()
                    ->state(fn (Subscription $record): string => $record->trialEnded() ? 'trial_ended' : $record->status)
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'trialing' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('trial_ends_at')->label('نهاية التجربة')->dateTime('Y-m-d')->placeholder('—')->sortable(),
                IconColumn::make('paid_by_card')->label('بالبطاقة')->boolean()
                    ->state(fn (Subscription $record): bool => $record->paidByCard()),
                TextColumn::make('current_period_end')->label('التجديد القادم')->dateTime('Y-m-d')->placeholder('—')->sortable(),
                TextColumn::make('stripe_subscription_id')->label('اشتراك Stripe')->placeholder('—')->copyable()->searchable()
                    ->url(fn (Subscription $record): ?string => $record->paidByCard() ? self::stripeDashboardUrl('subscriptions/'.$record->stripe_subscription_id) : null, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user.stripe_customer_id')->label('عميل Stripe')->placeholder('—')->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label('آخر تغيير')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('plan_id')->label('الخطة')
                    ->relationship('plan', 'slug')
                    ->getOptionLabelFromRecordUsing(fn (Plan $record): string => $record->name),
                SelectFilter::make('status')->label('الحالة')->options(array_intersect_key(self::STATUS_LABELS, array_flip(Subscription::STATUSES))),
            ])
            ->recordActions([
                ViewAction::make()->label('عرض'),
                EditAction::make()->label('تعديل'),
                DeleteAction::make()->label('حذف')->modalDescription('يعود المستخدم إلى خطوة اختيار الخطة.'),
            ]);
    }

    /**
     * A page of the Stripe dashboard, in test mode while the key is a test key.
     */
    public static function stripeDashboardUrl(string $path): string
    {
        $mode = str_starts_with((string) config('services.stripe.secret'), 'sk_live_') ? '' : 'test/';

        return "https://dashboard.stripe.com/{$mode}{$path}";
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageSubscriptions::route('/')];
    }
}
