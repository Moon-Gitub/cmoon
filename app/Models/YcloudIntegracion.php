<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YcloudIntegracion extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'ycloud_integraciones';

    protected $fillable = [
        'empresa_id',
        'api_key',
        'phone_from',
        'waba_id',
        'webhook_secret',
        'catalog_template',
        'bot_activo',
        'auto_reply',
        'activo',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'bot_activo' => 'boolean',
            'auto_reply' => 'boolean',
            'activo' => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }

    public function conversaciones(): HasMany
    {
        return $this->hasMany(YcloudConversacion::class, 'integracion_id');
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(YcloudMensaje::class, 'integracion_id');
    }

    public function phoneE164(): string
    {
        $phone = preg_replace('/\s+/', '', (string) $this->phone_from) ?? '';

        if ($phone !== '' && ! str_starts_with($phone, '+')) {
            $phone = '+'.$phone;
        }

        return $phone;
    }

    public function resolvedApiKey(): string
    {
        return (string) ($this->api_key ?: config('ycloud.api_key'));
    }
}
