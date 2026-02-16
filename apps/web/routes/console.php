<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('scans:purge-artifacts --days=30')->dailyAt('03:15');
