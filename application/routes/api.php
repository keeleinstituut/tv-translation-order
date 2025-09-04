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
        Route::get('/', 'index')->name('translation-order.tags.index');
        Route::post('/bulk-create', 'store')->name('translation-order.tags.store');
        Route::post('/bulk-update', 'update')->name('translation-order.tags.update');
    });

// Cached values endpoints to provide input data for other endpoints
// within this service, e.g. to create vendors.
// Only GET endpoints are allowed for read-only access.
Route::get('/classifier-values', [API\ClassifierValueController::class, 'index'])->name('translation-order.classifier_values.index');
Route::get('/institution-users', [API\InstitutionUserController::class, 'index'])->name('translation-order.institution_users.index');

Route::get('/institution-discounts', [API\InstitutionDiscountController::class, 'show'])->name('translation-order.institution_discounts.index');
Route::put('/institution-discounts', [API\InstitutionDiscountController::class, 'store'])->name('translation-order.institution_discounts.store');

Route::get('/skills', [API\SkillController::class, 'index'])->name('translation-order.skills.index');

Route::get('/vendors', [API\VendorController::class, 'index'])->name('translation-order.vendors.index');
Route::get('/vendors/{id}', [API\VendorController::class, 'show'])->name('translation-order.vendors.show');
Route::put('/vendors/{id}', [API\VendorController::class, 'update'])->name('translation-order.vendors.update');
Route::post('/vendors/bulk', [API\VendorController::class, 'bulkCreate'])->name('translation-order.vendors.bulkCreate');
Route::delete('/vendors/bulk', [API\VendorController::class, 'bulkDestroy'])->name('translation-order.vendors.bulkDestroy');

Route::get('/prices', [API\PriceController::class, 'index'])->name('translation-order.prices.index');
Route::post('/prices', [API\PriceController::class, 'store'])->name('translation-order.prices.store');
Route::post('/prices/bulk', [API\PriceController::class, 'bulkStore'])->name('translation-order.prices.bulkStore');
Route::put('/prices/bulk', [API\PriceController::class, 'bulkUpdate'])->name('translation-order.prices.bulkUpdate');
Route::delete('/prices/bulk', [API\PriceController::class, 'bulkDestroy'])->name('translation-order.prices.bulkDestroy');

Route::prefix('/projects')
    ->controller(API\ProjectController::class)
    ->whereUuid('id')->group(function (): void {
        Route::get('/', [API\ProjectController::class, 'index'])->name('translation-order.projects.index');
        Route::post('/', [API\ProjectController::class, 'store'])->name('translation-order.projects.store');
        Route::get('/{id}', [API\ProjectController::class, 'show'])->name('translation-order.projects.show');
        Route::put('/{id}', [API\ProjectController::class, 'update'])->name('translation-order.projects.update');
        Route::post('/{id}/cancel', [API\ProjectController::class, 'cancel'])->name('translation-order.projects.cancel');
        Route::get('/export-csv', [API\ProjectController::class, 'exportCsv'])->name('translation-order.projects.exportCsv');
    });

Route::prefix('/subprojects')
    ->controller(API\SubProjectController::class)
    ->whereUuid('id')->group(function (): void {
        Route::get('/', 'index')->name('translation-order.subprojects.index');
        Route::get('/{id}', 'show')->name('translation-order.subprojects.show');
        Route::post('/{id}/start-workflow', 'startWorkflow')->name('translation-order.subprojects.startWorkflow');
        Route::put('/{id}', 'update')->name('translation-order.subprojects.update');
        Route::post('/{id}/set-project-final-files', 'setProjectFinalFiles')->name('translation-order.subprojects.setProjectFinalFiles');
        Route::get('/languages', 'getLanguageCombinations')->name('translation-order.subprojects.getLanguageCombinations');
    });

Route::prefix('/cat-tool')
    ->controller(API\CatToolController::class)
    ->whereUuid('sub_project_id')->group(function (): void {
        Route::post('/setup', 'setup')->name('translation-order.cat.setup');
        Route::post('/split', 'split')->name('translation-order.cat.split');
        Route::post('/merge', 'merge')->name('translation-order.cat.merge');
        Route::get('/jobs/{sub_project_id}', 'jobsIndex')->name('translation-order.cat.jobsIndex');
        Route::put('/toggle-mt-engine/{sub_project_id}', 'toggleMTEngine')->name('translation-order.cat.toggleMTEngine');
        Route::get('/volume-analysis/{sub_project_id}', 'volumeAnalysis')->name('translation-order.cat.volumeAnalysis');
        Route::get('/download-xliff/{sub_project_id}', 'downloadXLIFFs')->name('translation-order.cat.downloadXLIFFs');
        Route::get('/download-translated/{sub_project_id}', 'downloadTranslations')->name('translation-order.cat.downloadTranslations');
        Route::get('/download-volume-analysis/{sub_project_id}', 'downloadVolumeAnalysisReport')->name('translation-order.cat.downloadVolumeAnalysisReport');
    });

