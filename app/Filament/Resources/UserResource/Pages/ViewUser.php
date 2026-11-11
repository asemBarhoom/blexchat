<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\RelationManagers\CheckoutSessionsRelationManager;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationManager;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    /**
     * The owner's checkout attempts, on their page only.
     *
     * @return array<class-string<RelationManager>>
     */
    public function getRelationManagers(): array
    {
        return [CheckoutSessionsRelationManager::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label(__('admin/common.edit')),
            UserResource::withdrawInvitationAction(),
            UserResource::renameWorkspaceAction(),
            UserResource::deleteWorkspaceAction(),
            UserResource::deleteAction(),
        ];
    }
}
