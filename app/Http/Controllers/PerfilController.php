<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PerfilController extends Controller
{
    public function edit(): View
    {
        return view('perfil.edit');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'password_actual' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'password_actual' => 'contraseña actual',
            'password' => 'contraseña nueva',
        ]);

        $request->user()->update(['password' => $request->input('password')]);

        return back()->with('ok', 'Contraseña actualizada correctamente.');
    }
}
