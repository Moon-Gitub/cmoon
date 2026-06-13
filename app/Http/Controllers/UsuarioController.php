<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    public function index(Request $request): View
    {
        $usuarios = User::with(['roles', 'sucursal'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->when($request->filled('buscar'), function ($query) use ($request) {
                $buscar = $request->string('buscar');
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$buscar}%")
                    ->orWhere('usuario', 'like', "%{$buscar}%")
                    ->orWhere('email', 'like', "%{$buscar}%"));
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('usuarios.index', compact('usuarios'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()->can('usuarios.crear'), 403);

        return view('usuarios.form', [
            'usuarioEditado' => new User,
            'roles' => Role::orderBy('name')->get(),
            'sucursales' => Sucursal::where('activa', true)->orderBy('nombre')->get(),
            'empresas' => auth()->user()->can('empresas.gestionar')
                ? Empresa::where('activa', true)->orderBy('razon_social')->get(['id', 'razon_social'])
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('usuarios.crear'), 403);

        $datos = $this->validar($request);

        $usuario = User::create([
            ...$datos,
            'empresa_id' => $this->empresaAsignada($request),
        ]);

        $usuario->syncRoles([$request->input('rol')]);

        return redirect()->route('usuarios.index')
            ->with('ok', "Usuario {$usuario->name} creado.");
    }

    public function edit(User $usuario): View
    {
        abort_unless(auth()->user()->can('usuarios.editar'), 403);

        return view('usuarios.form', [
            'usuarioEditado' => $usuario,
            'roles' => Role::orderBy('name')->get(),
            'sucursales' => Sucursal::where('activa', true)->orderBy('nombre')->get(),
            'empresas' => auth()->user()->can('empresas.gestionar')
                ? Empresa::where('activa', true)->orderBy('razon_social')->get(['id', 'razon_social'])
                : collect(),
        ]);
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        abort_unless(auth()->user()->can('usuarios.editar'), 403);

        $datos = $this->validar($request, $usuario);

        if (empty($datos['password'])) {
            unset($datos['password']);
        }

        // Evitar que un admin se desactive a sí mismo
        if ($usuario->id === auth()->id()) {
            $datos['activo'] = true;
        }

        if ($request->filled('empresa_id')) {
            $datos['empresa_id'] = $this->empresaAsignada($request);
        }

        $usuario->update($datos);
        $usuario->syncRoles([$request->input('rol')]);

        return redirect()->route('usuarios.index')
            ->with('ok', "Usuario {$usuario->name} actualizado.");
    }

    public function destroy(User $usuario): RedirectResponse
    {
        abort_unless(auth()->user()->can('usuarios.eliminar'), 403);

        if ($usuario->id === auth()->id()) {
            return back()->with('error', 'No podés eliminar tu propio usuario.');
        }

        $usuario->delete();

        return redirect()->route('usuarios.index')
            ->with('ok', "Usuario {$usuario->name} eliminado.");
    }

    /**
     * Solo quien gestiona empresas puede asignar usuarios a otra empresa;
     * el resto crea usuarios dentro de la suya.
     */
    private function empresaAsignada(Request $request): int
    {
        if (auth()->user()->can('empresas.gestionar') && $request->filled('empresa_id')) {
            $request->validate(['empresa_id' => [Rule::exists('empresas', 'id')]]);

            return $request->integer('empresa_id');
        }

        return auth()->user()->empresa_id;
    }

    private function validar(Request $request, ?User $usuario = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'usuario' => [
                'required', 'string', 'max:50', 'alpha_dash',
                Rule::unique('users', 'usuario')->ignore($usuario)->withoutTrashed(),
            ],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($usuario)->withoutTrashed(),
            ],
            'password' => [
                $usuario ? 'nullable' : 'required',
                'confirmed',
                Password::min(8),
            ],
            'rol' => ['required', Rule::exists('roles', 'name')],
            'sucursal_id' => ['nullable', Rule::exists('sucursales', 'id')],
            'activo' => ['boolean'],
        ], [], [
            'name' => 'nombre',
            'usuario' => 'usuario',
            'password' => 'contraseña',
            'rol' => 'rol',
            'sucursal_id' => 'sucursal',
        ]) + ['activo' => $request->boolean('activo')];
    }
}
