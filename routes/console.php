<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Missed or delayed payment webhooks are caught here (architecture §5).
Schedule::command('billing:reconcile')->everyFiveMinutes()->withoutOverlapping();

// Data retention (architecture §6).
Schedule::command('storesiphon:purge')->dailyAt('03:15')->withoutOverlapping();
Schedule::command('queue:prune-failed', ['--hours' => 720])->daily();
Schedule::command('auth:clear-resets')->daily();

// Heartbeat read by storesiphon:health (proves cron + scheduler are running).
Schedule::call(fn () => Cache::put('heartbeat:scheduler', now()->toIso8601String(), 3600))->everyMinute()->name('scheduler-heartbeat');
