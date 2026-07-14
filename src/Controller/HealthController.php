<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\Database;
use App\Http\Request;
use App\Http\Response;
use Throwable;

/**
 * Health checks para orquestadores (Docker, Kubernetes, load balancers).
 *
 * - /health/live:  el proceso responde (no toca la BD).
 * - /health/ready: además comprueba que la BD está accesible.
 */
final class HealthController
{
    public function __construct(private readonly Database $database)
    {
    }

    public function live(Request $request, array $params, ?string $ownerKeyId): Response
    {
        return Response::json(['status' => 'Healthy']);
    }

    public function ready(Request $request, array $params, ?string $ownerKeyId): Response
    {
        try {
            $this->database->connection()->query('SELECT 1');
        } catch (Throwable) {
            return Response::json(['status' => 'Unhealthy', 'checks' => ['mysql' => 'Unhealthy']], 503);
        }

        return Response::json(['status' => 'Healthy', 'checks' => ['mysql' => 'Healthy']]);
    }
}
