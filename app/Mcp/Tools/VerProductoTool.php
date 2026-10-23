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
class VerProductoTool extends Tool
{
    use ReturnsJson;

    protected string $name = 'ver_producto';

    protected string $description = 'Detalle de un producto (precios y stock por sucursal). Solo lectura.';

    public function __construct(private McpQueryService $query) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('ID del producto'),
        ];
    }

    public function handle(Request $request): Response
    {
        $datos = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
        ]);

        return $this->json($this->query->producto($this->actor(), (int) $datos['id']));
    }
}
