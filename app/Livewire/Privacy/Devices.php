<?php

namespace App\Livewire\Privacy;

use App\Actions\Privacy\EndSessions;
use App\Support\Privacy\DeviceLabel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Geräte und Anmeldungen" on /app/privatsphaere/geraete (route app.privacy.devices): where the member is signed in
 * right now (sessions), signing out single devices or all others (EndSessions), and the devices known for login alerts.
 * Session ids are never sent to the browser; a row is addressed by a SHA-256 hash of its id.
 */
class Devices extends Component
{
    /** Confirmation of the last change, announced through role="status"; set by the server only. */
    #[Locked]
    public ?string $status = null;

    /** End one other session of the member, addressed by the hash shown in the list. */
    public function endSession(string $key, EndSessions $end): void
    {
        $session = collect($this->otherSessions())->firstWhere('key', $key);
        abort_if($session === null, 404);
        $end->one(auth()->user(), $session['id'], session()->getId());
        $this->status = 'Abgemeldet: '.$session['device'].'.';
    }

    public function endOtherSessions(EndSessions $end): void
    {
        $count = $end->others(auth()->user(), session()->getId());
        $this->status = $count === 1 ? 'Ein anderes Gerät wurde abgemeldet.' : $count.' andere Geräte wurden abgemeldet.';
    }

    /** Forget one known device; the next sign-in with it sends a login alert again. */
    public function forgetDevice(int $deviceId): void
    {
        $device = auth()->user()->knownDevices()->findOrFail($deviceId);
        $device->delete();
        $this->status = 'Vergessen: '.$device->label.'.';
    }

    public function render(): View
    {
        $member = auth()->user();
        $current = DB::table('sessions')->where('id', session()->getId())->first();

        return view('livewire.privacy.devices', [
            'database' => config('session.driver') === 'database',
            'current' => [
                'device' => DeviceLabel::from(request()->userAgent()),
                'ip' => self::maskIp(request()->ip()),
                'active' => $current !== null ? Carbon::createFromTimestamp($current->last_activity) : now(),
            ],
            'others' => $this->otherSessions(),
            'known' => $member->knownDevices()->orderByDesc('last_seen_at')->get(),
            'alerts' => $member->privacy()->login_alerts,
        ])->layout('layouts.app', ['title' => 'Geräte und Anmeldungen', 'active' => 'privatsphaere', 'wide' => true]);
    }

    /**
     * The member's other sessions, most recently active first.
     *
     * @return array<int, array{id: string, key: string, device: string, ip: string, active: Carbon}>
     */
    private function otherSessions(): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        return DB::table('sessions')->where('user_id', auth()->id())->where('id', '!=', session()->getId())
            ->orderByDesc('last_activity')->orderBy('id')->get()
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'key' => hash('sha256', (string) $row->id),
                'device' => DeviceLabel::from($row->user_agent),
                'ip' => self::maskIp($row->ip_address),
                'active' => Carbon::createFromTimestamp($row->last_activity),
            ])->values()->all();
    }

    /** Enough of the IP address to recognise a network, not enough to identify a connection. */
    private static function maskIp(?string $ip): string
    {
        if ($ip === null || $ip === '') {
            return 'unbekannt';
        }
        if (str_contains($ip, ':')) {
            return implode(':', array_slice(explode(':', $ip), 0, 3)).':…';
        }

        return implode('.', array_slice(explode('.', $ip), 0, 3)).'.…';
    }
}
