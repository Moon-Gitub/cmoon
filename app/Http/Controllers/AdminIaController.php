<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\IaCompra;
use App\Models\IaPaquete;
use App\Models\User;
use App\Services\IaConfigService;
use App\Services\IaCupoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminIaController extends Controller
{
    public function index(IaConfigService $config, IaCupoService $cupo): View
    {
        $empresas = Empresa::query()
            ->orderBy('razon_social')
            ->get()
            ->map(function (Empresa $e) use ($cupo) {
                $e->setAttribute('cupo_ia', $cupo->resumen($e->id));

                return $e;
            });

        return view('admin.ia.index', [
            'config' => $config->config(),
            'resolver' => $config->resolver(),
            'providers' => $config->providers(),
            'paquetes' => IaPaquete::query()->orderBy('orden')->get(),
            'compras' => IaCompra::query()
                ->with(['empresa', 'usuario', 'paquete'])
                ->latest()
                ->limit(50)
                ->get(),
            'empresas' => $empresas,
            'superadmins' => User::query()->where('es_superadmin', true)->get(['id', 'name', 'email']),
        ]);
    }

    public function guardarConfig(Request $request, IaConfigService $config): RedirectResponse
    {
        $datos = $request->validate([
            'provider' => ['required', 'in:openai,openrouter,groq,custom'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'base_url' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:120'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $datos['activo'] = $request->boolean('activo');
        if (! empty($datos['base_url']) && ! filter_var($datos['base_url'], FILTER_VALIDATE_URL)) {
            return back()->withErrors(['base_url' => 'La Base URL no es válida.'])->withInput();
        }

        $config->guardar($datos);

        return back()->with('ok', 'Configuración de IA guardada.');
    }

    public function acreditar(Request $request, IaCupoService $cupo): RedirectResponse
    {
        $datos = $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
            'creditos' => ['required', 'integer', 'min:1', 'max:100000'],
            'ia_cupo_override' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'ia_plan' => ['nullable', 'in:incluido,abono'],
            'ia_abono_hasta' => ['nullable', 'date'],
        ]);

        $empresa = Empresa::query()->findOrFail($datos['empresa_id']);
        $cupo->acreditar($empresa->id, (int) $datos['creditos']);

        $update = [];
        if (array_key_exists('ia_cupo_override', $datos) && $datos['ia_cupo_override']) {
            $update['ia_cupo_override'] = $datos['ia_cupo_override'];
        }
        if (! empty($datos['ia_plan'])) {
            $update['ia_plan'] = $datos['ia_plan'];
            if ($datos['ia_plan'] === 'abono') {
                $update['ia_abono_hasta'] = $datos['ia_abono_hasta'] ?? now()->addYear()->toDateString();
                $update['ia_abono_solicitado_at'] = null;
            }
        }
        if ($update !== []) {
            $empresa->update($update);
        }

        return back()->with('ok', "Acreditados {$datos['creditos']} créditos a {$empresa->razon_social}.");
    }

    public function aprobarCompra(IaCompra $compra, IaCupoService $cupo): RedirectResponse
    {
        $cupo->aprobarCompra($compra, (int) auth()->id());

        return back()->with('ok', 'Compra aprobada y créditos acreditados.');
    }

    public function rechazarCompra(Request $request, IaCompra $compra, IaCupoService $cupo): RedirectResponse
    {
        $datos = $request->validate(['notas' => ['nullable', 'string', 'max:500']]);
        $cupo->rechazarCompra($compra, (int) auth()->id(), $datos['notas'] ?? null);

        return back()->with('ok', 'Compra rechazada.');
    }

    public function guardarPaquete(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'id' => ['nullable', 'exists:ia_paquetes,id'],
            'nombre' => ['required', 'string', 'max:80'],
            'creditos' => ['required', 'integer', 'min:1', 'max:100000'],
            'precio' => ['required', 'numeric', 'min:0'],
            'moneda' => ['nullable', 'string', 'max:10'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:999'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $payload = [
            'nombre' => $datos['nombre'],
            'creditos' => $datos['creditos'],
            'precio' => $datos['precio'],
            'moneda' => $datos['moneda'] ?? 'ARS',
            'orden' => $datos['orden'] ?? 0,
            'activo' => $request->boolean('activo', true),
        ];

        if (! empty($datos['id'])) {
            IaPaquete::query()->where('id', $datos['id'])->update($payload);
        } else {
            IaPaquete::query()->create($payload);
        }

        return back()->with('ok', 'Paquete guardado.');
    }

    public function marcarSuperadmin(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'activo' => ['nullable', 'boolean'],
        ]);

        User::query()->where('email', $datos['email'])->update([
            'es_superadmin' => $request->boolean('activo', true),
        ]);

        return back()->with('ok', 'Flag supermegaadmin actualizado.');
    }
}
