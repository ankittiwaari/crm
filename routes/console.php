<?php

use Illuminate\Support\Facades\Schedule;

/*
| Server cron: * * * * * cd /var/www/html/crm && php artisan schedule:run >> /dev/null 2>&1
*/

Schedule::command('crm:daily-checks')->dailyAt('07:00')->withoutOverlapping()->onOneServer();
Schedule::command('crm:daily-digest')->dailyAt('08:00')->withoutOverlapping()->onOneServer();

Schedule::command('backup:clean')->dailyAt('01:30')->onOneServer();
Schedule::command('backup:run')->dailyAt('02:00')->onOneServer();
Schedule::command('backup:monitor')->dailyAt('03:00')->onOneServer();
