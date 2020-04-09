<?php

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

Route::post('login', 'AuthController@login');
Route::post('register', 'UserController@register');
Route::post('forgot-password', 'UserController@forgotPassword');
Route::post('reset-password', 'UserController@resetPassword');
Route::post('validate-reset-token', 'UserController@validateResetToken');
Route::post('google-sso', 'UserController@google');
Route::middleware('auth:api')->get('/me', 'UserController@me');

Route::fallback(function () {
    return response()->json([
        'message' => 'Page Not Found. If the error persists, contact info@trytrig.com', ], 404);
});
