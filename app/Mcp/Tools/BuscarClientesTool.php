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
class BuscarClientesTool extends Tool
{
    use ReturnsJson;

    protected string $name = 'buscar_clientes';

    protected string $description = 'Busca clientes por nombre, documento, email o teléfono. Incluye saldo de cuenta corriente. Solo lectura.';

    public function __construct(private McpQueryService $query) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string()->description('Texto a buscar'),
            'limit' => $schema->integer()->description('Cantidad máxima (1-50)')->default(20),
        ];
    }

    public function handle(Request $request): Response
    {
        $datos = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return $this->json($this->query->clientes(
            $this->actor(),
            $datos['q'] ?? null,
            (int) ($datos['limit'] ?? 20),
        ));
    }
}
