<?php

namespace App\Rules;

use App\Models\Assignment;
use App\Models\Vendor;
use Illuminate\Validation\Validator;

readonly class OutsourcedAssignmentCandidateInstitutionRule
{
    public function __construct(
        private string $assignmentId,
        private string $message,
    ) {}

    public function __invoke(Validator $validator): void
    {
        $assignment = Assignment::with('currentOutsourceRequest.acceptedOffer')
            ->find($this->assignmentId);

        $outsourcedInstitutionId = $assignment?->currentOutsourceRequest?->acceptedOffer?->institution_id;

        if (! filled($outsourcedInstitutionId)) {
            return;
        }

        $vendorIds = collect($validator->validated()['data'] ?? [])->pluck('vendor_id');
        $validCount = Vendor::whereIn('id', $vendorIds)
            ->whereHas('institutionUser', fn ($q) => $q->where('institution->id', $outsourcedInstitutionId))
            ->count();

        if ($validCount !== $vendorIds->count()) {
            $validator->errors()->add('data', $this->message);
        }
    }
}
