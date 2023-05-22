<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/vendors', [API\VendorController::class, 'index']);
Route::post('/vendors/bulk', [API\VendorController::class, 'bulkCreate']);
Route::delete('/vendors/bulk', [API\VendorController::class, 'bulkDestroy']);
