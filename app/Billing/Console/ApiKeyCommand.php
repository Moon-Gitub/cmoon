<?php

namespace App\Billing\Console;

use App\Billing\Models\Customer;
use Illuminate\Console\Command;

class ApiKeyCommand extends Command
{
    protected $signature = 'billing:api-key {customer : id del hub o legacy:{id}}';

    protected $description = 'Genera la API key que un tenant usa en MOON_BILLING_KEY (invalida la anterior).';

    public function handle(): int
    {
        $arg = (string) $this->argument('customer');
        $c = str_starts_with($arg, 'legacy:')
            ? Customer::where('legacy_id', (int) substr($arg, 7))->first()
            : Customer::find((int) $arg);

        if (! $c) {
            $this->error('Cliente no encontrado.');

            return self::FAILURE;
        }

        $this->info("{$c->nombre}: MOON_BILLING_KEY=".$c->regenerarApiKey());

        return self::SUCCESS;
    }
}
