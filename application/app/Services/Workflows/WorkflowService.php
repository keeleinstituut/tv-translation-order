<?php

namespace App\Services\Workflows;

use App\Services\Workflows\Templates\WorkflowTemplateInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class WorkflowService
{
    const DATETIME_FORMAT = 'Y-m-d\TH:i:s.vO';

    public static function createDeployment(WorkflowTemplateInterface $workflowTemplate)
    {
        $response = static::client()->attach(
            'data',
            $workflowTemplate->getDefinition(),
            "{$workflowTemplate->getWorkflowProcessDefinitionId()}.bpmn"
        )->post('/deployment/create', [
            'deploy-changed-only' => true
        ]);

        return $response->throw()->json();
    }

    /**
     * @param $key
     * @param array $params
     * @return array
     * @throws RequestException
     */
    public static function startProcessDefinition($key, array $params = []): array
    {
        $response = static::client()->post("/process-definition/key/$key/start", $params);
        return $response->throw()->json();
    }

    /**
     * @throws RequestException
     */
    public static function getProcessInstances(array $params = []): array
    {
        return static::client()->post('/process-instance', $params)
            ->throw()->json();
    }

    /**
     * @throws RequestException
     */
    public static function addIdentityLink(string $taskId, string $identityId, string $identityType)
    {
        return static::client()->post("/task/$taskId/identity-links", [
            'userId' => $identityId,
            'type' => $identityType
        ])->throw()->json();
    }

    /**
     * @throws RequestException
     */
    public static function getIdentityLinks(string $taskId, string $identityType)
    {
        return static::client()->get("/task/$taskId/identity-links", [
            'type' => $identityType
        ])->throw()->json();
    }

    /**
     * @throws RequestException
     */
    public static function deleteIdentityLink(string $taskId, string $identityId, string $identityType)
    {
        return static::client()->post("/task/$taskId/identity-links/delete", [
            'userId' => $identityId,
            'type' => $identityType
        ])->throw()->json();
    }

    /**
     * @throws RequestException
     */
    public static function updateProcessInstanceVariable($processInstanceId, $variableName, $params = [])
    {
        $response = static::client()->put("/process-instance/$processInstanceId/variables/$variableName", $params);
        return $response->throw()->json();
    }

    /**
     * @throws RequestException
     */
    public static function deleteProcessInstances($processInstanceIds, string $deleteReason)
    {
        return static::client()->post("/process-instance/delete", [
            'deleteReason' => $deleteReason,
            'processInstanceIds' => $processInstanceIds,
            'skipCustomListeners' => false,
            'skipSubprocesses' => false
        ])->throw()->json();
    }

    public static function getTasks($params = [], $queryParams = [])
    {
        $response = static::client()->post('/task?' . http_build_query($queryParams), $params);

        return $response->throw()->json();
    }

    /**
     * @throws RequestException
     */
    public static function getTask(string $id)
    {
        $response = static::client()->get("/task/$id");

        return $response->throw()->json();
    }

    /**
     * @throws RequestException
     */
    public static function getTaskLocalVariables(string $id)
    {
        $response = static::client()->get("/task/$id/localVariables");

        return $response->throw()->json();
    }

    public static function getTasksCount($params = [])
    {
        $response = static::client()->post('/task/count', $params);

        return $response->throw()->json();
    }

    /**
     * @throws RequestException
     */
    public static function completeTask($taskId, $variables = [])
    {
        return static::client()->post("/task/$taskId/complete", [
            'variables' => empty($variables) ? null : $variables
        ])->throw()->json();
    }

    /**
     * @throws RequestException
     */
    public static function completeReviewTask($taskId, bool $isAccepted)
    {
        return static::client()->post("/task/$taskId/complete", [
            'variables' => [
                'subProjectFinished' => [
                    'value' => $isAccepted,
                ]
            ]
        ])->throw()->json();
    }

    /**
     * @throws RequestException
     */
    public static function completeProjectReviewTask($taskId, bool $isAccepted)
    {
        return static::client()->post("/task/$taskId/complete", [
            'variables' => [
                'acceptedByClient' => [
                    'value' => $isAccepted,
                ]
            ]
        ])->throw()->json();
    }

    /**
     * @param $taskId
     * @param array $params
     * @return array|mixed
     * @throws RequestException
     *
     * NOTE: it behaves like POST update (updates whole resource instead of partial update).
     * It will override all not passed task attributes.
     */
    public static function updateTask($taskId, array $params): mixed
    {
        return static::client()->put("/task/$taskId", $params)
            ->throw()->json();
    }


    /**
     * @throws RequestException
     */
    public static function setAssignee($taskId, string $vendorId)
    {
        return static::client()->post("/task/$taskId/assignee", [
            'userId' => $vendorId
        ])->throw()->json();
    }

    public static function getHistoryTask($params = [], $queryParams = [])
    {
        $response = static::client()->post('/history/task?' . http_build_query($queryParams), $params);
        return $response->throw()->json();
    }

    public static function getHistoryTaskCount($params = [])
    {
        $response = static::client()->post('/history/task/count', $params);

        return $response->throw()->json();
    }

    /**
     * @throws RequestException
     */
    public static function sendMessage($params = [])
    {
        $response = static::client()->post('/message', $params);

        return $response->throw()->json();
    }

    public static function getVariableInstance($params = [])
    {
        $response = static::client()->get('/variable-instance', $params);

        return $response->throw()->json();
    }

    public static function getHistoryVariableInstance($params = [])
    {
        $response = static::client()->get('/history/variable-instance', $params);

        return $response->throw()->json();
    }


    private static function client(): PendingRequest
    {
        $baseUrl = config('services.camunda.api_url');

        return Http::baseUrl($baseUrl);
    }
}
