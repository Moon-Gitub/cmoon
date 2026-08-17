<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CajaSesion extends Model
{
    protected $table = 'caja_sesiones';

    protected $fillable = [
        'caja_id',
        'user_id',
        'monto_apertura',
        'monto_cierre_declarado',
        'monto_cierre_sistema',
        'detalle_sistema',
        'detalle_declarado',
        'detalle_diferencias',
        'apertura_siguiente_monto',
        'estado',
        'observaciones',
        'abierta_at',
        'cerrada_at',
    ];

    protected function casts(): array
    {
        return [
            'monto_apertura' => 'decimal:2',
            'monto_cierre_declarado' => 'decimal:2',
            'monto_cierre_sistema' => 'decimal:2',
            'apertura_siguiente_monto' => 'decimal:2',
            'detalle_sistema' => 'array',
            'detalle_declarado' => 'array',
            'detalle_diferencias' => 'array',
            'abierta_at' => 'datetime',
            'cerrada_at' => 'datetime',
        ];
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(CajaMovimiento::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    /**
     * Efectivo esperado en caja: apertura + ventas en efectivo + ingresos - egresos - devoluciones.
     */
    public function efectivoEsperado(): float
    {
        $efectivo = collect($this->resumenPorMedio())
            ->first(fn ($row) => ($row['tipo'] ?? '') === 'efectivo');

        return round((float) ($efectivo['esperado'] ?? 0), 2);
    }

    /**
     * Totales por medio de pago para cierre ciego (estilo demonew).
     *
     * @return list<array{
     *   medio_pago_id: int|null,
     *   clave: string,
     *   nombre: string,
     *   tipo: string,
     *   ingresos: float,
     *   egresos: float,
     *   apertura: float,
     *   esperado: float
     * }>
     */
    public function resumenPorMedio(): array
    {
        $empresaId = $this->caja?->sucursal?->empresa_id
            ?? $this->usuario?->empresa_id;

        $medios = MedioPago::query()
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderByRaw("CASE WHEN tipo = 'efectivo' THEN 0 ELSE 1 END")
            ->orderBy('nombre')
            ->get();

        $pagos = VentaPago::query()
            ->selectRaw('medio_pago_id, SUM(importe) as total')
            ->whereHas('venta', fn ($q) => $q
                ->where('caja_sesion_id', $this->id)
                ->where('estado', 'completada')
                ->where('tipo', '!=', Venta::TIPO_DEVOLUCION))
            ->groupBy('medio_pago_id')
            ->pluck('total', 'medio_pago_id');

        $devoluciones = VentaPago::query()
            ->selectRaw('medio_pago_id, SUM(importe) as total')
            ->whereHas('venta', fn ($q) => $q
                ->where('caja_sesion_id', $this->id)
                ->where('estado', 'completada')
                ->where('tipo', Venta::TIPO_DEVOLUCION))
            ->groupBy('medio_pago_id')
            ->pluck('total', 'medio_pago_id');

        $ingresosManual = (float) $this->movimientos()->where('tipo', 'ingreso')->sum('importe');
        $egresosManual = (float) $this->movimientos()->where('tipo', 'egreso')->sum('importe');

        $idsUsados = $pagos->keys()
            ->merge($devoluciones->keys())
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $medios = $medios->filter(fn (MedioPago $m) => $m->activo || in_array($m->id, $idsUsados, true))->values();

        // Si hubo pagos de un medio inactivo/borrado no listado, agregar fila genérica.
        foreach ($idsUsados as $medioId) {
            if (! $medios->contains(fn (MedioPago $m) => $m->id === $medioId)) {
                $extra = MedioPago::find($medioId);
                if ($extra) {
                    $medios->push($extra);
                }
            }
        }

        $filas = [];
        foreach ($medios as $medio) {
            $ingresos = round((float) ($pagos[$medio->id] ?? 0), 2);
            $egresos = round((float) ($devoluciones[$medio->id] ?? 0), 2);
            $apertura = 0.0;

            if ($medio->tipo === 'efectivo') {
                $apertura = round((float) $this->monto_apertura, 2);
                $ingresos = round($ingresos + $ingresosManual, 2);
                $egresos = round($egresos + $egresosManual, 2);
            }

            $esperado = round($apertura + $ingresos - $egresos, 2);

            $filas[] = [
                'medio_pago_id' => $medio->id,
                'clave' => (string) $medio->id,
                'nombre' => $medio->nombre,
                'tipo' => $medio->tipo,
                'ingresos' => $ingresos,
                'egresos' => $egresos,
                'apertura' => $apertura,
                'esperado' => $esperado,
            ];
        }

        return $filas;
    }

    /**
     * @param  array<int|string, float|string>  $declaradoPorMedio  key = medio_pago_id
     * @return array{sistema: list<array>, declarado: list<array>, diferencias: list<array>, efectivo_sistema: float, efectivo_declarado: float}
     */
    public function armarCierreCiego(array $declaradoPorMedio): array
    {
        $sistema = $this->resumenPorMedio();
        $declarado = [];
        $diferencias = [];
        $efectivoSistema = 0.0;
        $efectivoDeclarado = 0.0;

        foreach ($sistema as $fila) {
            $id = (int) $fila['medio_pago_id'];
            $contado = round((float) ($declaradoPorMedio[$id] ?? $declaradoPorMedio[(string) $id] ?? 0), 2);
            // Igual que demonew: diferencia = esperado - contado (positivo = faltante).
            $dif = round((float) $fila['esperado'] - $contado, 2);

            $declarado[] = [
                'medio_pago_id' => $id,
                'nombre' => $fila['nombre'],
                'tipo' => $fila['tipo'],
                'importe' => $contado,
            ];

            if (abs($dif) >= 0.01) {
                $diferencias[] = [
                    'medio_pago_id' => $id,
                    'nombre' => $fila['nombre'],
                    'tipo' => $fila['tipo'],
                    'importe' => $dif,
                ];
            }

            if ($fila['tipo'] === 'efectivo') {
                $efectivoSistema = (float) $fila['esperado'];
                $efectivoDeclarado = $contado;
            }
        }

        return [
            'sistema' => $sistema,
            'declarado' => $declarado,
            'diferencias' => $diferencias,
            'efectivo_sistema' => $efectivoSistema,
            'efectivo_declarado' => $efectivoDeclarado,
        ];
    }

    public static function ultimaAperturaSugerida(int $cajaId): float
    {
        $prev = static::query()
            ->where('caja_id', $cajaId)
            ->where('estado', 'cerrada')
            ->whereNotNull('apertura_siguiente_monto')
            ->latest('cerrada_at')
            ->value('apertura_siguiente_monto');

        return round((float) ($prev ?? 0), 2);
    }
}
