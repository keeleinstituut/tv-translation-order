<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/** @template SpecificModelType of Model */
class ModelBelongsToInstitutionRule implements ValidationRule
{
    private Model $model;

    /** @var Closure(): mixed */
    private Closure $expectedInstitutionIdRetriever;

    /** @var ?Closure(SpecificModelType): array<string> */
    private ?Closure $extraErrorChecker;

    /** @var Closure(SpecificModelType): mixed */
    private Closure $actualInstitutionIdRetriever;

    /**
     * @param  Closure(): mixed  $expectedInstitutionIdRetriever
     * @param  ?Closure(SpecificModelType): mixed  $actualInstitutionIdRetriever
     * @param  ?Closure(SpecificModelType): array<string>  $extraErrorChecker
     */
    public function __construct(string $modelClassName, Closure $expectedInstitutionIdRetriever, ?Closure $actualInstitutionIdRetriever = null, ?Closure $extraErrorChecker = null)
    {
        if (! class_exists($modelClassName)
            || ! ($model = new $modelClassName)
            || ! ($model instanceof Model)) {
            throw new InvalidArgumentException("Rule constructed with an in invalid model class name: $modelClassName");
        }

        $this->model = $model;
        $this->expectedInstitutionIdRetriever = $expectedInstitutionIdRetriever;
        $this->actualInstitutionIdRetriever = $actualInstitutionIdRetriever ?? fn (Model $model) => $model->institution_id;
        $this->extraErrorChecker = $extraErrorChecker;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($foundModelInstance = $this->model::find($value))) {
            $fail('Did not find an instance of '.gettype($this->model).' with id '.$value);

            return;
        }

        if (empty($actualInstitutionId = $this->actualInstitutionIdRetriever->__invoke($foundModelInstance))) {
            $fail('Found instance of model but the actual institution_id provided by callback was empty');

            return;
        }

        if (empty($expectedInstitutionId = $this->expectedInstitutionIdRetriever->__invoke())) {
            $fail('The expected institution_id provided by callback was empty');

            return;
        }

        if ($expectedInstitutionId !== $actualInstitutionId) {
            $fail(
                'Actual institution_id did not match expected institutuion id. '.
                "Expected '$expectedInstitutionId', but was '$actualInstitutionId'"
            );
        }

        if (isset($this->extraErrorChecker)) {
            collect($this->extraErrorChecker->__invoke($foundModelInstance))
                ->each(function (string $errorMessage) use ($fail) {
                    $fail($errorMessage);
                });
        }
    }

    /**
     * @param  Closure(SpecificModelType): array<string>  $modelInstanceExtraValidator
     */
    public function addExtraValidation(Closure $modelInstanceExtraValidator): static
    {
        $this->extraErrorChecker = $modelInstanceExtraValidator;

        return $this;
    }

    /**
     * @param  Closure(): SpecificModelType  $expectedInstitutionIdRetriever
     * @param  ?Closure(SpecificModelType): mixed  $actualInstitutionIdRetriever
     * @param  ?Closure(SpecificModelType): array<string>  $extraErrorChecker
     */
    public static function create(
        string $modelClassName,
        Closure $expectedInstitutionIdRetriever,
        ?Closure $actualInstitutionIdRetriever = null,
        ?Closure $extraErrorChecker = null
    ): static {
        return new static(
            $modelClassName,
            $expectedInstitutionIdRetriever,
            actualInstitutionIdRetriever: $actualInstitutionIdRetriever,
            extraErrorChecker: $extraErrorChecker
        );
    }
}
