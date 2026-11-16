<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Execution\ProgressTask;
use App\Domain\Execution\ExecutionPhase;
use App\Domain\Execution\ExecutionStatus;
use App\Domain\Execution\ExecutionTransitionException;
use App\Http\GatewayIdentity;
use App\Http\Resources\ExecutionTaskResource;
use App\Models\ExecutionTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Executions', description: 'Fila de execução: diagnóstico e reparo')]
class ExecutionController extends Controller
{
    public function __construct(private readonly ProgressTask $progress) {}

    #[OA\Get(
        path: '/api/v1/executions',
        summary: 'Fila de execução ativa, por prioridade (reparos antes de diagnósticos) e antiguidade',
        security: [['gateway' => []]],
        tags: ['Executions'],
        responses: [new OA\Response(response: 200, description: 'Tarefas queued/in_progress')]
    )]
    public function index(): AnonymousResourceCollection
    {
        $tasks = ExecutionTask::whereIn('status', ExecutionStatus::activeValues())
            ->orderBy('priority', 'desc')->orderBy('queued_at')->get();

        return ExecutionTaskResource::collection($tasks);
    }

    #[OA\Get(path: '/api/v1/executions/{serviceOrderId}', summary: 'Tarefa e timeline da OS', security: [['gateway' => []]], tags: ['Executions'],
        parameters: [new OA\Parameter(name: 'serviceOrderId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Tarefa'), new OA\Response(response: 404, description: 'OS sem tarefa')])]
    public function show(int $serviceOrderId): JsonResponse
    {
        return response()->json(new ExecutionTaskResource($this->task($serviceOrderId)));
    }

    #[OA\Post(path: '/api/v1/executions/{serviceOrderId}/diagnosis/start', summary: 'Mecânico inicia o diagnóstico (OS → in_diagnosis)', security: [['gateway' => []]], tags: ['Executions'],
        parameters: [new OA\Parameter(name: 'serviceOrderId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Em diagnóstico'), new OA\Response(response: 422, description: 'Fase/status inválido')])]
    public function startDiagnosis(Request $request, int $serviceOrderId): JsonResponse
    {
        return $this->act(fn () => $this->progress->start($this->task($serviceOrderId), ExecutionPhase::DIAGNOSIS, $this->mechanic($request)));
    }

    #[OA\Post(path: '/api/v1/executions/{serviceOrderId}/diagnosis/finish', summary: 'Mecânico conclui o diagnóstico (a saga pede o orçamento ao Billing)', security: [['gateway' => []]], tags: ['Executions'],
        parameters: [new OA\Parameter(name: 'serviceOrderId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [new OA\Property(property: 'notes', type: 'string', example: 'Pastilhas gastas')])),
        responses: [new OA\Response(response: 200, description: 'Diagnóstico concluído'), new OA\Response(response: 422, description: 'Fase/status inválido')])]
    public function finishDiagnosis(Request $request, int $serviceOrderId): JsonResponse
    {
        return $this->act(fn () => $this->progress->finish($this->task($serviceOrderId), ExecutionPhase::DIAGNOSIS, $this->mechanic($request), $this->notes($request)));
    }

    #[OA\Post(path: '/api/v1/executions/{serviceOrderId}/repair/start', summary: 'Mecânico inicia o reparo (pivot da saga; OS → in_execution)', security: [['gateway' => []]], tags: ['Executions'],
        parameters: [new OA\Parameter(name: 'serviceOrderId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Em reparo'), new OA\Response(response: 422, description: 'Fase/status inválido')])]
    public function startRepair(Request $request, int $serviceOrderId): JsonResponse
    {
        return $this->act(fn () => $this->progress->start($this->task($serviceOrderId), ExecutionPhase::REPAIR, $this->mechanic($request)));
    }

    #[OA\Post(path: '/api/v1/executions/{serviceOrderId}/repair/finish', summary: 'Mecânico finaliza o reparo (OS → finalized; Billing abre o pagamento)', security: [['gateway' => []]], tags: ['Executions'],
        parameters: [new OA\Parameter(name: 'serviceOrderId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [new OA\Property(property: 'notes', type: 'string')])),
        responses: [new OA\Response(response: 200, description: 'Reparo finalizado'), new OA\Response(response: 422, description: 'Fase/status inválido')])]
    public function finishRepair(Request $request, int $serviceOrderId): JsonResponse
    {
        return $this->act(fn () => $this->progress->finish($this->task($serviceOrderId), ExecutionPhase::REPAIR, $this->mechanic($request), $this->notes($request)));
    }

    private function task(int $serviceOrderId): ExecutionTask
    {
        return ExecutionTask::where('service_order_id', $serviceOrderId)->firstOrFail();
    }

    private function mechanic(Request $request): int
    {
        return GatewayIdentity::fromRequest($request)->userId;
    }

    private function notes(Request $request): ?string
    {
        $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);

        return $request->input('notes');
    }

    /** @param callable(): ExecutionTask $action */
    private function act(callable $action): JsonResponse
    {
        try {
            return response()->json(new ExecutionTaskResource($action()));
        } catch (ExecutionTransitionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
