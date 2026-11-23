<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('qa:cleanup {--execute} {--force} {--backup-reference=}')]
#[Description('Inventory or remove the two legacy seeded QA accounts')]
class CleanupQaData extends Command
{
    /** @var array<int, string> */
    protected $aliases = ['app:clean-qa-data'];

    /** @var array<string, array{name: string, business: ?string, admin: bool}> */
    private const CANDIDATES = [
        'admin@blexchat.test' => ['name' => 'مدير النظام', 'business' => null, 'admin' => true],
        'nour@nourboutique.com' => ['name' => 'Nour', 'business' => 'Nour Boutique', 'admin' => false],
    ];

    /** @var array<string, string> */
    private const NOUR_CHANNELS = [
        'instagram' => '17841400000000001',
        'whatsapp' => '966501234567',
        'tiktok' => '7300000000000000001',
    ];

    public function handle(): int
    {
        $users = User::query()
            ->whereIn('email', array_keys(self::CANDIDATES))
            ->withCount(['channels', 'automations', 'contacts', 'teammates', 'workspaces'])
            ->with('subscription:id,user_id,stripe_subscription_id')
            ->get()
            ->keyBy('email');
        $rows = [];
        $unsafe = [];

        foreach (self::CANDIDATES as $email => $fingerprint) {
            $user = $users->get($email);
            if ($user === null) {
                $rows[] = [$email, 'absent', '-', '-', '-', '-'];

                continue;
            }

            $reasons = $this->unsafeReasons($user, $fingerprint);
            $unsafe = [...$unsafe, ...array_map(fn (string $reason): string => "{$email}: {$reason}", $reasons)];
            $conversations = DB::table('conversations')->whereIn('channel_id', $user->channels()->select('id'))->count();
            $rows[] = [$email, $reasons === [] ? 'seed fingerprint matched' : 'REFUSED', (string) $user->channels_count,
                (string) $user->automations_count, (string) $user->contacts_count, (string) $conversations];
        }

        $this->table(['Account', 'Safety', 'Channels', 'Automations', 'Contacts', 'Conversations'], $rows);
        if ($unsafe !== []) {
            foreach ($unsafe as $reason) {
                $this->error($reason);
            }

            return self::FAILURE;
        }

        if (! $this->option('execute')) {
            $this->info('Dry run only. No data was changed.');

            return self::SUCCESS;
        }

        if (! $this->option('force') || blank($this->option('backup-reference'))) {
            $this->error('Deletion requires both --force and a non-empty --backup-reference.');

            return self::FAILURE;
        }

        $deleted = DB::transaction(function () use ($users): int {
            $count = $users->count();
            User::query()->whereKey($users->pluck('id'))->delete();

            return $count;
        });
        $this->info("Deleted {$deleted} QA account(s). Backup: {$this->option('backup-reference')}");

        return self::SUCCESS;
    }

    /** @param array{name: string, business: ?string, admin: bool} $fingerprint
     * @return list<string>
     */
    private function unsafeReasons(User $user, array $fingerprint): array
    {
        $reasons = [];
        if ($user->name !== $fingerprint['name'] || $user->business_name !== $fingerprint['business'] || $user->is_admin !== $fingerprint['admin']) {
            $reasons[] = 'profile no longer matches the seeded QA fingerprint';
        }
        if ($user->teammates_count > 0) {
            $reasons[] = 'has teammates';
        }
        if ($user->subscription !== null || filled($user->stripe_customer_id)) {
            $reasons[] = 'has billing data';
        }

        $unexpected = $user->channels()->get(['platform', 'account_id'])->reject(
            fn ($channel): bool => $user->email === 'nour@nourboutique.com'
                && (self::NOUR_CHANNELS[$channel->platform] ?? null) === $channel->account_id
        );
        if ($unexpected->isNotEmpty()) {
            $reasons[] = 'has a channel outside the seeded QA identifiers';
        }
        if ($user->email === 'admin@blexchat.test' && ($user->automations_count > 0 || $user->contacts_count > 0 || $user->workspaces_count > 0)) {
            $reasons[] = 'admin account owns application data';
        }

        return $reasons;
    }
}
