<?php

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Someone who commented on or messaged one of the owner's channels, or whom the owner added by hand
 * with their consent (W22, W23). Each counts against the plan's contacts_limit.
 *
 * @property string $platform
 * @property string|null $platform_id
 * @property string $name
 * @property list<string> $tags
 * @property bool $added_manually
 * @property Carbon $first_seen_at
 * @property Carbon|null $last_active_at
 */
#[Fillable(['user_id', 'channel_id', 'platform', 'platform_id', 'name', 'handle', 'avatar', 'phone', 'email', 'city', 'gender', 'language', 'tags', 'subscribed', 'source_automation_id', 'added_manually', 'consented_at', 'first_seen_at', 'last_active_at'])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    /** The channels a contact can come from, with the names the frontend shows. */
    public const PLATFORMS = [
        'instagram' => 'Instagram',
        'whatsapp' => 'WhatsApp',
        'tiktok' => 'TikTok',
        'messenger' => 'Messenger',
    ];

    public const GENDERS = ['female', 'male'];

    /** How a contact arrived: an automation answered them, they messaged, or the owner added them. */
    public const SOURCES = ['automation', 'messaged', 'added'];

    public const SORTS = ['recent', 'newest', 'name'];

    protected $attributes = [
        'tags' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'json:unicode',
            'subscribed' => 'boolean',
            'added_manually' => 'boolean',
            'consented_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_active_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function sourceAutomation(): BelongsTo
    {
        return $this->belongsTo(Automation::class, 'source_automation_id')->withTrashed();
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class)->latestOfMany('last_message_at');
    }

    /**
     * The contact for someone who just commented or messaged on a channel, created the first time
     * they do. Their name and picture are filled in when Instagram shares them, never overwritten
     * with nothing.
     */
    public static function recordActivity(Channel $channel, string $platformId, ?string $name = null, ?string $handle = null, ?string $avatar = null): self
    {
        $contact = self::firstOrNew(['channel_id' => $channel->id, 'platform_id' => $platformId]);

        $contact->fill([
            'user_id' => $channel->user_id,
            'platform' => $channel->platform,
            'name' => $name ?? $contact->name ?? $handle ?? $platformId,
            'handle' => $handle ?? $contact->handle,
            'avatar' => $avatar ?? $contact->avatar,
            'last_active_at' => now(),
        ]);
        $contact->first_seen_at ??= now();
        $contact->save();

        return $contact;
    }

    /**
     * What the lists show under the name: their username, else the phone number, else the name.
     * Never empty, so a conversation always has something to match its contact by.
     */
    public function displayHandle(): string
    {
        return $this->handle ?? $this->phone ?? $this->name;
    }

    /**
     * Matches the name, handle, city, a tag, or the channel's name, ignoring case.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $like = '%'.mb_strtolower(trim($term)).'%';
        $platforms = array_keys(array_filter(self::PLATFORMS, fn (string $label): bool => str_contains(mb_strtolower($label), mb_strtolower(trim($term)))));

        $query->where(function (Builder $query) use ($like, $platforms): void {
            foreach (['name', 'handle', 'city', 'tags'] as $column) {
                $query->orWhereRaw("lower({$column}) like ?", [$like]);
            }

            $query->when($platforms !== [], fn (Builder $query): Builder => $query->orWhereIn('platform', $platforms));
        });
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeFromSource(Builder $query, string $source): void
    {
        match ($source) {
            'automation' => $query->whereNotNull('source_automation_id'),
            'added' => $query->where('added_manually', true),
            default => $query->whereNull('source_automation_id')->where('added_manually', false),
        };
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeWithTag(Builder $query, string $tag): void
    {
        $query->whereRaw('lower(tags) like ?', ['%'.json_encode(mb_strtolower($tag), JSON_UNESCAPED_UNICODE).'%']);
    }

    /**
     * Most recently active first by default; contacts never active sort last.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSortedBy(Builder $query, string $sort): void
    {
        match ($sort) {
            'newest' => $query->orderByDesc('first_seen_at'),
            'name' => $query->orderBy('name'),
            default => $query->orderByRaw('last_active_at is null')->orderByDesc('last_active_at'),
        };

        $query->orderByDesc('id');
    }
}
