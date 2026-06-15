<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\DesktopInstallation;
use App\Models\MovimientoCuenta;
use App\Models\Entrega;
use App\Models\Visita;
use App\Services\Desktop\DesktopLicenseService;
use App\Services\Mobile\MobileFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileFieldApiController extends Controller
{
    public function __construct(
        private MobileFieldService $campo,
        private DesktopLicenseService $licencias,
    ) {}

    public function cliente(Request $request, Cliente $cliente): JsonResponse
    {
        abort_unless($this->campo->clientesQuery(auth()->user())->where('id', $cliente->id)->exists(), 404);

        return response()->json($this->campo->detalleCliente($cliente));
    }

    public function rutasMias(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->can('rutas.ver_movil'), 403);

        return response()->json([
            'fecha' => today()->toDateString(),
            'clientes' => $this->campo->rutasDelDia(auth()->user()),
        ]);
    }

    public function entregasPendientes(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->can('entregas.confirmar_movil'), 403);

        return response()->json([
            'items' => $this->campo->entregasPendientes(auth()->user()),
        ]);
    }

    public function reporteVendedor(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->can('reportes.vendedor_movil'), 403);

        $datos = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        return response()->json(
            $this->campo->reporteVendedor(auth()->user(), $datos['desde'] ?? null, $datos['hasta'] ?? null)
        );
    }

    public function syncCobranzas(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->can('cobranzas.crear_movil'), 403);

        return $this->syncLote($request, 'cobranzas', function (array $item) {
            $existente = MovimientoCuenta::where('uuid', $item['uuid'])->first();
            if ($existente) {
                return ['uuid' => $item['uuid'], 'ok' => true, 'id' => $existente->id];
            }

            $mov = $this->campo->registrarCobranza(auth()->user(), $item);

            return ['uuid' => $item['uuid'], 'ok' => true, 'id' => $mov->id];
        }, [
            'cobranzas' => ['required', 'array'],
            'cobranzas.*.uuid' => ['required', 'uuid'],
            'cobranzas.*.cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'cobranzas.*.importe' => ['required', 'numeric', 'gt:0'],
            'cobranzas.*.concepto' => ['nullable', 'string', 'max:255'],
            'cobranzas.*.fecha' => ['nullable', 'date'],
        ]);
    }

    public function syncVisitas(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->can('rutas.ver_movil'), 403);

        return $this->syncLote($request, 'visitas', function (array $item) {
            $existente = Visita::where('uuid', $item['uuid'])->first();
            if ($existente) {
                return ['uuid' => $item['uuid'], 'ok' => true, 'id' => $existente->id];
            }

            $visita = $this->campo->registrarVisita(auth()->user(), $item);

            return ['uuid' => $item['uuid'], 'ok' => true, 'id' => $visita->id];
        }, [
            'visitas' => ['required', 'array'],
            'visitas.*.uuid' => ['required', 'uuid'],
            'visitas.*.cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'visitas.*.estado' => ['nullable', 'string', 'max:20'],
            'visitas.*.fecha' => ['nullable', 'date'],
            'visitas.*.lat' => ['nullable', 'numeric'],
            'visitas.*.lng' => ['nullable', 'numeric'],
            'visitas.*.observaciones' => ['nullable', 'string', 'max:1000'],
            'visitas.*.ruta_id' => ['nullable', 'integer', 'exists:rutas,id'],
        ]);
    }

    public function syncEntregas(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->can('entregas.confirmar_movil'), 403);

        return $this->syncLote($request, 'entregas', function (array $item) {
            $existente = Entrega::where('uuid', $item['uuid'])->first();
            if ($existente) {
                return ['uuid' => $item['uuid'], 'ok' => true, 'id' => $existente->id];
            }

            $entrega = $this->campo->registrarEntrega(auth()->user(), $item);

            return ['uuid' => $item['uuid'], 'ok' => true, 'id' => $entrega->id];
        }, [
            'entregas' => ['required', 'array'],
            'entregas.*.uuid' => ['required', 'uuid'],
            'entregas.*.cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'entregas.*.presupuesto_id' => ['nullable', 'integer', 'exists:presupuestos,id'],
            'entregas.*.venta_id' => ['nullable', 'integer', 'exists:ventas,id'],
            'entregas.*.estado' => ['nullable', 'string', 'max:20'],
            'entregas.*.observaciones' => ['nullable', 'string', 'max:1000'],
            'entregas.*.firma_base64' => ['nullable', 'string'],
            'entregas.*.fotos_base64' => ['nullable', 'array'],
            'entregas.*.fotos_base64.*' => ['nullable', 'string'],
        ]);
    }

    private function syncLote(Request $request, string $key, callable $procesar, array $rules): JsonResponse
    {
        /** @var DesktopInstallation $instalacion */
        $instalacion = $request->attributes->get('desktop_installation');
        $deviceToken = $request->attributes->get('desktop_token');

        $datos = $request->validate($rules);
        $resultados = [];

        foreach ($datos[$key] as $item) {
            try {
                $resultados[] = $procesar($item);
            } catch (\Throwable $e) {
                $resultados[] = ['uuid' => $item['uuid'] ?? null, 'ok' => false, 'error' => $e->getMessage()];
            }
        }

        $instalacion->update(['last_sync_at' => now()]);

        return response()->json([
            'resultados' => $resultados,
            'license' => $this->licencias->emitir($instalacion, $deviceToken)['license'],
        ]);
    }
}
