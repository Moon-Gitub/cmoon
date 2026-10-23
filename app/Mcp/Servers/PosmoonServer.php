<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\BuscarClientesTool;
use App\Mcp\Tools\BuscarProductosTool;
use App\Mcp\Tools\CajasEstadoTool;
use App\Mcp\Tools\CuentaClienteTool;
use App\Mcp\Tools\EmpresaResumenTool;
use App\Mcp\Tools\ListarVentasTool;
use App\Mcp\Tools\StockBajoTool;
use App\Mcp\Tools\VerProductoTool;
use App\Mcp\Tools\VerVentaTool;
use Laravel\Mcp\Server;

class PosmoonServer extends Server
{
    protected string $name = 'POSMoon';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        MCP de solo lectura del POS POSMoon.
        El token está atado a una empresa: nunca cruza datos de otro tenant.
        No hay herramientas para crear, editar ni borrar.
        Usá empresa_resumen primero; después buscar_productos, buscar_clientes, listar_ventas, ver_venta, stock_bajo, cajas_estado o cuenta_cliente.
    MARKDOWN;

    protected array $tools = [
        EmpresaResumenTool::class,
        BuscarProductosTool::class,
        VerProductoTool::class,
        BuscarClientesTool::class,
        ListarVentasTool::class,
        VerVentaTool::class,
        StockBajoTool::class,
        CajasEstadoTool::class,
        CuentaClienteTool::class,
    ];
}
