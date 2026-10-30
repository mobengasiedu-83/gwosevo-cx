<?php

namespace App\Console\Commands;

use App\Jobs\InfrastructureProbe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class CheckInfrastructure extends Command
{
    protected $signature = 'contact-center:check-infrastructure';

    protected $description = 'Check database and isolated Redis cache, session and queue round trips';

    public function handle(): int
    {
        DB::connection()->getPdo();
        $this->info('Database connection OK.');
        $key = 'foundation-check:'.Str::uuid();
        Cache::store('redis')->put($key, 'ok', 30);
        if (Cache::store('redis')->pull($key) !== 'ok') {
            throw new \RuntimeException('Redis cache failed.');
        }
        $handler = app('session')->driver('redis')->getHandler();
        $handler->write($key, 'session-ok');
        if ($handler->read($key) !== 'session-ok') {
            throw new \RuntimeException('Redis session failed.');
        }
        $handler->destroy($key);
        Queue::connection('redis')->push(new InfrastructureProbe, '', $key);
        $job = Queue::connection('redis')->pop($key);
        if (! $job) {
            throw new \RuntimeException('Redis queue failed.');
        }
        $job->fire();
        $job->delete();
        Redis::connection()->del('queues:'.$key.':notify');
        $this->info('Redis cache, session and queue round trips OK.');

        return self::SUCCESS;
    }
}
