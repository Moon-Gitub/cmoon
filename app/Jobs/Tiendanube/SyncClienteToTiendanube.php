<?php

namespace App\Jobs\Tiendanube;

use App\Models\Cliente;
use App\Models\TiendanubeIntegracion;
use App\Models\TiendanubeLog;
use App\Services\TiendanubeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncClienteToTiendanube implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public TiendanubeIntegracion $integracion,
        public Cliente $cliente,
    ) {}

    public function handle(TiendanubeService $tiendanube): void
    {
        if (! $this->integracion->activo || ! $this->integracion->sync_customers) {
            return;
        }

        $service = $tiendanube->forIntegracion($this->integracion);

        // Buscar cliente existente en Tiendanube por email
        $existingCustomer = null;

        if ($this->cliente->email) {
            $customers = $service->getCustomers(['q' => $this->cliente->email]);

            foreach ($customers as $customer) {
                if (strtolower($customer['email'] ?? '') === strtolower($this->cliente->email)) {
                    $existingCustomer = $customer;
                    break;
                }
            }
        }

        $customerData = $this->buildCustomerData();

        try {
            if ($existingCustomer) {
                // Actualizar cliente existente
                $service->updateCustomer($existingCustomer['id'], $customerData);

                TiendanubeLog::registrar(
                    $this->integracion,
                    'customer_sync',
                    'push',
                    'customer',
                    $this->cliente->id,
                    mensaje: "Cliente actualizado en Tiendanube: {$this->cliente->nombre}",
                );
            } else {
                // Crear nuevo cliente
                $result = $service->createCustomer($customerData);

                TiendanubeLog::registrar(
                    $this->integracion,
                    'customer_sync',
                    'push',
                    'customer',
                    $this->cliente->id,
                    response: ['tn_customer_id' => $result['id'] ?? null],
                    mensaje: "Cliente creado en Tiendanube: {$this->cliente->nombre}",
                );
            }
        } catch (\Throwable $e) {
            TiendanubeLog::registrar(
                $this->integracion,
                'customer_sync',
                'push',
                'customer',
                $this->cliente->id,
                status: 'error',
                mensaje: "Error sincronizando cliente {$this->cliente->nombre}: {$e->getMessage()}",
            );

            throw $e;
        }
    }

    private function buildCustomerData(): array
    {
        $data = [
            'name' => $this->cliente->nombre,
            'email' => $this->cliente->email,
        ];

        if ($this->cliente->telefono) {
            $data['phone'] = $this->cliente->telefono;
        }

        if ($this->cliente->documento) {
            $data['identification'] = $this->cliente->documento;
        }

        if ($this->cliente->direccion) {
            $data['default_address'] = [
                'address' => $this->cliente->direccion,
                'city' => $this->cliente->ciudad ?? null,
                'province' => $this->cliente->provincia ?? null,
                'zipcode' => $this->cliente->codigo_postal ?? null,
                'country' => 'AR',
            ];
        }

        return $data;
    }
}
