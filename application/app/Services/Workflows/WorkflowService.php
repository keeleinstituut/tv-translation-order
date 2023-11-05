<?php

namespace App\Services\Workflows;

use App\Services\Workflows\Templates\SubProjectWorkflowTemplateInterface;
use App\Services\Workflows\Templates\WorkflowTemplateInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use phpseclib3\Math\BigInteger\Engines\PHP;

class WorkflowService
{
    public static function processDefinitionList()
    {
        $response = static::client()->get('/process-definition');

        return $response;
    }

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

    public static function updateProcessInstanceVariable($processInstanceId, $variableName, $params = [])
    {
        $response = static::client()->put("/process-instance/$processInstanceId/variables/$variableName", $params);
        return $response->throw()->json();
    }

    public static function deleteProcessInstances($processInstanceIds, string $deleteReason)
    {
        $response = static::client()->post("/process-instance/delete", [
            'deleteReason' => $deleteReason,
            'processInstanceIds' => $processInstanceIds,
            'skipCustomListeners' => true,
            'skipSubprocesses' => true
        ]);

        return $response->throw()->json();
    }

    public static function getProcessInstanceVariable($processInstanceId, $variableName, $params = [])
    {
        return static::client()->get("/process-instance/$processInstanceId/variables/$variableName", $params)
            ->throw()->json();
    }

    public static function getTask($params = [])
    {
        $response = static::client()->get('/task', $params);

        return $response->throw()->json();
    }

    public static function getTaskCount($params = [])
    {
        $response = static::client()->get('/task/count', $params);

        return $response->throw()->json();
    }

    public static function completeTask($taskId, $params = [])
    {
        $response = static::client()->post("/task/$taskId/complete", $params);
        return $response->throw()->json();
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

    public static function getHistoryTask($params = [])
    {
        $response = static::client()->get('/history/task', $params);

        return $response->throw()->json();
    }

    public static function getHistoryTaskCount($params = [])
    {
        $response = static::client()->get('/history/task/count', $params);

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

    private static function client()
    {
        $baseUrl = getenv('CAMUNDA_API_URL');

        return Http::baseUrl($baseUrl);
    }
}
