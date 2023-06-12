<?php

namespace App\Services\CAT;

use Illuminate\Support\Facades\Http;


class MateCatServiceBase
{
    public static function createProject($params)
    {
        $collection = collect($params);
        $files = $collection->get('files');
        $rest = $collection->except('files');

        $builder = static::client();
        $files->each(function ($file) use ($builder) {
            $builder->attach('files[]', $file->stream(), $file->file_name);
        });

        $response = $builder->post("/v1/new", $rest->all());
        return $response->throw()->json();
    }

    public static function urls($projectId, $projectPass)
    {
        $builder = static::client();
        $response = $builder->get("/v2/projects/$projectId/$projectPass/urls");
        return $response->throw()->json();
    }

    public static function status($projectId, $projectPass)
    {
        $builder = static::client();
        $response = $builder->get("/status", [
            'id_project' => $projectId,
            'project_pass' => $projectPass,
        ]);
        return $response->throw()->json();
    }

    private static function client()
    {
        $baseUrl = getenv('MATECAT_API_URL');
        return Http::baseUrl($baseUrl)->timeout(30)->connectTimeout(30);
    }
}
