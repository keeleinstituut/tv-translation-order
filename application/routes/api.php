<?php

use App\Http\Controllers\API;
use App\Http\Controllers\TagController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::prefix('/tags')
    ->controller(TagController::class)
    ->group(function (): void {
        Route::get('/', 'index');
        Route::post('/bulk-create', 'store');
        Route::post('/bulk-update', 'update');
    });

// Cached values endpoints to provide input data for other endpoints
// within this service, e.g. to create vendors.
// Only GET endpoints are allowed for read-only access.
Route::get('/classifier-values', [API\ClassifierValueController::class, 'index']);
Route::get('/institution-users', [API\InstitutionUserController::class, 'index']);

Route::get('/skills', [API\SkillController::class, 'index']);

Route::get('/vendors', [API\VendorController::class, 'index']);
Route::put('/vendors/{id}', [API\VendorController::class, 'update']);
Route::post('/vendors/bulk', [API\VendorController::class, 'bulkCreate']);
Route::delete('/vendors/bulk', [API\VendorController::class, 'bulkDestroy']);

Route::get('/prices', [API\PriceController::class, 'index']);
Route::post('/prices', [API\PriceController::class, 'store']);
Route::post('/prices/bulk', [API\PriceController::class, 'bulkStore']);
Route::put('/prices/bulk', [API\PriceController::class, 'bulkUpdate']);
Route::delete('/prices/bulk', [API\PriceController::class, 'bulkDestroy']);
