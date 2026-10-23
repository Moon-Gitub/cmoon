<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ReturnsJson;
use App\Services\McpQueryService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class CajasEstadoTool extends Tool
{
    use ReturnsJson;

    protected string $name = 'cajas_estado';

    protected string $description = 'Cajas actualmente abiertas de la empresa (sucursal, usuario, monto de apertura). Solo lectura.';

    public function __construct(private McpQueryService $query) {}

    public function handle(Request $request): Response
    {
        return $this->json($this->query->cajas($this->actor()));
    }
}
