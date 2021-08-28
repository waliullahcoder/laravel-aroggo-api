<?php

use App\Http\Controllers\AuthController;
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

Route::post('/v1/auth/sms/send/', [AuthController::class, 'SMSSend']);
Route::post('/v1/auth/sms/verify/', [AuthController::class, 'SMSVerify']);
Route::post('/v1/auth/logout/', [AuthController::class, 'logout']);
Route::post('/admin/v1/auth/sms/send/', [AuthController::class, 'adminSMSSend']);
Route::post('/admin/v1/auth/sms/verify/', [AuthController::class, 'adminSMSVerify']);
