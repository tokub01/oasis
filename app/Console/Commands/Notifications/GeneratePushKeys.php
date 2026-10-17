<?php

namespace App\Console\Commands\Notifications;

use App\Support\Notifications\WebPush;
use Illuminate\Console\Command;

/**
 * Create a VAPID key pair for push hints (docs/oasis-pwa.md). Prints the two .env lines; it never writes the .env
 * itself. Keep the pair: a new pair makes every existing browser subscription invalid.
 */
class GeneratePushKeys extends Command
{
    protected $signature = 'oasis:push-keys';

    protected $description = 'Create a VAPID key pair for push hints and print the .env lines';

    public function handle(): int
    {
        $keys = WebPush::generateKeys();
        $this->line('OASIS_VAPID_PUBLIC='.$keys['public']);
        $this->line('OASIS_VAPID_PRIVATE='.$keys['private']);

        return self::SUCCESS;
    }
}
