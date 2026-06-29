<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Conexión a la base legacy (POS Moon viejo / demonew)
    |--------------------------------------------------------------------------
    |
    | Solo lectura en producción. El ETL nunca escribe en esta conexión.
    | Activar con LEGACY_IMPORT_ENABLED=true al migrar un cliente.
    |
    */
    'enabled' => env('LEGACY_IMPORT_ENABLED', false),

    'connection' => env('LEGACY_DB_CONNECTION', 'legacy'),

    'default_legacy_empresa_id' => (int) env('LEGACY_EMPRESA_ID', 1),

    /*
    |--------------------------------------------------------------------------
    | Orden de importación (clave => clase importer)
    |--------------------------------------------------------------------------
    */
    'importers' => [
        'setup' => \App\LegacyImport\Importers\SetupImporter::class,
        'categorias' => \App\LegacyImport\Importers\CategoriaImporter::class,
        'listas_precio' => \App\LegacyImport\Importers\ListaPrecioImporter::class,
        'medios_pago' => \App\LegacyImport\Importers\MedioPagoImporter::class,
        'users' => \App\LegacyImport\Importers\UserImporter::class,
        'proveedores' => \App\LegacyImport\Importers\ProveedorImporter::class,
        'productos' => \App\LegacyImport\Importers\ProductoImporter::class,
        'combos' => \App\LegacyImport\Importers\ComboImporter::class,
        'clientes' => \App\LegacyImport\Importers\ClienteImporter::class,
        'ventas' => \App\LegacyImport\Importers\VentaImporter::class,
        'comprobantes' => \App\LegacyImport\Importers\ComprobanteImporter::class,
        'cc_clientes' => \App\LegacyImport\Importers\ClienteCuentaCorrienteImporter::class,
        'cc_proveedores' => \App\LegacyImport\Importers\ProveedorCuentaCorrienteImporter::class,
        'retenciones' => \App\LegacyImport\Importers\RetencionImporter::class,
        'presupuestos' => \App\LegacyImport\Importers\PresupuestoImporter::class,
        'compras' => \App\LegacyImport\Importers\CompraImporter::class,
    ],

    'chunk_size' => (int) env('LEGACY_IMPORT_CHUNK', 200),

];
