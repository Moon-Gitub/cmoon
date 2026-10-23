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
class BuscarProductosTool extends Tool
{
    use ReturnsJson;

    protected string $name = 'buscar_productos';

    protected string $description = 'Busca productos por nombre o código. Devuelve precio y stock. Solo lectura, máximo 50.';

    public function __construct(private McpQueryService $query) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string()->description('Texto a buscar en nombre o código'),
            'limit' => $schema->integer()->description('Cantidad máxima (1-50)')->default(20),
            'solo_activos' => $schema->boolean()->description('Si es false incluye inactivos')->default(true),
        ];
    }

    public function handle(Request $request): Response
    {
        $datos = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'solo_activos' => ['nullable', 'boolean'],
        ]);

        return $this->json($this->query->productos(
            $this->actor(),
            $datos['q'] ?? null,
            (int) ($datos['limit'] ?? 20),
            array_key_exists('solo_activos', $datos) ? (bool) $datos['solo_activos'] : true,
        ));
    }
}
