<?php

namespace App\Http\Controllers;

use App\Services\TotpService;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PerfilController extends Controller
{
    public function edit(TotpService $totp): View
    {
        $qr = null;
        $secret = session('2fa.pending_secret');
        if (is_string($secret) && $secret !== '') {
            $url = $totp->otpauthUrl(
                $secret,
                (string) (auth()->user()->usuario ?: auth()->user()->email),
                (string) config('app.name', 'POSMoon'),
            );
            $qr = (new QRCode(new QROptions([
                'outputInterface' => QRMarkupSVG::class,
                'eccLevel' => EccLevel::M,
            ])))->render($url);
        }

        return view('perfil.edit', [
            'qrSvg' => $qr,
            'pendingSecret' => $secret,
        ]);
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

    public function enableTwoFactor(TotpService $totp): RedirectResponse
    {
        $secret = $totp->secret();
        session(['2fa.pending_secret' => $secret]);

        return back()->with('ok', 'Escaneá el código con tu app de autenticación y confirmá con un código de 6 dígitos.');
    }

    public function confirmTwoFactor(Request $request, TotpService $totp): RedirectResponse
    {
        $request->validate([
            'codigo' => ['required', 'string'],
        ], [], ['codigo' => 'código']);

        $secret = session('2fa.pending_secret');
        if (! is_string($secret) || $secret === '' || ! $totp->verify($secret, (string) $request->input('codigo'))) {
            throw ValidationException::withMessages([
                'codigo' => 'El código no es válido. Generá de nuevo el QR si venció.',
            ]);
        }

        $codes = $totp->recoveryCodes();
        $request->user()->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($codes)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget('2fa.pending_secret');

        return back()->with([
            'ok' => 'Doble factor activado. Guardá los códigos de recuperación en un lugar seguro.',
            '2fa_recovery' => $codes,
        ]);
    }

    public function disableTwoFactor(Request $request, TotpService $totp): RedirectResponse
    {
        $request->validate([
            'password_actual' => ['required', 'current_password'],
            'codigo' => ['required', 'string'],
        ], [], [
            'password_actual' => 'contraseña actual',
            'codigo' => 'código',
        ]);

        $user = $request->user();
        $ok = false;
        if ($user->tieneDosFactores()) {
            try {
                $ok = $totp->verify(Crypt::decryptString((string) $user->two_factor_secret), (string) $request->input('codigo'));
            } catch (\Throwable) {
                $ok = false;
            }
        }

        if (! $ok) {
            throw ValidationException::withMessages([
                'codigo' => 'Código inválido.',
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('ok', 'Doble factor desactivado.');
    }
}
