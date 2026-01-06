<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use SyncTools\AmqpConnectionRegistry;
use Exception;

class HealthCheckController extends Controller
{
    /**
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            DB::select('SELECT 1');

            Redis::command('PING');

            $amqpRegistry = app(AmqpConnectionRegistry::class);
            $channel = $amqpRegistry->getConnection()->channel();
            if (!$channel->is_open()) {
                throw new Exception('AMQP channel is not open');
            }
            return response()->json(['status' => 'ok']);
        } catch (Exception $e) {
            Log::error('Health check failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Health check failed'
            ], 503);
        }
    }
}

