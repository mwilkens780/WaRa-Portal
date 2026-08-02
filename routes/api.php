<?php

use App\Http\Controllers\Api\WebClubImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(\App\Http\Middleware\VerifyWebClubToken::class)
    ->post('/webclub-import', [WebClubImportController::class, 'store']);
