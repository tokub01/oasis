<?php

/*
 * Operations without third-party services (docs/oasis-operations.md): encrypted backups with a real restore test,
 * the health check with its URL, alerts and admin page, and error mails without personal data.
 */

use App\Enums\Role;
use App\Livewire\Admin\System;
use App\Mail\Operations\ErrorAlertMail;
use App\Mail\Operations\HealthAlert;
use App\Models\SystemHeartbeat;
use App\Models\User;
use App\Support\Operations\BackupCipher;
use App\Support\Operations\Backups;
use App\Support\Operations\ErrorAlert;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\NetworkFixtures as N;

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
    Storage::fake('backups-test');
    config(['oasis.operations.backup_disk' => 'backups-test', 'oasis.operations.backup_key' => BackupCipher::generateKey(),
        'oasis.operations.verify_database' => 'testing_restore_'.(ParallelTesting::token() ?: '0')]);
});

function healthyHeartbeats(): void
{
    foreach (['scheduler', 'mollie-sync', 'backup', 'backup-verify'] as $key) {
        SystemHeartbeat::success($key);
    }
}

describe('backups', function () {
    it('creates an encrypted backup and restores it into its own database with identical row counts', function () {
        $member = N::member(['display_name' => 'Rückspiel Testperson']);
        N::post($member, ['body' => "Ein Tropfen mit ; Semikolon\nund Zeilenumbruch"]);

        $this->artisan('oasis:backup')->expectsOutputToContain('angelegt')->assertSuccessful();
        $name = app(Backups::class)->latest();
        $encrypted = Storage::disk('backups-test')->get('backups/'.$name.'.sql.gz.enc');
        expect($encrypted)->toStartWith('OASISBK1')->not->toContain('Rückspiel Testperson');

        $this->artisan('oasis:backup-verify')->expectsOutputToContain('wiederhergestellt und geprüft')->assertSuccessful();
        expect(SystemHeartbeat::lastSuccess('backup'))->not->toBeNull()
            ->and(SystemHeartbeat::lastSuccess('backup-verify'))->not->toBeNull();
    });

    it('refuses a changed backup and a wrong key, and records the failure', function () {
        $this->artisan('oasis:backup')->assertSuccessful();
        $name = app(Backups::class)->latest();
        $path = 'backups/'.$name.'.sql.gz.enc';
        $original = Storage::disk('backups-test')->get($path);

        Storage::disk('backups-test')->put($path, substr($original, 0, -5).'xxxxx');
        $this->artisan('oasis:backup-verify')->expectsOutputToContain('Prüfsumme')->assertFailed();
        expect(SystemHeartbeat::find('backup-verify')->last_failure_at)->not->toBeNull();

        Storage::disk('backups-test')->put($path, $original);
        config(['oasis.operations.backup_key' => BackupCipher::generateKey()]);
        $this->artisan('oasis:backup-verify')->expectsOutputToContain('falscher Schlüssel')->assertFailed();
    });

    it('decrypts a backup for a manual restore and never overwrites a file', function () {
        N::member(['display_name' => 'Manuelle Wiederherstellung']);
        $this->artisan('oasis:backup')->assertSuccessful();
        $name = app(Backups::class)->latest();
        $target = sys_get_temp_dir().'/oasis-restore-'.uniqid().'.sql';

        $this->artisan('oasis:backup-decrypt', ['name' => $name, 'path' => $target])->assertSuccessful();
        expect(file_get_contents($target))->toContain('Manuelle Wiederherstellung')->toContain('CREATE TABLE `users`');
        $this->artisan('oasis:backup-decrypt', ['name' => $name, 'path' => $target])->expectsOutputToContain('existiert schon')->assertFailed();
        unlink($target);
    });

    it('keeps backups only for the configured days', function () {
        Storage::disk('backups-test')->put('backups/oasis-'.now()->utc()->subDays(20)->format('Ymd-His').'.json', '{}');
        Storage::disk('backups-test')->put('backups/oasis-'.now()->utc()->subDays(20)->format('Ymd-His').'.sql.gz.enc', 'x');

        $this->artisan('oasis:backup')->assertSuccessful();

        expect(Storage::disk('backups-test')->files('backups'))->toHaveCount(2);
    });

    it('explains a missing key instead of writing an unencrypted backup', function () {
        config(['oasis.operations.backup_key' => null]);

        $this->artisan('oasis:backup')->expectsOutputToContain('oasis:backup-key')->assertFailed();
        expect(Storage::disk('backups-test')->allFiles())->toBe([]);
    });
});

