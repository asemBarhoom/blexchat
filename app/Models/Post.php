<?php

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    private const MEDIA_TYPES = ['IMAGE' => 'image', 'VIDEO' => 'video', 'CAROUSEL_ALBUM' => 'carousel'];

    /** A Facebook Page post's `status_type`, as the media types the app knows; a text post has none. */
    private const FACEBOOK_MEDIA_TYPES = ['added_photos' => 'image', 'added_video' => 'video'];

    protected $fillable = [
        'channel_id',
        'platform_post_id',
        'caption',
        'thumbnail_url',
        'permalink',
        'media_type',
        'like_count',
        'comment_count',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
        ];
    }

    /**
     * Columns for a post as Instagram's media endpoint describes it.
     *
     * @param  array{caption?: string, media_product_type?: string|null, media_type?: string, timestamp?: string, thumbnail_url?: string, media_url?: string, permalink?: string, like_count?: int, comments_count?: int}  $media
     * @return array<string, mixed>
     */
    public static function attributesFromInstagram(array $media): array
    {
        return [
            'caption' => $media['caption'] ?? null,
            'media_type' => ($media['media_product_type'] ?? null) === 'REELS' ? 'reel' : (self::MEDIA_TYPES[$media['media_type'] ?? ''] ?? null),
            'thumbnail_url' => $media['thumbnail_url'] ?? $media['media_url'] ?? null,
            'permalink' => $media['permalink'] ?? null,
            'like_count' => $media['like_count'] ?? null,
            'comment_count' => $media['comments_count'] ?? null,
            'posted_at' => isset($media['timestamp']) ? Carbon::parse($media['timestamp']) : null,
        ];
    }

    /**
     * @param  array{title?: string, video_description?: string, cover_image_url?: string, share_url?: string, like_count?: int, comment_count?: int, create_time?: int}  $video
     * @return array<string, mixed>
     */
    public static function attributesFromTikTok(array $video): array
    {
        return [
            'caption' => $video['video_description'] ?? $video['title'] ?? null,
            'media_type' => 'video',
            'thumbnail_url' => $video['cover_image_url'] ?? null,
            'permalink' => $video['share_url'] ?? null,
            'like_count' => $video['like_count'] ?? null,
            'comment_count' => $video['comment_count'] ?? null,
            'posted_at' => isset($video['create_time']) ? Carbon::createFromTimestamp((int) $video['create_time']) : null,
        ];
    }

    /**
     * Columns for a post as a Facebook Page's posts endpoint describes it.
     *
     * Likes are the post's reactions of any kind; comments are its top-level ones.
     *
     * @param  array{message?: string, full_picture?: string, permalink_url?: string, status_type?: string, created_time?: string, reactions?: array<string, mixed>, comments?: array<string, mixed>}  $post
     * @return array<string, mixed>
     */
    public static function attributesFromFacebook(array $post): array
    {
        return [
            'caption' => $post['message'] ?? null,
            'media_type' => self::FACEBOOK_MEDIA_TYPES[$post['status_type'] ?? ''] ?? null,
            'thumbnail_url' => $post['full_picture'] ?? null,
            'permalink' => $post['permalink_url'] ?? null,
            'like_count' => data_get($post, 'reactions.summary.total_count'),
            'comment_count' => data_get($post, 'comments.summary.total_count'),
            'posted_at' => isset($post['created_time']) ? Carbon::parse($post['created_time']) : null,
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
