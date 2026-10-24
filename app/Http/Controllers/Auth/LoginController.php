<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'usuario' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [], [
            'usuario' => 'usuario',
            'password' => 'contraseña',
        ]);

        $throttleKey = Str::lower($request->input('usuario')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $segundos = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'usuario' => "Demasiados intentos. Probá de nuevo en {$segundos} segundos.",
            ]);
        }

        $campo = filter_var($request->input('usuario'), FILTER_VALIDATE_EMAIL) ? 'email' : 'usuario';

        $credenciales = [
            $campo => $request->input('usuario'),
            'password' => $request->input('password'),
        ];

        if (! Auth::attempt($credenciales, $request->boolean('recordarme'))) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'usuario' => 'Usuario o contraseña incorrectos.',
            ]);
        }

        $user = $request->user();

        if (! $user->activo) {
            Auth::logout();

            throw ValidationException::withMessages([
                'usuario' => 'El usuario está desactivado. Contactá al administrador.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        if ($user->tieneDosFactores()) {
            $recordarme = $request->boolean('recordarme');
            Auth::logout();
            $request->session()->put('login.2fa', [
                'user_id' => $user->id,
                'remember' => $recordarme,
                'expires_at' => now()->addMinutes(5)->getTimestamp(),
            ]);

            return redirect()->route('login.2fa');
        }

        $request->session()->regenerate();
        $user->forceFill(['ultimo_acceso_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function showTwoFactor(): View|RedirectResponse
    {
        if (! $this->pendingUser()) {
            return redirect()->route('login');
        }

        return view('auth.two-factor');
    }

    public function challengeTwoFactor(Request $request, TotpService $totp): RedirectResponse
    {
        $pending = $this->pendingUser();
        if (! $pending) {
            return redirect()->route('login');
        }

        $request->validate([
            'codigo' => ['required', 'string'],
        ], [], ['codigo' => 'código']);

        $throttleKey = '2fa|'.$pending->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'codigo' => 'Demasiados intentos. Volvé a ingresar usuario y contraseña.',
            ]);
        }

        $codigo = (string) $request->input('codigo');
        $ok = false;

        try {
            $secret = Crypt::decryptString((string) $pending->two_factor_secret);
            $ok = $totp->verify($secret, $codigo);
        } catch (\Throwable) {
            $ok = false;
        }

        if (! $ok) {
            $ok = $this->consumeRecoveryCode($pending, $codigo);
        }

        if (! $ok) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'codigo' => 'Código inválido.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $remember = (bool) data_get($request->session()->get('login.2fa'), 'remember', false);
        $request->session()->forget('login.2fa');

        Auth::login($pending, $remember);
        $request->session()->regenerate();
        $pending->forceFill(['ultimo_acceso_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function pendingUser(): ?User
    {
        $data = session('login.2fa');
        if (! is_array($data) || empty($data['user_id'])) {
            return null;
        }

        if (($data['expires_at'] ?? 0) < now()->getTimestamp()) {
            session()->forget('login.2fa');

            return null;
        }

        $user = User::query()->find($data['user_id']);

        return $user && $user->activo ? $user : null;
    }

    private function consumeRecoveryCode(User $user, string $codigo): bool
    {
        $codigo = strtolower(preg_replace('/\s+/', '', $codigo) ?? '');
        if ($codigo === '' || blank($user->two_factor_recovery_codes)) {
            return false;
        }

        try {
            $codes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return false;
        }

        if (! is_array($codes)) {
            return false;
        }

        $restantes = [];
        $usado = false;
        foreach ($codes as $guardado) {
            if (! $usado && hash_equals((string) $guardado, $codigo)) {
                $usado = true;

                continue;
            }
            $restantes[] = $guardado;
        }

        if (! $usado) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode(array_values($restantes))),
        ])->save();

        return true;
    }
}
