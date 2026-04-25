<?php

namespace App\Http\Requests\API;

use App\Enums\CandidateStatus;
use App\Enums\ExternalRequestStatus;
use App\Models\Assignment;
use App\Models\Candidate;
use App\Models\ExternalTranslationRequest;
use App\Models\InstitutionPartner;
use App\Rules\ProjectFileValidator;
use App\Rules\ScannedRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class ExternalTranslationRequestCreateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'assignment_id' => 'required|uuid|exists:assignments,id',
            'mode' => 'required|string|in:CASCADE,PARALLEL',
            'reaction_time_minutes' => 'required_if:mode,CASCADE|nullable|integer|in:15,30,60,120,180,240',
            'deadline_at' => 'required_if:mode,PARALLEL|nullable|date|after:now',
            'recipients' => 'required|array|min:1',
            'recipients.*.institution_id' => 'required|uuid',
            'special_instructions' => 'nullable|string',
            'include_source_files' => 'nullable|boolean',
            'include_price' => 'nullable|boolean',
            'override_price' => 'nullable|numeric|min:0',
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

                if ($assignment->external_institution_id !== null) {
                    $validator->errors()->add('assignment_id', 'Assignment is already shared with an external institution.');
                }

                if (ExternalTranslationRequest::query()
                    ->where('assignment_id', $assignmentId)
                    ->where('status', ExternalRequestStatus::Active)
                    ->exists()
                ) {
                    $validator->errors()->add('assignment_id', 'An active external translation request already exists for this assignment.');
                }

                $callerInstitutionId = Auth::user()->institutionId;
                $recipientIds = collect($this->input('recipients', []))->pluck('institution_id')->filter();

                $validPartnerIds = InstitutionPartner::query()
                    ->where('institution_id', $callerInstitutionId)
                    ->whereIn('partner_institution_id', $recipientIds)
                    ->whereHas('partnerInstitution', fn($q) => $q->whereNull('deleted_at'))
                    ->pluck('partner_institution_id')
                    ->all();

                foreach ($recipientIds as $index => $institutionId) {
                    if (!in_array($institutionId, $validPartnerIds, true)) {
                        $validator->errors()->add(
                            "recipients.{$index}.institution_id",
                            'Institution is not an active partner of your institution.'
                        );
                    }
                }

                $duplicates = $recipientIds->duplicates();
                if ($duplicates->isNotEmpty()) {
                    $validator->errors()->add('recipients', 'Recipient institution IDs must be unique.');
                }
            },
        ];
    }
}
