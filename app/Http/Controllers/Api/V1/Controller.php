<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller as BaseController;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Execution Service — Workshop OS',
    description: 'Fila de execução da oficina: diagnóstico e reparo (MongoDB). Fase 4: chamado via Traefik, que autentica no OS Service '
        .'(ForwardAuth) e repassa X-User-Id/X-User-Role/X-User-Client-Id.',
)]
#[OA\Server(url: L5_SWAGGER_CONST_HOST, description: 'Gateway (Traefik)')]
#[OA\SecurityScheme(securityScheme: 'gateway', type: 'http', scheme: 'bearer', description: 'Token do OS Service (Sanctum ou JWT do Lambda), validado pelo gateway')]
abstract class Controller extends BaseController {}
