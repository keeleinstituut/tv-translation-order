<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\SkillListRequest;
use App\Http\Resources\API\SkillResource;
use App\Models\Skill;
use OpenApi\Attributes as OA;

class SkillController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/skills',
        summary: 'List skills',
        tags: ['Skills'],
        parameters: [],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: SkillResource::class)]
    public function index(SkillListRequest $request)
    {
        $query = $this->getBaseQuery();
        $data = $query->get();

        return SkillResource::collection($data);
    }

    private function getBaseQuery()
    {
        return Skill::getModel();
    }
}
