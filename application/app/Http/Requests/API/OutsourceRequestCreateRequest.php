<?php

namespace App\Http\Requests\API;

use App\Enums\CandidateStatus;
use App\Enums\OutsourceRequestMode;
use App\Enums\OutsourceRequestStatus;
use App\Models\Assignment;
use App\Models\Candidate;
use App\Models\OutsourceRequest;
use App\Models\InstitutionPartner;
use App\Rules\ProjectFileValidator;
use App\Rules\ScannedRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['assignment_id', 'mode', 'reaction_time_minutes', 'offers'],
        properties: [
            new OA\Property(property: 'assignment_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'mode', type: 'string', enum: OutsourceRequestMode::class),
            new OA\Property(property: 'reaction_time_minutes', type: 'integer', maximum: 525600, minimum: 1),
            new OA\Property(
                property: 'offers',
                type: 'array',
                items: new OA\Items(
                    required: ['institution_id'],
                    properties: [new OA\Property(property: 'institution_id', type: 'string', format: 'uuid')],
                    type: 'object',
                )
            ),
            new OA\Property(property: 'special_instructions', type: 'string', nullable: true),
            new OA\Property(property: 'include_source_files', type: 'boolean', nullable: true),
            new OA\Property(property: 'include_price', type: 'boolean', nullable: true),
            new OA\Property(property: 'fixed_price', type: 'number', format: 'double', nullable: true),
            new OA\Property(property: 'request_files', type: 'array', items: new OA\Items(type: 'string', format: 'binary'), nullable: true),
        ]
    )
)]
class OutsourceRequestCreateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'assignment_id' => 'required|uuid|exists:assignments,id',
            'mode' => 'required|string|in:CASCADE,PARALLEL',
            'reaction_time_minutes' => 'required|integer|min:1|max:525600',
            'offers' => 'required|array|min:1',
            'offers.*.institution_id' => 'required|uuid',
            'special_instructions' => 'nullable|string',
            'include_source_files' => 'nullable|boolean',
            'include_price' => 'nullable|boolean',
            'fixed_price' => 'nullable|numeric|min:0',
            'request_files' => 'nullable|array',
            'request_files.*' => [ProjectFileValidator::createRule(), ScannedRule::createRule()],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $assignmentId = $this->input('assignment_id');
                $assignment = Assignment::find($assignmentId);

                if (!$assignment) {
                    return;
                }

                if ($assignment->assigned_vendor_id !== null) {
                    $validator->errors()->add('assignment_id', 'Assignment already has a vendor assigned.');
                }

                if (Candidate::query()
                    ->where('assignment_id', $assignmentId)
                    ->whereIn('status', [CandidateStatus::New, CandidateStatus::SubmittedToVendor])
                    ->exists()
                ) {
                    $validator->errors()->add('assignment_id', 'Assignment has active vendor candidates.');
                }

                if (OutsourceRequest::query()
                    ->where('assignment_id', $assignmentId)
                    ->whereNot('status', OutsourceRequestStatus::Cancelled)
                    ->exists()
                ) {
                    $validator->errors()->add('assignment_id', 'An external translation request already exists for this assignment.');
                }

                $callerInstitutionId = Auth::user()->institutionId;
                $recipientIds = collect($this->input('offers', []))->pluck('institution_id')->filter();

                $validPartnerIds = InstitutionPartner::query()
                    ->where('institution_id', $callerInstitutionId)
                    ->whereIn('partner_institution_id', $recipientIds)
                    ->whereHas('partnerInstitution', fn($q) => $q->whereNull('deleted_at'))
                    ->pluck('partner_institution_id')
                    ->all();

                foreach ($recipientIds as $index => $institutionId) {
                    if (!in_array($institutionId, $validPartnerIds, true)) {
                        $validator->errors()->add(
                            "offers.{$index}.institution_id",
                            'Institution is not an active partner of your institution.'
                        );
                    }
                }

                $duplicates = $recipientIds->duplicates();
                if ($duplicates->isNotEmpty()) {
                    $validator->errors()->add('offers', 'Offer institution IDs must be unique.');
                }
            },
        ];
    }
}
