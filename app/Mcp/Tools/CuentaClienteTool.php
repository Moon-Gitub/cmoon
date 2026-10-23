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
class CuentaClienteTool extends Tool
{
    use ReturnsJson;

    protected string $name = 'cuenta_cliente';

    protected string $description = 'Saldo y últimos movimientos de cuenta corriente de un cliente. Solo lectura.';

    public function __construct(private McpQueryService $query) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'cliente_id' => $schema->integer()->required()->description('ID del cliente'),
            'limit' => $schema->integer()->description('Movimientos a devolver (1-50)')->default(20),
        ];
    }

    public function handle(Request $request): Response
    {
        $datos = $request->validate([
            'cliente_id' => ['required', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return $this->json($this->query->cuentaCliente(
            $this->actor(),
            (int) $datos['cliente_id'],
            (int) ($datos['limit'] ?? 20),
        ));
    }
}
