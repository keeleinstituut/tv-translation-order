<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis;
use PhpAmqpLib\Connection\AbstractConnection;
use SyncTools\AmqpConnectionRegistry;

class HealthCheckController extends Controller
{
    private const PROCESS_PATTERNS = [
        'php-fpm' => '/php-fpm/',
        'laravel-worker' => '/artisan queue:work/',
        'laravel-scheduler' => '/artisan schedule:work/',
        'laravel-classifiers-sync' => '/amqp:consume.*classifier-value/',
        'laravel-institutions-sync' => '/amqp:consume.*institution[^-]/',
        'laravel-institution-users-sync' => '/amqp:consume.*institution-user/',
    ];

    private const CRITICAL_PROCESSES = [
        'php-fpm',
    ];

    public function startup(): JsonResponse
    {
        try {
            if (!$this->checkProcesses(self::CRITICAL_PROCESSES)) {
                return $this->unhealthy('Critical processes not starting');
            }

            if (!$this->checkDatabase()) {
                return $this->unhealthy('Database not accessible');
            }

            return $this->healthy();
        } catch (Exception $e) {
            Log::error('Startup health check failed: ' . $e->getMessage());
            return $this->unhealthy('Startup check failed');
        }
    }

    /**
     * Readiness probe - checks if container is ready to serve traffic
     * Returns 200 when ready, 503 otherwise
     */
    public function ready(): JsonResponse
    {
        try {
            if (!$this->checkProcesses(array_keys(self::PROCESS_PATTERNS))) {
                return $this->unhealthy('Required processes not running');
            }

            if (!$this->checkDatabase()) {
                return $this->unhealthy('Application database not accessible');
            }

            if (!$this->checkRedis()) {
                return $this->unhealthy('Redis not accessible');
            }

            if (!$this->checkAmqp()) {
                return $this->unhealthy('AMQP not accessible');
            }

            return $this->healthy();
        } catch (Exception $e) {
            Log::error('Readiness health check failed: ' . $e->getMessage());
            return $this->unhealthy('Readiness check failed');
        }
    }

    public function live(): JsonResponse
    {
        try {
            if (!$this->checkProcesses(array_keys(self::PROCESS_PATTERNS))) {
                return $this->unhealthy('Critical processes not running');
            }

            if (!$this->checkDatabase()) {
                return $this->unhealthy('Database not accessible');
            }

            return $this->healthy();
        } catch (Exception $e) {
            Log::error('Liveness health check failed: ' . $e->getMessage());
            return $this->unhealthy('Liveness check failed');
        }
    }


    private function checkProcesses(array $processes): bool
    {
        try {
            $result = Process::timeout(2)
                ->run('ps aux');

            if (!$result->successful()) {
                Log::warning('ps aux command failed');
                return false;
            }

            $output = $result->output();
            $foundProcesses = [];

            foreach ($processes as $process) {
                if (!isset(self::PROCESS_PATTERNS[$process])) {
                    Log::warning("Unknown process in check: $process");
                    continue;
                }

                $pattern = self::PROCESS_PATTERNS[$process];
                if (preg_match($pattern, $output)) {
                    $foundProcesses[] = $process;
                }
            }

            return count($foundProcesses) === count($processes);
        } catch (Exception $e) {
            Log::error('Process check failed: ' . $e->getMessage());
            return false;
        }
    }

    private function checkDatabase(?string $connection = null): bool
    {
        try {
            if ($connection) {
                DB::connection($connection)->select('SELECT 1');
            } else {
                DB::select('SELECT 1');
            }
            return true;
        } catch (Exception $e) {
            $connectionName = $connection ?? 'default';
            Log::warning("Database check failed for {$connectionName}: " . $e->getMessage());
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            Redis::command('PING');
            return true;
        } catch (Exception $e) {
            Log::warning('Redis check failed: ' . $e->getMessage());
            return false;
        }
    }

    private function checkAmqp(): bool
    {
        try {
            /** @var AbstractConnection $connection */
            $connection = app(AmqpConnectionRegistry::class)->getConnection();
            $channel = $connection->channel();

            if (!$channel->is_open()) {
                $channel->close();
                return false;
            }

            $channel->close();
            return true;
        } catch (Exception $e) {
            Log::warning('AMQP check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Return healthy response
     */
    private function healthy(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    /**
     * Return unhealthy response
     */
    private function unhealthy(string $reason): JsonResponse
    {
        return response()->json(['status' => 'unhealthy', 'reason' => $reason], 503);
    }
}

