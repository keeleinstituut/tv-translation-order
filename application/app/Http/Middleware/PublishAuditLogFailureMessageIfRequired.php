<?php

namespace App\Http\Middleware;

use App\Http\Controllers\API\AssignmentController;
use App\Http\Controllers\API\CatToolController;
use App\Http\Controllers\API\CatToolTmKeyController;
use App\Http\Controllers\API\InstitutionDiscountController;
use App\Http\Controllers\API\MediaController;
use App\Http\Controllers\API\PriceController;
use App\Http\Controllers\API\ProjectController;
use App\Http\Controllers\API\VendorController;
use App\Http\Controllers\API\VolumeController;
use App\Models\Assignment;
use App\Models\InstitutionDiscount;
use App\Models\Media;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\Vendor;
use AuditLogClient\Enums\AuditLogEventFailureType;
use AuditLogClient\Enums\AuditLogEventObjectType;
use AuditLogClient\Enums\AuditLogEventType;
use AuditLogClient\Services\AuditLogMessageBuilder;
use AuditLogClient\Services\AuditLogPublisher;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PublishAuditLogFailureMessageIfRequired
{
    public function __construct(protected AuditLogPublisher $publisher)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(\Illuminate\Http\Request): Response  $next
     *
     * @throws ValidationException
     * @throws Throwable
     */
    public function handle(\Illuminate\Http\Request $request, Closure $next): Response
    {
        $response = $next($request);

        $eventTypeAndParameters = static::resolveEventTypeAndParameters();
        $failureType = match ($response->getStatusCode()) {
            Response::HTTP_UNPROCESSABLE_ENTITY,
            Response::HTTP_BAD_REQUEST => AuditLogEventFailureType::UNPROCESSABLE_ENTITY,
            Response::HTTP_FORBIDDEN => AuditLogEventFailureType::FORBIDDEN,
            default => null
        };

        if (filled($failureType) && filled($eventTypeAndParameters)) {
            [$eventType, $eventParameters] = $eventTypeAndParameters;
            $auditLogMessage = AuditLogMessageBuilder::makeUsingJWT($failureType)->toMessageEvent($eventType, $eventParameters);
            $this->publisher->publish($auditLogMessage);
        }

        return $response;
    }

    /**
     * @return null|array{ AuditLogEventType, ?array }
     */
    private static function resolveEventTypeAndParameters(): ?array
    {
        [$controller, $action] = explode('@', Route::currentRouteAction());

        return match ($controller) {
            MediaController::class,
            ProjectController::class => static::resolveProjectEventTypeAndParameters($controller, $action),
            CatToolController::class,
            CatToolTmKeyController::class => static::resolveSubprojectEventTypeAndParameters($controller, $action),
            AssignmentController::class,
            VolumeController::class => static::resolveAssignmentEventTypeAndParameters($controller, $action),
            PriceController::class,
            VendorController::class => static::resolveVendorEventTypeAndParameters($controller, $action),
            InstitutionDiscountController::class => static::resolveInstitutionEventTypeAndParameters($controller, $action),
            default => null,
        };
    }

    /**
     * @return null|array{ AuditLogEventType, ?array }
     */
    public static function resolveProjectEventTypeAndParameters(string $controller, string $action): ?array
    {
        return match ([$controller, $action]) {
            [MediaController::class, 'bulkStore'],
            [MediaController::class, 'bulkDestroy'], => [AuditLogEventType::ModifyObject, [
                'object_type' => AuditLogEventObjectType::Project,
                'input' => Request::input(),
            ]],
            [MediaController::class, 'download'], => [AuditLogEventType::DownloadProjectFile, [
                'media_id' => Request::input('id'),
                'file_name' => Media::find(Request::input('id'))?->file_name,
                'input' => Request::input(),
            ]],
            [ProjectController::class, 'store'], => [AuditLogEventType::CreateObject, [
                'object_type' => AuditLogEventObjectType::Project->value,
                'input' => Request::input(),
            ]],
            [ProjectController::class, 'update'], => [AuditLogEventType::ModifyObject, [
                'object_type' => AuditLogEventObjectType::Project->value,
                'object_identity_subset' => Project::find(Route::current()->parameter('id'))?->getIdentitySubset(),
                'input' => Request::input(),
            ]],
            default => null
        };
    }

    /**
     * @return null|array{ AuditLogEventType, ?array }
     */
    public static function resolveSubprojectEventTypeAndParameters(string $controller, string $action): ?array
    {
        return match ([$controller, $action]) {
            [CatToolController::class, 'setup'],
            [CatToolController::class, 'split'],
            [CatToolController::class, 'merge'],
            [CatToolTmKeyController::class, 'sync'] => [AuditLogEventType::ModifyObject, [
                'object_type' => AuditLogEventObjectType::Subproject->value,
                'object_identity_subset' => SubProject::find(Request::input('sub_project_id'))?->getIdentitySubset(),
                'input' => Request::input(),
            ]],
            [CatToolController::class, 'toggleMTEngine'] => [AuditLogEventType::ModifyObject, [
                'object_type' => AuditLogEventObjectType::Subproject->value,
                'object_identity_subset' => SubProject::find(Route::current()->parameter('sub_project_id'))?->getIdentitySubset(),
                'input' => Request::input(),
            ]],
            [CatToolController::class, 'downloadXLIFFs'] => [AuditLogEventType::DownloadSubprojectXliffs, [
                'object_type' => AuditLogEventObjectType::Subproject->value,
                'input' => Request::input(),
            ]],
            [CatToolController::class, 'downloadTranslations'] => [AuditLogEventType::DownloadSubprojectTranslations, [
                'object_type' => AuditLogEventObjectType::Subproject->value,
                'input' => Request::input(),
            ]],
            [CatToolTmKeyController::class, 'toggleWritable'], => [AuditLogEventType::ModifyObject, [
                'object_type' => AuditLogEventObjectType::Subproject->value,
                'object_identity_subset' => SubProject::whereRelation('catToolTmKeys', 'id', Route::current()->parameter('id'))
                    ->get()
                    ->first()
                    ?->getIdentitySubset(),
                'input' => Request::input(),
            ]],

            default => null
        };
    }

    /**
     * @return null|array{ AuditLogEventType, ?array }
     */
    public static function resolveAssignmentEventTypeAndParameters(string $controller, string $action): ?array
    {
        return match ([$controller, $action]) {
            [AssignmentController::class, 'linkToCatToolJobs'], => [AuditLogEventType::ModifyObject, [
                'object_type' => AuditLogEventObjectType::Assignment->value,
                'object_identity_subsets' => Assignment::whereIn('id', Request::input('linking.*.assignment_id'))
                    ->get()
                    ->map(fn (Assignment $assignment) => $assignment->getIdentitySubset())
                    ->all(),
                'input' => Request::input(),
            ]],
            [AssignmentController::class, 'store'], => [AuditLogEventType::CreateObject, [
                'object_type' => AuditLogEventObjectType::Assignment->value,
                'input' => Request::input(),
            ]],
            [AssignmentController::class, 'update'],
            [AssignmentController::class, 'updateAssigneeComment'],
            [AssignmentController::class, 'addCandidates'],
            [AssignmentController::class, 'deleteCandidate'] => [AuditLogEventType::ModifyObject, [
                'object_type' => AuditLogEventObjectType::Assignment->value,
                'object_identity_subset' => Assignment::find(Route::current()->parameter('id'))?->getIdentitySubset(),
                'input' => Request::input(),
            ]],
            [AssignmentController::class, 'destroy'], => [AuditLogEventType::RemoveObject, [
                'object_type' => AuditLogEventObjectType::Assignment->value,
                'object_identity_subset' => Assignment::find(Route::current()->parameter('id'))?->getIdentitySubset(),
            ]],

            [VolumeController::class, 'store'],
            [VolumeController::class, 'storeCatToolVolume'] => [AuditLogEventType::ModifyObject, [
                'object_type' => AuditLogEventObjectType::Assignment->value,
                'object_identity_subset' => Assignment::find(Request::input('assignment_id'))?->getIdentitySubset(),
                'input' => Request::input(),
            ]],
            [VolumeController::class, 'update'],
            [VolumeController::class, 'updateCatToolVolume'],
            [VolumeController::class, 'destroy'] => [AuditLogEventType::ModifyObject, [
                'object_type' => AuditLogEventObjectType::Assignment->value,
                'object_identity_subset' => Assignment::whereRelation('volumes', 'id', Route::current()->parameter('id'))->get()->first()?->getIdentitySubset(),
                'input' => Request::input(),
            ]],
            default => null
        };
    }

    /**
     * @return null|array{ AuditLogEventType, ?array }
     */
    public static function resolveVendorEventTypeAndParameters(string $controller, string $action): ?array
    {
        return match ([$controller, $action]) {
            [PriceController::class, 'store'], => [AuditLogEventType::ModifyObject, [
                'object_type' => AuditLogEventObjectType::Vendor->value,
                'object_identity_subset' => Vendor::find(Request::input('vendor_id'))?->getIdentitySubset(),
                'input' => Request::input(),
            ]],
            [PriceController::class, 'bulkStore'],
            [PriceController::class, 'bulkUpdate'],
            [PriceController::class, 'bulkDestroy'] => [AuditLogEventType::ModifyObject, [
                'object_type' => AuditLogEventObjectType::Vendor->value,
                'input' => Request::input(),
            ]],
            [VendorController::class, 'update'], => [AuditLogEventType::ModifyObject, [
                'object_type' => AuditLogEventObjectType::Vendor->value,
                'object_identity_subset' => Vendor::find(Route::current()->parameter('id'))?->getIdentitySubset(),
                'input' => Request::input(),
            ]],
            [VendorController::class, 'bulkCreate'], => [AuditLogEventType::CreateObject, [
                'object_type' => AuditLogEventObjectType::Vendor->value,
                'input' => Request::input(),
            ]],
            [VendorController::class, 'bulkDestroy'], => [AuditLogEventType::RemoveObject, [
                'object_type' => AuditLogEventObjectType::Vendor->value,
                'input' => Request::input(),
            ]],
            default => null
        };
    }

    /**
     * @return null|array{ AuditLogEventType, ?array }
     */
    public static function resolveInstitutionEventTypeAndParameters(string $controller, string $action): ?array
    {
        return match ([$controller, $action]) {
            [InstitutionDiscountController::class, 'store'] => InstitutionDiscount::whereRelation('institution', 'id', Auth::user()?->institutionId)->exists()
                ? [AuditLogEventType::ModifyObject, [
                    'object_type' => AuditLogEventObjectType::InstitutionDiscount->value,
                    'object_identity_subset' => InstitutionDiscount::whereRelation('institution', 'id', Auth::user()?->institutionId)->get()->first()->getIdentitySubset(),
                    'input' => Request::input(),
                ]]
                : [AuditLogEventType::CreateObject, [
                    'object_type' => AuditLogEventObjectType::InstitutionDiscount->value,
                    'input' => Request::input(),
                ]],

            default => null
        };
    }
}
