<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ReturnsJson;
use App\Services\McpQueryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class ListarVentasTool extends Tool
{
    use ReturnsJson;

    protected string $name = 'listar_ventas';

    protected string $description = 'Lista ventas recientes. Filtros opcionales: desde, hasta (YYYY-MM-DD) y estado. Solo lectura, máximo 50.';

    public function __construct(private McpQueryService $query) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'desde' => $schema->string()->description('Fecha desde (YYYY-MM-DD)'),
            'hasta' => $schema->string()->description('Fecha hasta (YYYY-MM-DD)'),
            'estado' => $schema->string()->description('Estado: completada, anulada, etc.'),
            'limit' => $schema->integer()->description('Cantidad máxima (1-50)')->default(20),
        ];
    }

    public function handle(Request $request): Response
    {
        $datos = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'estado' => ['nullable', 'string', 'max:40'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return $this->json($this->query->ventas(
            $this->actor(),
            $datos['desde'] ?? null,
            $datos['hasta'] ?? null,
            $datos['estado'] ?? null,
            (int) ($datos['limit'] ?? 20),
        ));
    }
}
