<?php

namespace App\Livewire\Admin;

use App\Models\SystemHeartbeat;
use App\Support\Operations\Backups;
use App\Support\Operations\HealthCheck;
use App\Support\Security\BotProtection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Systemzustand on /admin/system (route admin.system): the same checks as /gesundheit with a "Jetzt prüfen" button,
 * the latest backup, the last runs of every operation and today's refused bot attempts. Admins only; contains no personal data.
 */
class System extends Component
{
    public function mount(): void
    {
        Gate::authorize('administrate');
    }

    /** Re-render runs the checks again. */
    public function check(): void
    {
        Gate::authorize('administrate');
    }

    public function render(HealthCheck $health, Backups $backups): View
    {
        Gate::authorize('administrate');

        return view('livewire.admin.system', [
            'results' => $health->run(),
            'latestBackup' => rescue(fn () => $backups->latest(), null, false),
            'heartbeats' => SystemHeartbeat::orderBy('key')->get(),
            'tokenSet' => (string) config('oasis.operations.health_token') !== '',
            'backupKeySet' => (string) config('oasis.operations.backup_key') !== '',
            'botRefusals' => BotProtection::today(),
        ])->layout('layouts.staff', ['title' => 'Systemzustand', 'active' => 'system']);
    }
}
