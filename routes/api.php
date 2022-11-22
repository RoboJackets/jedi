<?php

declare(strict_types=1);

use App\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

// @phan-file-suppress PhanStaticCallToNonStatic

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('v1/')->name('api.v1.')->middleware('auth.token')->group(static function (): void {
    Route::post('/apiary', [SyncController::class, 'sync']);
});

Route::githubWebhooks('/v1/github');
