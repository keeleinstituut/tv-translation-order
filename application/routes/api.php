<?php

use App\Http\Controllers\API;
use App\Http\Controllers\TagController;
use App\Models\Project;
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

Route::get('/institution-discounts', [API\InstitutionDiscountController::class, 'show']);
Route::put('/institution-discounts', [API\InstitutionDiscountController::class, 'store']);

Route::get('/skills', [API\SkillController::class, 'index']);

Route::get('/vendors', [API\VendorController::class, 'index']);
Route::get('/vendors/{id}', [API\VendorController::class, 'show']);
Route::put('/vendors/{id}', [API\VendorController::class, 'update']);
Route::post('/vendors/bulk', [API\VendorController::class, 'bulkCreate']);
Route::delete('/vendors/bulk', [API\VendorController::class, 'bulkDestroy']);

Route::get('/prices', [API\PriceController::class, 'index']);
Route::post('/prices', [API\PriceController::class, 'store']);
Route::post('/prices/bulk', [API\PriceController::class, 'bulkStore']);
Route::put('/prices/bulk', [API\PriceController::class, 'bulkUpdate']);
Route::delete('/prices/bulk', [API\PriceController::class, 'bulkDestroy']);

Route::get('/projects', [API\ProjectController::class, 'index']);
Route::post('/projects', [API\ProjectController::class, 'store']);
Route::get('/projects/{id}', [API\ProjectController::class, 'show']);

Route::get('/assignments', [API\AssignmentController::class, 'index']);
Route::put('/assignments/{id}', [API\AssignmentController::class, 'update']);

Route::get('/subprojects', [API\SubProjectController::class, 'index']);
Route::get('/subprojects/{id}', [API\SubProjectController::class, 'show']);

Route::prefix('/cat-tool')
    ->controller(API\CatToolController::class)->group(function (): void {
        Route::post('/setup', 'setup');
        Route::post('/split', 'split');
        Route::post('/merge', 'merge');
        Route::get('/jobs/{sub_project_id}', 'jobsIndex');
        Route::get('/volume-analysis/{sub_project_id}', 'volumeAnalysis');
        Route::get('/download-xliff/{sub_project_id}', 'downloadXLIFFs');
        Route::get('/download-translated/{sub_project_id}', 'downloadTranslations');
        Route::get('/download-volume-analysis/{sub_project_id}', 'downloadVolumeAnalysisReport');
    });

Route::prefix('/volumes')
    ->controller(API\VolumeController::class)->group(function (): void {
        Route::post('/', 'store');
        Route::post('/cat-tool', 'storeCatToolVolume');
        Route::put('/{id}', 'update');
        Route::put('/cat-tool/{id}', 'updateCatToolVolume');
        Route::delete('/{id}', 'destroy');
    });

Route::prefix('/assignments')
    ->controller(API\AssignmentController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');
        Route::post('/cat-tool', 'storeCatToolVolume');
        Route::put('/{id}', 'update');
        Route::put('/cat-tool/{id}', 'updateCatToolVolume');
        Route::delete('/{id}', 'destroy');
    });

Route::get('/workflow/tasks', [API\WorkflowController::class, 'getTasks']);
Route::get('/workflow/tasks/{id}', [API\WorkflowController::class, 'getTask']);
Route::post('/workflow/tasks/{id}/complete', [API\WorkflowController::class, 'completeTask']);
Route::get('/workflow/history/tasks', [API\WorkflowController::class, 'getHistoryTasks']);

// ??
//Route::get('/cat/urls/translate/{project_id}', []);
//Route::get('/cat/urls/revise/{project_id}', []);
// ??
Route::get('/redirect', [API\RedirectController::class, '__invoke']);
