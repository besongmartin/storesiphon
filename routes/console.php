<?php

use Illuminate\Support\Facades\Schedule;

// Missed or delayed payment webhooks are caught here (architecture §5).
Schedule::command('billing:reconcile')->everyFiveMinutes()->withoutOverlapping();
