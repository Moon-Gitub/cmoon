<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class N8nIntegracion extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'n8n_integraciones';

    protected $fillable = [
        'empresa_id',
        'base_url',
        'header_name',
        'header_value',
        'inbound_secret',
        'webhooks',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'header_value' => 'encrypted',
            'webhooks' => 'array',
            'activo' => 'boolean',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(N8nLog::class, 'integracion_id');
    }

    public function urlPara(string $evento): ?string
    {
        $url = trim((string) data_get($this->webhooks, $evento, ''));

        return $url !== '' ? $url : null;
    }
}
