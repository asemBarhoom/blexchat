<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = ['provider', 'event_key', 'status', 'object', 'payload', 'error'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
