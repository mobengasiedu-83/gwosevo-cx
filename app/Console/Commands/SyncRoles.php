<?php

namespace App\Console\Commands;

use App\Services\Roles;
use Illuminate\Console\Command;

class SyncRoles extends Command
{
    protected $signature = 'contact-center:sync-roles';

    protected $description = 'Create built-in roles and synchronize administrative permissions';

    public function handle(): int
    {
        Roles::seed();
        $this->info('Built-in roles and permissions synchronized.');

        return self::SUCCESS;
    }
}
