<?php

namespace App\Models;

use App\Models\Concerns\HomeListItem;
use Database\Factories\UseCaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Translatable\HasTranslations;

class UseCase extends Model
{
    /** @use HasFactory<UseCaseFactory> */
    use HasFactory, HasTranslations, HomeListItem;

    public const SHAPES = ['rounded', 'blob', 'arch', 'circle', 'pill', 'house'];

    public const TONES = ['brand', 'tint', 'ink'];

    /** @var list<string> */
    public array $translatable = ['name', 'line'];

    protected $fillable = ['key', 'slug', 'name', 'line', 'shape', 'tone', 'sort', 'active'];

    protected static function booted(): void
    {
        // Deleting a card keeps its solution page: the page takes over the slug, name, shape and tone.
        static::deleting(function (self $useCase): void {
            $useCase->solution?->forceFill([
                'use_case_id' => null,
                'slug' => $useCase->slug,
                'name' => $useCase->getTranslations('name'),
                'shape' => $useCase->shape,
                'tone' => $useCase->tone,
            ])->save();
        });
    }

    /**
     * The /solutions/{slug} page the card links to, which reads its slug, name, shape and tone from here.
     */
    public function solution(): HasOne
    {
        return $this->hasOne(Solution::class);
    }
}
