<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolController extends Controller
{
    public function index(): View
    {
        // Agrupar permisos por módulo (prefijo antes del punto)
        $permisos = Permission::orderBy('name')->get()
            ->groupBy(fn ($permiso) => explode('.', $permiso->name)[0]);

        return view('roles.index', [
            'roles' => Role::with('permissions')->withCount('users')->orderBy('name')->get(),
            'permisos' => $permisos,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('roles', 'name')],
        ], [], ['name' => 'nombre del rol']);

        Role::create(['name' => $datos['name'], 'guard_name' => 'web']);

        return back()->with('ok', "Rol {$datos['name']} creado. Ahora asignale permisos.");
    }

    public function update(Request $request, Role $rol): RedirectResponse
    {
        $datos = $request->validate([
            'permisos' => ['nullable', 'array'],
            'permisos.*' => [Rule::exists('permissions', 'name')],
        ]);

        if ($rol->name === 'Administrador') {
            return back()->with('error', 'El rol Administrador no se puede modificar: siempre tiene todos los permisos.');
        }

        $rol->syncPermissions($datos['permisos'] ?? []);

        return back()->with('ok', "Permisos del rol {$rol->name} actualizados.");
    }

    public function destroy(Role $rol): RedirectResponse
    {
        if ($rol->name === 'Administrador') {
            return back()->with('error', 'El rol Administrador no se puede eliminar.');
        }

        if ($rol->users()->count() > 0) {
            return back()->with('error', "No se puede eliminar: hay usuarios con el rol {$rol->name}.");
        }

        $rol->delete();

        return back()->with('ok', "Rol {$rol->name} eliminado.");
    }
}
