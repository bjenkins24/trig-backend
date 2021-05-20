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
Route::post('logout', 'AuthController@logout');
Route::post('register', 'UserController@register');
Route::post('forgot-password', 'UserController@forgotPassword');
Route::post('reset-password', 'UserController@resetPassword');
Route::post('validate-reset-token', 'UserController@validateResetToken');
Route::post('google-sso', 'UserController@googleSso');
Route::get('queue', 'UserController@queue');

/*
 * Authenticated routes
 */
Route::middleware('auth:api')->get('/me', 'UserController@me');
Route::middleware('auth:api')->patch('/me', 'UserController@update');
Route::middleware('auth:api')->delete('/me', 'UserController@delete');
Route::middleware('auth:api')->post('/collection', 'CollectionController@create');
Route::middleware('auth:api')->get('/collection/{id}', 'CollectionController@get');
Route::middleware('auth:api')->get('/collections', 'CollectionController@getAll');
Route::middleware('auth:api')->patch('/collection/{id}', 'CollectionController@update');
Route::middleware('auth:api')->delete('/collection/{id}', 'CollectionController@delete');
Route::middleware(['auth:api', 'throttle:60,1'])->post('/card', 'CardController@create');
Route::middleware('auth:api')->get('/card/{id}', 'CardController@get');
Route::middleware('auth:api')->get('/cards/{queryConstraints?}', 'CardController@getAll');
Route::middleware('auth:api')->patch('/card/{id}', 'CardController@update');
Route::middleware('auth:api')->delete('/card/{id}', 'CardController@delete');
Route::middleware('auth:api')->post('/extension/check-authed', 'CardController@checkAuthed');

Route::fallback('WebController@fallback');
