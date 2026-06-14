<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Services\StockService;
use Illuminate\Database\Seeder;

/**
 * 20 productos típicos de almacén/kiosco argentino, con stock inicial.
 * Idempotente: se puede ejecutar varias veces (updateOrCreate por código).
 */
class ProductosEjemploSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::first();
        if (! $empresa) {
            $this->command?->warn('No hay empresa. Ejecute DatosInicialesSeeder primero.');

            return;
        }

        $sucursal = Sucursal::where('empresa_id', $empresa->id)->where('activa', true)->first();
        if (! $sucursal) {
            $this->command?->warn('No hay sucursal activa.');

            return;
        }

        $categorias = [
            'Bebidas' => Categoria::firstOrCreate(
                ['empresa_id' => $empresa->id, 'nombre' => 'Bebidas'],
                ['activa' => true]
            ),
            'Almacén' => Categoria::firstOrCreate(
                ['empresa_id' => $empresa->id, 'nombre' => 'Almacén'],
                ['activa' => true]
            ),
            'Lácteos' => Categoria::firstOrCreate(
                ['empresa_id' => $empresa->id, 'nombre' => 'Lácteos'],
                ['activa' => true]
            ),
            'Fiambres' => Categoria::firstOrCreate(
                ['empresa_id' => $empresa->id, 'nombre' => 'Fiambres'],
                ['activa' => true]
            ),
            'Panadería' => Categoria::firstOrCreate(
                ['empresa_id' => $empresa->id, 'nombre' => 'Panadería'],
                ['activa' => true]
            ),
        ];

        $productos = [
            // Bebidas
            ['7790895000123', 'Coca Cola 2,25 L', 'Bebidas', 4500, 3200, false, 'UN', 48],
            ['7790742000011', 'Fernet Branca 750 ml', 'Bebidas', 12500, 8900, false, 'UN', 24],
            ['7790123456011', 'Quilmes rubia lata 473 ml', 'Bebidas', 1900, 1200, false, 'UN', 120],
            ['7790123456028', 'Agua mineral Benedictino 2 L', 'Bebidas', 2200, 1400, false, 'UN', 60],
            ['7790123456035', 'Cepita naranja 1 L', 'Bebidas', 2800, 1800, false, 'UN', 36],
            // Almacén
            ['7790123456101', 'Yerba mate Taragüí 1 kg', 'Almacén', 4200, 2900, false, 'UN', 30],
            ['7790123456102', 'Arroz largo fino 1 kg', 'Almacén', 2100, 1400, false, 'UN', 50],
            ['7790123456103', 'Fideos spaghetti Marolio 500 g', 'Almacén', 1800, 1100, false, 'UN', 40],
            ['7790123456104', 'Aceite de girasol Natura 900 ml', 'Almacén', 3500, 2400, false, 'UN', 28],
            ['7790123456105', 'Azúcar Ledesma 1 kg', 'Almacén', 1900, 1200, false, 'UN', 45],
            ['7790123456106', 'Dulce de leche La Serenisima 400 g', 'Almacén', 3200, 2100, false, 'UN', 32],
            ['7790123456107', 'Galletitas Oreo 118 g', 'Almacén', 2900, 1900, false, 'UN', 36],
            ['7790123456108', 'Papas fritas Lays clásicas 85 g', 'Almacén', 2600, 1700, false, 'UN', 42],
            ['7790123456109', 'Café Nescafé Clásico 170 g', 'Almacén', 6800, 4800, false, 'UN', 18],
            // Lácteos
            ['7790123456201', 'Leche entera La Serenisima 1 L', 'Lácteos', 2400, 1600, false, 'UN', 72],
            ['7790123456202', 'Huevos blancos docena', 'Lácteos', 3800, 2600, false, 'DOC', 24],
            // Panadería
            ['7790123456301', 'Pan lactal Bimbo 460 g', 'Panadería', 4200, 2800, false, 'UN', 20],
            // Fiambres / balanza (PLU 5 dígitos para etiquetas EAN balanza)
            ['00101', 'Queso cremoso (kg)', 'Fiambres', 8500, 6200, true, 'KG', 15],
            ['00102', 'Jamón cocido (kg)', 'Fiambres', 7200, 5100, true, 'KG', 12],
            ['00103', 'Salame tandilero (kg)', 'Fiambres', 9800, 7000, true, 'KG', 8],
        ];

        $stockService = app(StockService::class);

        foreach ($productos as [$codigo, $nombre, $catNombre, $precioVenta, $precioCompra, $pesable, $unidad, $stockInicial]) {
            $producto = Producto::updateOrCreate(
                ['empresa_id' => $empresa->id, 'codigo' => $codigo],
                [
                    'categoria_id' => $categorias[$catNombre]->id,
                    'nombre' => $nombre,
                    'unidad' => $unidad,
                    'pesable' => $pesable,
                    'precio_compra' => $precioCompra,
                    'precio_venta' => $precioVenta,
                    'alicuota_iva' => 21,
                    'stock_minimo' => $pesable ? 2 : 5,
                    'activo' => true,
                ]
            );

            $stockService->ajustarA(
                $producto,
                $sucursal->id,
                (float) $stockInicial,
                'Stock inicial — productos ejemplo Argentina',
            );
        }

        $this->command?->info('Cargados '.count($productos).' productos de ejemplo con stock.');
    }
}
