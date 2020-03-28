<?php

use Illuminate\Http\Request;
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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('users', 'UserController@index');
Route::get('users/{user}', 'UserController@show');
Route::post('login', 'AuthController@login');

Route::fallback(function () {
    return response()->json([
        'message' => 'Page Not Found. If the error persists, contact info@trytrig.com', ], 404);
});
