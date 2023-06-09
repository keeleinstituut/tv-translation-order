<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\ClassifierValueListRequest;
use App\Http\Resources\API\ClassifierValueResource;
use App\Models\ClassifierValue;

class ClassifierValueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ClassifierValueListRequest $request)
    {
        $params = collect($request->validated());

        $query = $this->getBaseQuery();

        if ($type = $params->get('type')) {
            $query = $query->where('type', $type);
        }

        $data = $query->get();

        return ClassifierValueResource::collection($data);
    }

    private function getBaseQuery() {
        return ClassifierValue::getModel();
    }
}
