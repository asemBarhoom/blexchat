<?php

namespace App\Models;

use App\Models\Concerns\HomeListItem;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class FaqItem extends Model
{
    use HasTranslations, HomeListItem;

    /** @var list<string> */
    public array $translatable = ['question', 'answer', 'link_label'];

    protected $fillable = ['question', 'answer', 'link_label', 'link_url', 'claim_unverified', 'sort', 'active'];

    protected function casts(): array
    {
        return ['claim_unverified' => 'boolean'];
    }
}
