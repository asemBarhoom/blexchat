<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'google_id', 'password', 'business_name', 'is_admin'])]
#[Hidden(['password', 'remember_token', 'google_id'])]
class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Shop owners sign up through the API; only staff marked as admins may use the panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    /**
     * The account for an address proven by an emailed link or Google, created on first sign-in.
     * Either proof verifies the email.
     *
     * @return array{0: self, 1: bool} The user, and whether the account was just created.
     */
    public static function signInWithEmail(string $email, ?string $name = null): array
    {
        $user = self::firstOrNew(['email' => Str::lower($email)]);
        $isNew = ! $user->exists;

        $user->name ??= $name;
        $user->email_verified_at ??= now();
        $user->save();

        return [$user, $isNew];
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    public function automations(): HasMany
    {
        return $this->hasMany(Automation::class);
    }
}
