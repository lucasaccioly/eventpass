<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('demo:prune')->hourly();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
