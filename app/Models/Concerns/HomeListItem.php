<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * A row of an admin-managed homepage list: drag-ordered by `sort`, hidden when not `active`.
 */
trait HomeListItem
{
    public static function bootHomeListItem(): void
    {
        static::creating(function (self $item): void {
            $item->sort ??= (int) static::query()->max('sort') + 1;
        });
    }

    public function initializeHomeListItem(): void
    {
        $this->mergeCasts(['sort' => 'integer', 'active' => 'boolean']);
    }

    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('active', true)->orderBy('sort')->orderBy('id');
    }
}
