<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthTokenController extends Controller
{
    /**
     * Emite un token Bearer de Sanctum para consumir /api/v1/*.
     */
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'usuario' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::query()
            ->where('usuario', $datos['usuario'])
            ->orWhere('email', $datos['usuario'])
            ->first();

        if (! $user || ! Hash::check($datos['password'], $user->password) || ! $user->activo) {
            throw ValidationException::withMessages([
                'usuario' => ['Credenciales inválidas.'],
            ]);
        }

        $token = $user->createToken($datos['device_name'] ?? 'api-v1');

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'usuario' => $user->usuario,
                'email' => $user->email,
                'empresa_id' => $user->empresa_id,
            ],
        ]);
    }

    /**
     * Revoca el token actual.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Token revocado.']);
    }

    /**
     * Perfil del usuario autenticado.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('empresa:id,nombre');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'usuario' => $user->usuario,
            'email' => $user->email,
            'empresa_id' => $user->empresa_id,
            'empresa' => $user->empresa,
            'sucursal_id' => $user->sucursal_id,
        ]);
    }
}
