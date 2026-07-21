<?php

use App\Console\Commands\DispatchScheduledMessages;
use Illuminate\Support\Facades\Schedule;

Schedule::command(DispatchScheduledMessages::class)->everyMinute();
