<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_devuelve_json_minimo(): void
    {
        $this->getJson('/up')
            ->assertOk()
            ->assertExactJson(['ok' => true])
            ->assertHeaderMissing('X-Powered-By');
    }

    public function test_login_envia_csp_y_permissions_policy(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('Content-Security-Policy')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_login_sin_2fa_entra_al_dashboard(): void
    {
        $user = User::factory()->create([
            'usuario' => 'cajero',
            'activo' => true,
            'password' => 'password',
        ]);

        $this->post('/login', [
            'usuario' => 'cajero',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_con_2fa_pide_codigo(): void
    {
        $totp = new TotpService;
        $secret = $totp->secret();
        $user = User::factory()->create([
            'usuario' => 'admin2fa',
            'activo' => true,
            'password' => 'password',
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post('/login', [
            'usuario' => 'admin2fa',
            'password' => 'password',
        ])->assertRedirect(route('login.2fa'));

        $this->assertGuest();

        $this->post('/login/2fa', [
            'codigo' => $totp->at($secret, intdiv(time(), 30)),
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }
}
