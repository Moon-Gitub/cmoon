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
class StockBajoTool extends Tool
{
    use ReturnsJson;

    protected string $name = 'stock_bajo';

    protected string $description = 'Productos activos con stock en o por debajo del mínimo. Solo lectura.';

    public function __construct(private McpQueryService $query) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Cantidad máxima (1-50)')->default(30),
        ];
    }

    public function handle(Request $request): Response
    {
        $datos = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return $this->json($this->query->stockBajo($this->actor(), (int) ($datos['limit'] ?? 30)));
    }
}
