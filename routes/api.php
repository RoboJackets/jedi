<?php

declare(strict_types=1);

use App\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

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

Route::group(['prefix' => 'v1/', 'as' => 'api.v1.', 'middleware' => ['auth.token']], static function (): void {
    Route::post('/apiary', [SyncController::class, 'sync']);
});

Route::webhooks('/v1/github', 'github');
