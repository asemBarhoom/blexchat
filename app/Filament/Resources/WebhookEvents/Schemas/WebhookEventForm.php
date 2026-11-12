<?php

namespace App\Filament\Resources\WebhookEvents\Schemas;

use Filament\Schemas\Schema;

class WebhookEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }
}
