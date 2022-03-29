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

Route::get('/', function () {
    return view('welcome');
});

Route::post('/payments/pay', 'PaymentController@pay')->name('pay');
//The first one is that we can obtain an approval, or basically perform an approval of that a specific
//payment, or the second one is that the user cancel.
//And we need to provide that different routes in order to determine how to proceed depending on what the user did.
Route::get('/payments/approval', 'PaymentController@approval')->name('approval');
Route::get('/payments/cancelled', 'PaymentController@cancelled')->name('cancelled');

Route::prefix('subscribe')->name('subscribe.')->group(function () {
    Route::get('/', 'SubscriptionController@show')->name('show');

    Route::post('/', 'SubscriptionController@store')->name('store');

    Route::get('/approval', 'SubscriptionController@approval')->name('approval');

    Route::get('/cancelled', 'SubscriptionController@cancelled')->name('cancelled');
});

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');
