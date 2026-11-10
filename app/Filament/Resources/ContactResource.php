<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactResource\Pages;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Workspace;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContactResource extends Resource
{
    /** @return array<string, string> */
    public static function genderLabels(): array
    {
        return ['female' => __('admin/contacts.female'), 'male' => __('admin/contacts.male')];
    }

    protected static ?string $model = Contact::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    public static function getNavigationLabel(): string
    {
        return __('admin/contacts.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin/contacts.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/contacts.plural');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin/common.nav_communication');
    }

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label(__('admin/common.owner'))->relationship('user', 'email')->searchable()->preload()->required()
                ->live()->afterStateUpdated(fn (Set $set): mixed => $set('channel_id', null)),
            Select::make('channel_id')->label(__('admin/contacts.connected_channel'))
                ->relationship('channel', 'username', fn (Builder $query, Get $get): Builder => $query->where('user_id', $get('user_id')))
                ->getOptionLabelFromRecordUsing(fn (Channel $record): string => (Contact::PLATFORMS[$record->platform] ?? $record->platform).' — '.($record->username ?? $record->account_id))
                ->helperText(__('admin/contacts.channel_help')),
            Select::make('platform')->label(__('admin/common.platform'))->options(Contact::PLATFORMS)->required()->default('instagram'),
            TextInput::make('platform_id')->label(__('admin/common.platform_id'))->maxLength(255)
                ->helperText(__('admin/contacts.platform_id_help')),
            TextInput::make('name')->label(__('admin/common.name'))->required()->maxLength(200),
            TextInput::make('handle')->label(__('admin/contacts.handle'))->maxLength(255),
            TextInput::make('phone')->label(__('admin/contacts.phone'))->tel()->maxLength(30),
            TextInput::make('email')->label(__('admin/common.email'))->email()->maxLength(255),
            TextInput::make('city')->label(__('admin/common.city'))->maxLength(100),
            Select::make('gender')->label(__('admin/contacts.gender'))->options(self::genderLabels()),
            TextInput::make('language')->label(__('admin/contacts.language'))->maxLength(10)->placeholder('ar'),
            TagsInput::make('tags')->label(__('admin/common.tags'))->columnSpanFull(),
            Select::make('source_automation_id')->label(__('admin/contacts.source_automation'))
                ->relationship('sourceAutomation', 'name', fn (Builder $query, Get $get): Builder => $query->where('user_id', $get('user_id')))
                ->getOptionLabelFromRecordUsing(fn (Automation $record): string => $record->name),
            DateTimePicker::make('first_seen_at')->label(__('admin/common.first_seen'))->required()->default(now()),
            DateTimePicker::make('last_active_at')->label(__('admin/common.last_active')),
            DateTimePicker::make('consented_at')->label(__('admin/contacts.consented_at')),
            Toggle::make('added_manually')->label(__('admin/common.added_manually')),
            Toggle::make('subscribed')->label(__('admin/contacts.subscribed_to_messages'))->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'sourceAutomation', 'workspace']))
            ->columns([
                TextColumn::make('workspace.name')->label(__('admin/common.workspace'))->searchable()->toggleable(),
                TextColumn::make('name')->label(__('admin/common.name'))->searchable(['name', 'handle', 'phone', 'email'])
                    ->description(fn (Contact $record): ?string => $record->handle),
                TextColumn::make('platform')->label(__('admin/common.channel'))->badge()
                    ->formatStateUsing(fn (string $state): string => Contact::PLATFORMS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'instagram' => 'pink',
                        'whatsapp' => 'success',
                        'messenger' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('user.email')->label(__('admin/common.owner'))->searchable()->toggleable(),
                TextColumn::make('tags')->label(__('admin/common.tags'))->badge()->placeholder(__('admin/common.no_tags')),
                TextColumn::make('sourceAutomation.name')->label(__('admin/contacts.source'))
                    ->placeholder(fn (Contact $record): string => $record->added_manually ? __('admin/common.added_manually') : __('admin/contacts.messaged_directly')),
                TextColumn::make('city')->label(__('admin/common.city'))->placeholder('—')->toggleable(),
                IconColumn::make('subscribed')->label(__('admin/common.subscribed'))->boolean(),
                TextColumn::make('last_active_at')->label(__('admin/common.last_active'))->since()->placeholder('—')->sortable(),
                TextColumn::make('first_seen_at')->label(__('admin/common.first_seen'))->dateTime('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_active_at', 'desc')
            ->filters([
                SelectFilter::make('workspace_id')->label(__('admin/common.workspace'))->options(fn (): array => Workspace::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('platform')->label(__('admin/common.channel'))->options(Contact::PLATFORMS),
                SelectFilter::make('user_id')->label(__('admin/common.owner'))->relationship('user', 'email')->searchable()->preload(),
                TernaryFilter::make('added_manually')->label(__('admin/common.added_manually')),
                TernaryFilter::make('subscribed')->label(__('admin/common.subscribed')),
            ])
            ->recordActions([
                ViewAction::make()->label(__('admin/common.view')),
                EditAction::make()->label(__('admin/common.edit')),
                DeleteAction::make()->label(__('admin/common.delete'))->modalDescription(__('admin/contacts.delete_note')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageContacts::route('/')];
    }
}
