<?php declare(strict_types = 1);

use App\Http\Controllers\SelfServiceController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['prefix' => 'self-service/', 'middleware' => ['auth.cas']], static function (): void {
    Route::post('/github', [SelfServiceController::class, 'github']);
    Route::post('/sums', [SelfServiceController::class, 'sums']);
});
