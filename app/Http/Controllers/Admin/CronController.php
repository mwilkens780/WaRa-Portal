<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;

class CronController extends Controller
{
    public function run(string $token)
    {
        if (!hash_equals(config('cron.scheduler_token', ''), $token)) {
            abort(403);
        }

        Artisan::call('schedule:run');

        return response('OK ' . now()->toDateTimeString(), 200)
            ->header('Content-Type', 'text/plain');
    }
}
