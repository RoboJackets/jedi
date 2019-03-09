<?php

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
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;


Route::group(['middleware' => 'auth.cas.force'], function () {
    Route::get('/', function () {
        return view('welcome');
    });
    // Route::get('profile', function () {
    //     return view('users/userprofile', ['id' => auth()->user()->id]);
    // });

});
