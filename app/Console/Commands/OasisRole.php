<?php

namespace App\Console\Commands;

use App\Actions\Moderation\ChangeRole;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

/** Trusted operator bootstrap for the first admin; does not bypass the staff 2FA requirement. */
class OasisRole extends Command
{
    protected $signature = 'oasis:role {email} {role}';

    protected $description = 'Setzt die Oasis-Rolle eines Kontos (member, moderator, admin).';

    /** Reject unknown accounts and role values without changing data. */
    public function handle(ChangeRole $action): int
    {
        $role = Role::tryFrom((string) $this->argument('role'));
        $user = User::where('email', $this->argument('email'))->first();
        if (! $role || ! $user) {
            $this->error('Konto oder Rolle ungültig. Erlaubt sind member, moderator und admin.');

            return self::FAILURE;
        }
        $action->handle($user, $role);
        $this->info('Rolle gesetzt: '.$role->label().'.');

        return self::SUCCESS;
    }
}
