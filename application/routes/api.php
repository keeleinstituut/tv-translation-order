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

// Cached values endpoints to provide input data for other endpoints
// within this service, e.g. to create vendors.
// Only GET endpoints are allowed for read-only access.
Route::get('/classifier-values', [API\ClassifierValueController::class, 'index']);
Route::get('/institution-users', [API\InstitutionUserController::class, 'index']);

Route::get('/vendors', [API\VendorController::class, 'index']);
Route::post('/vendors/bulk', [API\VendorController::class, 'bulkCreate']);
Route::delete('/vendors/bulk', [API\VendorController::class, 'bulkDestroy']);

Route::get('/prices', [API\PriceController::class, 'index']);
Route::post('/prices', [API\PriceController::class, 'store']);
Route::post('/prices/bulk', [API\PriceController::class, 'bulkStore']);
Route::put('/prices/bulk', [API\PriceController::class, 'bulkUpdate']);
Route::delete('/prices/bulk', [API\PriceController::class, 'bulkDestroy']);