Route::prefix('/tm-keys')
    ->controller(API\CatToolTmKeyController::class)
    ->whereUuid('sub_project_id')->group(function (): void {
        Route::get('/{sub_project_id}', 'index')->name('translation-order.cat_tool_tm_keys.index');
        Route::get('/subprojects/{key}', 'subProjectsIndex')->name('translation-order.cat_tool_tm_keys.subProjectsIndex');
        Route::post('/sync', 'sync')->name('translation-order.cat_tool_tm_keys.sync');
        Route::put('/toggle-writable/{id}', 'toggleWritable')->name('translation-order.cat_tool_tm_keys.toggleWritable');
        Route::post('/{sub_project_id}', 'create')->name('translation-order.cat_tool_tm_keys.create');
    });

Route::prefix('/volumes')
    ->controller(API\VolumeController::class)
    ->whereUuid('id')->group(function (): void {
        Route::post('/', 'store')->name('translation-order.volumes.store');
        Route::post('/cat-tool', 'storeCatToolVolume')->name('translation-order.volumes.storeCatToolVolume');
        Route::put('/{id}', 'update')->name('translation-order.volumes.update');
        Route::put('/cat-tool/{id}', 'updateCatToolVolume')->name('translation-order.volumes.updateCatToolVolume');
        Route::delete('/{id}', 'destroy')->name('translation-order.volumes.destroy');
    });

Route::prefix('/assignments')
    ->controller(API\AssignmentController::class)
    ->whereUuid('id')->group(function (): void {
        Route::get('/{sub_project_id}', 'index')->name('translation-order.assignments.index');
        Route::post('/link-cat-tool-jobs', 'linkToCatToolJobs')->name('translation-order.assignments.linkToCatToolJobs');
        Route::post('/', 'store')->name('translation-order.assignments.store');
        Route::put('/{id}', 'update')->name('translation-order.assignments.update');
        Route::delete('/{id}', 'destroy')->name('translation-order.assignments.destroy');
        Route::put('/{id}/assignee-comment', 'updateAssigneeComment')->name('translation-order.assignments.updateAssigneeComment');
        Route::post('/{id}/candidates/bulk', 'addCandidates')->name('translation-order.assignments.addCandidates');
        Route::delete('/{id}/candidates/bulk', 'deleteCandidate')->name('translation-order.assignments.deleteCandidate');
        Route::post('/{id}/mark-as-completed', 'markAsCompleted')->name('translation-order.assignments.markAsCompleted');
    });

Route::prefix('/workflow')
    ->controller(API\WorkflowController::class)
    ->whereUuid('id')->group(function (): void {
        Route::get('/tasks', [API\WorkflowController::class, 'getTasks'])->name('translation-order.workflow.getTasks');
        Route::get('/tasks2', [API\WorkflowController::class, 'getTasks2'])->name('translation-order.workflow.getTasks2');
        Route::get('/tasks/{id}', [API\WorkflowController::class, 'getTask'])->name('translation-order.workflow.getTask');
        Route::post('/tasks/{id}/complete', [API\WorkflowController::class, 'completeTask'])->name('translation-order.workflow.completeTask');
        Route::post('/tasks/{id}/accept', [API\WorkflowController::class, 'acceptTask'])->name('translation-order.workflow.acceptTask');
        Route::get('/history/tasks', [API\WorkflowController::class, 'getHistoryTasks'])->name('translation-order.workflow.getHistoryTasks');
        Route::get('/history/tasks2', [API\WorkflowController::class, 'getHistoryTasks2'])->name('translation-order.workflow.getHistoryTasks2');
        Route::get('/history/tasks/{id}', [API\WorkflowController::class, 'getHistoryTask'])->name('translation-order.workflow.getHistoryTask');
    });


Route::prefix('/media')
    ->controller(API\MediaController::class)
    ->group(function () {
        Route::post('/bulk', 'bulkStore')->name('translation-order.media.bulkStore');
        Route::delete('/bulk', 'bulkDestroy')->name('translation-order.media.bulkDestroy');
        Route::get('/download', 'download')->name('translation-order.media.download');
        Route::put('/{id}', 'update')->name('translation-order.media.update');
    });

// ??
//Route::get('/cat/urls/translate/{project_id}', []);
//Route::get('/cat/urls/revise/{project_id}', []);
// ??
Route::get('/redirect', [API\RedirectController::class, '__invoke']);
