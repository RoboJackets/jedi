<?php declare(strict_types = 1);

use Illuminate\Http\Request;

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
    Route::post('/sync', 'SyncController@sync');
});
