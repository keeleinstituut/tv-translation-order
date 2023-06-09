<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\InstitutionUserListRequest;
use App\Http\Resources\API\InstitutionUserResource;
use App\Models\InstitutionUser;

class InstitutionUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(InstitutionUserListRequest $request)
    {
        $params = collect($request->validated());

        $query = $this->getBaseQuery();
        $data = $query->paginate($params->get('limit', 10));

        return InstitutionUserResource::collection($data);
    }

    private function getBaseQuery()
    {
        return InstitutionUser::getModel();
    }
}
