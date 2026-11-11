<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Elimina ogni giorno le registrazioni scadute e mai confermate
Schedule::command('model:prune', ['--model' => [\App\Models\PendingRegistration::class]])->daily();
