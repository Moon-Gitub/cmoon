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
class EmpresaResumenTool extends Tool
{
    use ReturnsJson;

    protected string $name = 'empresa_resumen';

    protected string $description = 'Resumen de la empresa del token: identidad, ventas de hoy y del mes, stock bajo y cajas abiertas. Solo lectura.';

    public function __construct(private McpQueryService $query) {}

    public function handle(Request $request): Response
    {
        return $this->json($this->query->resumen($this->actor()));
    }
}
