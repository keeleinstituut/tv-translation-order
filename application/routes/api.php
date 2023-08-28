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
Route::post('/subprojects/{id}/send-to-cat', [API\SubProjectController::class, 'sendToCat']);
Route::post('/subprojects/{id}/send-to-work', [API\SubProjectController::class, 'sendToWork']);

Route::get('/workflow/tasks', [API\WorkflowController::class, 'getTasks']);
Route::get('/workflow/tasks/{id}', [API\WorkflowController::class, 'getTask']);
Route::post('/workflow/tasks/{id}/complete', [API\WorkflowController::class, 'completeTask']);
Route::get('/workflow/history/tasks', [API\WorkflowController::class, 'getHistoryTasks']);

// ??
//Route::get('/cat/urls/translate/{project_id}', []);
//Route::get('/cat/urls/revise/{project_id}', []);
// ??
Route::get('/redirect', [API\RedirectController::class, '__invoke']);

Route::get('/playground', function (Request $request) {
    $response = [];
    $project = Project::find('99a6d516-fb33-47c2-9291-ad3e0c512cc4');
    $response['startProcessInstance'] = $project->workflow()->startProcessInstance();

    //    dd($response);
    $response['project'] = $project->refresh();

    return $response;
    //    return [
    //        'name' => fake()->name(),
    //    ];
});

Route::get('/playground2', function (Request $request) {
    $response = [];
    $projects = Project::getModel()
        ->with('subProjects')
        ->paginate();

    return $projects;
    $response['projects'] = $projects;

    return $response;
});
