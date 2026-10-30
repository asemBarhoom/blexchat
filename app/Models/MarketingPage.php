<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingPage extends Model
{
    protected $fillable = ['slug', 'locale', 'content'];

    protected function casts(): array
    {
        return ['content' => 'array'];
    }
}