describe('health check', function () {
    it('answers only with the token and reports problems with status 503', function () {
        config(['oasis.operations.health_token' => 'geheimes-token']);
        healthyHeartbeats();

        $this->get('/gesundheit')->assertNotFound();
        $this->get('/gesundheit?token=falsch')->assertNotFound();
        $this->getJson('/gesundheit', ['X-Health-Token' => 'geheimes-token'])->assertOk()->assertJsonPath('status', 'ok');

        $this->travel(10)->minutes();
        $this->getJson('/gesundheit?token=geheimes-token')->assertStatus(503)->assertJsonPath('status', 'problem')
            ->assertJsonPath('checks.scheduler.ok', false)->assertJsonPath('checks.backup.ok', true);

        config(['oasis.operations.health_token' => null]);
        $this->get('/gesundheit?token=')->assertNotFound();
    });

    it('mails the operator about problems at most every six hours per problem', function () {
        healthyHeartbeats();
        $this->artisan('oasis:health-check')->assertSuccessful();
        Mail::assertNothingSent();

        $this->travel(10)->minutes();
        $this->artisan('oasis:health-check')->assertFailed();
        Mail::assertSent(HealthAlert::class, fn (HealthAlert $mail) => $mail->hasTo(config('oasis.mail.operator'))
            && array_key_exists('scheduler', $mail->problems) && ! array_key_exists('backup', $mail->problems));

        // Same problem a little later: no second mail. (After 30 minutes the Mollie sync would be a new problem.)
        $this->travel(10)->minutes();
        $this->artisan('oasis:health-check')->assertFailed();
        Mail::assertSentCount(1);
        $this->travel(6)->hours();
        $this->artisan('oasis:health-check')->assertFailed();
        Mail::assertSentCount(2);
    });

    it('shows the same checks to admins only', function () {
        $this->actingAs(N::member())->get(route('admin.system'))->assertForbidden();
        $admin = User::factory()->create(['role' => Role::Admin, 'two_factor_secret' => encrypt('x'), 'two_factor_confirmed_at' => now()]);

        Livewire::actingAs($admin)->test(System::class)->assertSee('Systemzustand')->assertSee('Zeitplaner')
            ->assertSee('noch nie erfolgreich gelaufen')->call('check')->assertOk();
    });

    it('records the Mollie sync and the scheduler heartbeat', function () {
        $this->artisan('oasis:mollie-sync')->assertSuccessful();
        expect(SystemHeartbeat::lastSuccess('mollie-sync'))->not->toBeNull();
    });
});

describe('error mails', function () {
    it('mails where an unexpected error happened in production, without its message, once per hour', function () {
        config(['oasis.operations.error_mails' => true]);
        app()->detectEnvironment(fn () => 'production');
        $error = new RuntimeException('Geheime Daten: anna@example.tld');

        ErrorAlert::report($error);
        ErrorAlert::report($error);

        Mail::assertSent(ErrorAlertMail::class, 1);
        Mail::assertSent(ErrorAlertMail::class, fn (ErrorAlertMail $mail) => $mail->class === RuntimeException::class
            && str_contains($mail->location, 'OperationsTest.php') && ! str_contains($mail->render(), 'anna@example.tld'));
    });

    it('stays silent outside production', function () {
        ErrorAlert::report(new RuntimeException('lokal'));
        Mail::assertNothingSent();
    });
});
