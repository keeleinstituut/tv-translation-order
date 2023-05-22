<?php

namespace App\Http\Requests\Helpers;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class NestedFormRequestValidator
{
    private FormRequest $formRequest;

    private function __construct(FormRequest $formRequest)
    {
        $formRequest->setContainer(app());
        $formRequest->setRedirector(app(\Illuminate\Routing\Redirector::class));
        $this->formRequest = $formRequest;
    }

    public static function formRequest(FormRequest $formRequest) {
        return new static($formRequest);
    }

    public function setData($data) {
        $this->formRequest->merge($data);
        return $this;
    }

    public function validate() {
        $validator = $this->getValidatorInstance();
        $validator->fails();
        return $this;
    }

    public function setMessagesToValidator(Validator $parentValidator, $prefix = null)
    {
        $validator = $this->getValidatorInstance();

        collect($validator->errors())->each(function ($messages, $field) use ($parentValidator, $prefix) {
            $key = $field;
            if ($prefix) {
                $key = "$prefix.$key";
            }

            collect($messages)->each(function ($message) use ($parentValidator, $key) {
                $parentValidator->errors()->add($key, $message);
            });
        });
    }

    private function getValidatorInstance()
    {
        // This is a hacky solution to construct validator from FormRequest with set rules
        // using the same approach as FormRequest does automatically on request validations.
        // Calls protected method of FormRequest, which is safe and does not affect rest
        // of the application.
        $reflection = new \ReflectionMethod(FormRequest::class, 'getValidatorInstance');
        $reflection->setAccessible(true);
        return $reflection->invoke($this->formRequest);
    }
}