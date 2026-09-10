<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CatalogoCategoriaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\CuentaCorrienteController;
use App\Http\Controllers\EmpresasAdminController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\PresupuestoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositoController;
use App\Http\Controllers\EmisorController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\FacturacionController;
use App\Http\Controllers\FacturaRecurrenteController;
use App\Http\Controllers\InformeController;
use App\Http\Controllers\ListaPrecioController;
use App\Http\Controllers\MedioPagoController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\RetencionController;
use App\Http\Controllers\RutaController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\TiendanubeController;
use App\Http\Controllers\ShopifyController;
use App\Http\Controllers\YcloudController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\TiendanubeWebhookController;
use App\Http\Controllers\ShopifyWebhookController;
use App\Http\Controllers\YcloudWebhookController;
use App\Http\Controllers\N8nController;
use App\Http\Controllers\N8nWebhookController;
use App\Http\Controllers\AsistenteController;
use App\Http\Controllers\AdminIaController;
use App\Http\Controllers\BancoController;
use App\Http\Controllers\BusquedaController;
use App\Http\Controllers\ChequeController;
use App\Http\Controllers\CobranzaController;
use App\Http\Controllers\CrmController;
use App\Http\Controllers\DescargasController;
use App\Http\Controllers\IaOperativaController;
use App\Http\Controllers\MercadoPagoVisorController;
use App\Http\Controllers\OrdenCompraController;
use App\Http\Controllers\RemitoController;
use App\Http\Controllers\VentaController;
use App\Services\Afip\AfipSoap;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Webhook Tiendanube (sin auth, validado por HMAC)
Route::post('/webhooks/tiendanube', [TiendanubeWebhookController::class, 'handle'])
    ->name('tiendanube.webhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Webhook Shopify (sin auth, validado por X-Shopify-Hmac-Sha256)
Route::post('/webhooks/shopify', [ShopifyWebhookController::class, 'handle'])
    ->name('shopify.webhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/webhooks/ycloud', [YcloudWebhookController::class, 'handle'])
    ->name('ycloud.webhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/webhooks/n8n', [N8nWebhookController::class, 'handle'])
    ->name('n8n.webhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Catálogo PDF por categoría (link público para compartir, estilo demonew)
Route::get('/catalogo/{token}/categoria/{categoria}', [CatalogoCategoriaController::class, 'publico'])
    ->whereNumber('categoria')
    ->name('catalogo.categoria.publico');

// Diagnóstico de salida a AFIP (token = sha256(APP_KEY) primeros 16 hex)
Route::get('/_diag/afip', function () {
    $esperado = substr(hash('sha256', (string) config('app.key')), 0, 16);
    abort_unless(hash_equals($esperado, (string) request('t')), 404);

    $certs = [];
    foreach (\App\Models\Emisor::query()->get(['id', 'cuit', 'entorno', 'certificado_path', 'clave_privada_path']) as $e) {
        $certs[] = [
            'id' => $e->id,
            'cuit' => $e->cuit,
            'entorno' => $e->entorno,
            'cert_exists' => $e->certificado_path && Storage::exists($e->certificado_path),
            'key_exists' => $e->clave_privada_path && Storage::exists($e->clave_privada_path),
        ];
    }

    return response()->json([
        'conectividad' => AfipSoap::diagnosticarConectividad(),
        'emisores' => $certs,
        'openssl' => extension_loaded('openssl'),
        'soap' => extension_loaded('soap'),
    ]);
})->name('diag.afip');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/apps/descargar', [DescargasController::class, 'index'])->name('descargas.index');
    Route::get('/apps/descargar/{platform}', [DescargasController::class, 'download'])
        ->whereIn('platform', ['windows', 'linux', 'android'])
        ->name('descargas.download');

    Route::prefix('busqueda')->name('busqueda.')->group(function () {
        Route::get('/productos', [BusquedaController::class, 'productos'])->name('productos');
        Route::get('/clientes', [BusquedaController::class, 'clientes'])->name('clientes');
        Route::get('/proveedores', [BusquedaController::class, 'proveedores'])->name('proveedores');
        Route::get('/ventas', [BusquedaController::class, 'ventas'])->name('ventas');
        Route::get('/comprobantes', [BusquedaController::class, 'comprobantes'])->name('comprobantes');
        Route::get('/usuarios', [BusquedaController::class, 'usuarios'])->name('usuarios');
    });

    Route::get('/asistente', [AsistenteController::class, 'index'])->name('asistente.index');
    Route::post('/asistente/preguntar', [AsistenteController::class, 'preguntar'])->name('asistente.preguntar');
    Route::post('/asistente/abono', [AsistenteController::class, 'solicitarAbono'])->name('asistente.abono');
    Route::post('/asistente/comprar', [AsistenteController::class, 'comprarPaquete'])->name('asistente.comprar');

    Route::middleware('superadmin')->prefix('admin/ia')->name('admin.ia.')->group(function () {
        Route::get('/', [AdminIaController::class, 'index'])->name('index');
        Route::post('/config', [AdminIaController::class, 'guardarConfig'])->name('config');
        Route::post('/acreditar', [AdminIaController::class, 'acreditar'])->name('acreditar');
        Route::post('/compras/{compra}/aprobar', [AdminIaController::class, 'aprobarCompra'])->name('compras.aprobar');
        Route::post('/compras/{compra}/rechazar', [AdminIaController::class, 'rechazarCompra'])->name('compras.rechazar');
        Route::post('/paquetes', [AdminIaController::class, 'guardarPaquete'])->name('paquetes');
        Route::post('/superadmin', [AdminIaController::class, 'marcarSuperadmin'])->name('superadmin');
    });

    Route::post('/ia/productos/sugerir', [IaOperativaController::class, 'sugerirProducto'])->name('ia.productos.sugerir');
    Route::post('/ia/productos/sugerir-canales', [IaOperativaController::class, 'sugerirCanales'])->name('ia.productos.canales');
    Route::post('/ia/productos/aplicar-canales', [IaOperativaController::class, 'aplicarCanalesSugeridos'])->name('ia.productos.canales.aplicar');
    Route::post('/ia/afip/explicar', [IaOperativaController::class, 'explicarAfip'])->name('ia.afip.explicar');

    Route::get('/perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
    Route::put('/perfil/password', [PerfilController::class, 'updatePassword'])->name('perfil.password');

    Route::middleware('permission:usuarios.ver')->group(function () {
        Route::resource('usuarios', UsuarioController::class)->except('show');
    });

    Route::middleware('permission:sucursales.ver')->group(function () {
        Route::resource('sucursales', SucursalController::class)
            ->except('show')
            ->parameters(['sucursales' => 'sucursal']);
    });

    Route::middleware('permission:depositos.ver')->group(function () {
        Route::get('/depositos', [DepositoController::class, 'index'])->name('depositos.index');
        Route::post('/depositos', [DepositoController::class, 'store'])->name('depositos.store');
        Route::put('/depositos/{deposito}', [DepositoController::class, 'update'])->name('depositos.update');
    });

    Route::middleware('permission:empresa.ver')->group(function () {
        Route::get('/empresa', [EmpresaController::class, 'edit'])->name('empresa.edit');
        Route::put('/empresa', [EmpresaController::class, 'update'])->name('empresa.update');
    });

    Route::middleware('permission:productos.ver')->group(function () {
        Route::get('/productos/importar', [ProductoController::class, 'importarForm'])->name('productos.importar');
        Route::post('/productos/importar', [ProductoController::class, 'importar'])->name('productos.importar.procesar');
        Route::get('/productos/plantilla-csv', [ProductoController::class, 'plantillaCsv'])->name('productos.plantilla');
        Route::get('/productos/precio-masivo', [ProductoController::class, 'precioMasivoForm'])->name('productos.precio-masivo');
        Route::post('/productos/precio-masivo', [ProductoController::class, 'precioMasivo'])->name('productos.precio-masivo.aplicar');
        Route::get('/productos/canales', [ProductoController::class, 'canales'])->name('productos.canales');
        Route::post('/productos/canales', [ProductoController::class, 'canalesAplicar'])->name('productos.canales.aplicar');
        Route::get('/productos/{producto}/auditoria', [ProductoController::class, 'auditoria'])->name('productos.auditoria');
        Route::get('/productos/{producto}/stock', [ProductoController::class, 'stock'])->name('productos.stock');
        Route::post('/productos/{producto}/stock', [ProductoController::class, 'ajustarStock'])->name('productos.stock.ajustar');
        Route::get('/productos/{producto}/lotes', [ProductoController::class, 'lotes'])->name('productos.lotes');
        Route::post('/productos/{producto}/lotes', [ProductoController::class, 'storeLote'])->name('productos.lotes.store');
        Route::get('/productos/{producto}/combo', [ProductoController::class, 'combo'])->name('productos.combo');
        Route::post('/productos/{producto}/combo', [ProductoController::class, 'agregarComponente'])->name('productos.combo.agregar');
        Route::delete('/productos/{producto}/combo/{componente}', [ProductoController::class, 'quitarComponente'])->name('productos.combo.quitar');
        Route::resource('productos', ProductoController::class)->except('show');
    });

    Route::middleware('permission:categorias.ver')->group(function () {
        Route::resource('categorias', CategoriaController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/categorias/{categoria}/pdf', [CatalogoCategoriaController::class, 'pdf'])
            ->name('categorias.pdf');
    });

    Route::middleware('permission:listas-precio.ver')->group(function () {
        Route::resource('listas-precio', ListaPrecioController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['listas-precio' => 'listaPrecio']);
    });

    Route::middleware('permission:clientes.ver')->group(function () {
        Route::post('/clientes/padron-afip', [ClienteController::class, 'consultarPadron'])->name('clientes.padron');
        Route::resource('clientes', ClienteController::class);
    });

    Route::middleware('permission:rutas.gestionar')->group(function () {
        Route::get('/rutas', [RutaController::class, 'index'])->name('rutas.index');
        Route::post('/rutas', [RutaController::class, 'store'])->name('rutas.store');
        Route::put('/rutas/{ruta}', [RutaController::class, 'update'])->name('rutas.update');
        Route::delete('/rutas/{ruta}', [RutaController::class, 'destroy'])->name('rutas.destroy');
    });

    Route::middleware('permission:proveedores.ver')->group(function () {
        Route::resource('proveedores', ProveedorController::class)
            ->except('show')
            ->parameters(['proveedores' => 'proveedor']);
    });

    Route::middleware('permission:cuentas.ver')->group(function () {
        Route::get('/clientes/{cliente}/cuenta', [CuentaCorrienteController::class, 'cliente'])->name('clientes.cuenta');
        Route::post('/clientes/{cliente}/cuenta', [CuentaCorrienteController::class, 'registrarCliente'])->name('clientes.cuenta.registrar');
        Route::get('/proveedores/{proveedor}/cuenta', [CuentaCorrienteController::class, 'proveedor'])->name('proveedores.cuenta');
        Route::post('/proveedores/{proveedor}/cuenta', [CuentaCorrienteController::class, 'registrarProveedor'])->name('proveedores.cuenta.registrar');
        Route::post('/proveedores/{proveedor}/cuenta/factura', [CuentaCorrienteController::class, 'facturaProveedor'])->name('proveedores.cuenta.factura');
        Route::post('/proveedores/{proveedor}/cuenta/pago', [CuentaCorrienteController::class, 'pagoProveedor'])->name('proveedores.cuenta.pago');
    });

    Route::middleware('permission:pos.vender')->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos');
        Route::get('/pos/catalogo', [PosController::class, 'catalogo'])->name('pos.catalogo');
        Route::post('/pos/caja', [PosController::class, 'seleccionarCaja'])->name('pos.caja');
        Route::post('/pos/clientes', [PosController::class, 'crearCliente'])->name('pos.clientes.store');
        Route::post('/pos/ventas', [PosController::class, 'guardar'])->name('pos.guardar');
        Route::post('/pos/ventas/{venta}/facturar', [PosController::class, 'facturar'])
            ->middleware('permission:facturacion.emitir')
            ->name('pos.facturar');
        Route::post('/pos/qr/crear', [PosController::class, 'crearQrMercadoPago'])->name('pos.qr.crear');
        Route::get('/pos/qr/estado', [PosController::class, 'consultarQrMercadoPago'])->name('pos.qr.estado');
    });

    Route::middleware('permission:ventas.ver')->group(function () {
        Route::get('/ventas', [VentaController::class, 'index'])->name('ventas.index');
        Route::get('/ventas/{venta}', [VentaController::class, 'show'])->name('ventas.show');
        Route::post('/ventas/{venta}/anular', [VentaController::class, 'anular'])->name('ventas.anular');
        Route::post('/ventas/{venta}/fecha', [VentaController::class, 'editarFecha'])->name('ventas.editar-fecha');
        Route::get('/ventas/{venta}/ticket', [VentaController::class, 'ticket'])->name('ventas.ticket');
    });

    Route::middleware('permission:cajas.ver')->group(function () {
        Route::get('/cajas', [CajaController::class, 'index'])->name('cajas.index');
        Route::post('/cajas', [CajaController::class, 'store'])->name('cajas.store');
        Route::delete('/cajas/{caja}', [CajaController::class, 'destroy'])->name('cajas.destroy');
        Route::post('/cajas/{caja}/abrir', [CajaController::class, 'abrir'])->name('cajas.abrir');
        Route::get('/cajas/sesiones/{sesion}', [CajaController::class, 'sesion'])->name('cajas.sesion');
        Route::post('/cajas/sesiones/{sesion}/cerrar', [CajaController::class, 'cerrar'])->name('cajas.cerrar');
        Route::post('/cajas/sesiones/{sesion}/movimiento', [CajaController::class, 'movimiento'])->name('cajas.movimiento');
    });

    Route::middleware('permission:medios-pago.ver')->group(function () {
        Route::resource('medios-pago', MedioPagoController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['medios-pago' => 'medioPago']);
    });

    Route::middleware('permission:facturacion.ver')->group(function () {
        Route::get('/facturacion', [FacturacionController::class, 'index'])->name('facturacion.index');
        Route::get('/facturacion/manual', [FacturacionController::class, 'manualForm'])->name('facturacion.manual');
        Route::post('/facturacion/manual', [FacturacionController::class, 'manualStore'])->name('facturacion.manual.store');
        Route::get('/facturacion/{comprobante}', [FacturacionController::class, 'show'])
            ->whereNumber('comprobante')->name('facturacion.show');
        Route::get('/facturacion/{comprobante}/ticket', [FacturacionController::class, 'ticket'])
            ->whereNumber('comprobante')->name('facturacion.ticket');
        Route::post('/facturacion/{comprobante}/email', [FacturacionController::class, 'enviarEmail'])
            ->whereNumber('comprobante')->name('facturacion.enviar-email');
        Route::get('/facturacion/{comprobante}/nota', [FacturacionController::class, 'notaForm'])
            ->whereNumber('comprobante')->name('facturacion.nota');
        Route::post('/facturacion/{comprobante}/nota', [FacturacionController::class, 'notaStore'])
            ->whereNumber('comprobante')->name('facturacion.nota.store');
        Route::post('/ventas/{venta}/facturar', [FacturacionController::class, 'facturar'])->name('ventas.facturar');
        Route::post('/facturacion/facturar-lote', [FacturacionController::class, 'facturarLote'])->name('facturacion.facturar-lote');
        Route::post('/facturacion/{comprobante}/reintentar', [FacturacionController::class, 'reintentar'])->name('facturacion.reintentar');

        Route::get('/facturas-recurrentes', [FacturaRecurrenteController::class, 'index'])->name('facturas-recurrentes.index');
        Route::post('/facturas-recurrentes', [FacturaRecurrenteController::class, 'store'])
            ->middleware('permission:facturacion.emitir')
            ->name('facturas-recurrentes.store');
        Route::post('/facturas-recurrentes/{facturaRecurrente}/toggle', [FacturaRecurrenteController::class, 'toggle'])
            ->whereNumber('facturaRecurrente')
            ->middleware('permission:facturacion.emitir')
            ->name('facturas-recurrentes.toggle');
        Route::delete('/facturas-recurrentes/{facturaRecurrente}', [FacturaRecurrenteController::class, 'destroy'])
            ->whereNumber('facturaRecurrente')
            ->middleware('permission:facturacion.emitir')
            ->name('facturas-recurrentes.destroy');
    });

    Route::middleware('permission:emisores.ver')->group(function () {
        Route::get('/emisores', [EmisorController::class, 'index'])->name('emisores.index');
        Route::post('/emisores', [EmisorController::class, 'store'])->name('emisores.store');
        Route::put('/emisores/{emisor}', [EmisorController::class, 'update'])->name('emisores.update');
        Route::post('/emisores/{emisor}/certificado', [EmisorController::class, 'certificado'])->name('emisores.certificado');
        Route::post('/emisores/{emisor}/puntos-venta', [EmisorController::class, 'puntoVenta'])->name('emisores.punto-venta');
        Route::delete('/emisores/{emisor}/puntos-venta/{puntoVenta}', [EmisorController::class, 'eliminarPuntoVenta'])->name('emisores.punto-venta.eliminar');
        Route::delete('/emisores/{emisor}', [EmisorController::class, 'destroy'])->name('emisores.destroy');
    });

    Route::middleware('permission:informes.ver')->prefix('informes')->name('informes.')->group(function () {
        Route::get('/', [InformeController::class, 'index'])->name('index');
        Route::get('/ventas', [InformeController::class, 'ventas'])->name('ventas');
        Route::get('/productos', [InformeController::class, 'productos'])->name('productos');
        Route::get('/rentabilidad', [InformeController::class, 'rentabilidad'])->name('rentabilidad');
        Route::get('/categorias', [InformeController::class, 'categorias'])->name('categorias');
        Route::get('/pedidos', [InformeController::class, 'pedidos'])->name('pedidos');
        Route::get('/stock', [InformeController::class, 'stock'])->name('stock');
        Route::get('/libro-iva', [InformeController::class, 'libroIva'])->name('libro-iva');
        Route::get('/libro-iva-compras', [InformeController::class, 'libroIvaCompras'])->name('libro-iva-compras');
        Route::get('/citi-ventas', [InformeController::class, 'citiVentas'])->name('citi-ventas');
        Route::get('/cuentas-corrientes', [InformeController::class, 'cuentasCorrientes'])->name('cuentas-corrientes');
        Route::get('/cajas', [InformeController::class, 'cajas'])->name('cajas');
    });

    Route::middleware('permission:cobranzas.ver')->group(function () {
        Route::get('/cobranzas', [CobranzaController::class, 'index'])->name('cobranzas.index');
    });

    Route::middleware('permission:cheques.ver')->group(function () {
        Route::get('/cheques', [ChequeController::class, 'index'])->name('cheques.index');
        Route::get('/cheques/nuevo', [ChequeController::class, 'create'])->name('cheques.create');
        Route::post('/cheques', [ChequeController::class, 'store'])->name('cheques.store');
        Route::post('/cheques/{cheque}/estado', [ChequeController::class, 'cambiarEstado'])->name('cheques.cambiar-estado');
    });

    Route::middleware('permission:ordenes-compra.ver')->group(function () {
        Route::get('/ordenes-compra', [OrdenCompraController::class, 'index'])->name('ordenes-compra.index');
        Route::get('/ordenes-compra/nueva', [OrdenCompraController::class, 'create'])->name('ordenes-compra.create');
        Route::post('/ordenes-compra', [OrdenCompraController::class, 'store'])->name('ordenes-compra.store');
        Route::post('/ordenes-compra/ocr', [OrdenCompraController::class, 'ocr'])->name('ordenes-compra.ocr');
        Route::get('/ordenes-compra/{ordenCompra}', [OrdenCompraController::class, 'show'])->whereNumber('ordenCompra')->name('ordenes-compra.show');
        Route::post('/ordenes-compra/{ordenCompra}/recibir', [OrdenCompraController::class, 'recibir'])->name('ordenes-compra.recibir');
    });

    Route::middleware('permission:remitos.ver')->group(function () {
        Route::get('/remitos', [RemitoController::class, 'index'])->name('remitos.index');
        Route::get('/remitos/nuevo', [RemitoController::class, 'createFromPresupuesto'])->name('remitos.create');
        Route::post('/remitos', [RemitoController::class, 'store'])->name('remitos.store');
        Route::get('/remitos/{remito}', [RemitoController::class, 'show'])->whereNumber('remito')->name('remitos.show');
        Route::post('/remitos/{remito}/entregar', [RemitoController::class, 'entregar'])->name('remitos.entregar');
        Route::post('/remitos/{remito}/facturar', [RemitoController::class, 'facturar'])->name('remitos.facturar');
    });

    Route::middleware('permission:bancos.ver')->group(function () {
        Route::get('/bancos', [BancoController::class, 'index'])->name('bancos.index');
        Route::post('/bancos', [BancoController::class, 'store'])->name('bancos.store');
        Route::get('/bancos/{cuentaBancaria}', [BancoController::class, 'show'])->whereNumber('cuentaBancaria')->name('bancos.show');
        Route::post('/bancos/{cuentaBancaria}/importar', [BancoController::class, 'importCsv'])->name('bancos.importar');
        Route::post('/bancos/{cuentaBancaria}/movimientos/{movimiento}/conciliar', [BancoController::class, 'conciliar'])
            ->whereNumber(['cuentaBancaria', 'movimiento'])
            ->name('bancos.conciliar');
        Route::post('/bancos/{cuentaBancaria}/movimientos/{movimiento}/desconciliar', [BancoController::class, 'desconciliar'])
            ->whereNumber(['cuentaBancaria', 'movimiento'])
            ->name('bancos.desconciliar');
    });

    Route::middleware('permission:crm.ver')->group(function () {
        Route::get('/crm', [CrmController::class, 'index'])->name('crm.index');
        Route::post('/crm', [CrmController::class, 'store'])->name('crm.store');
        Route::post('/crm/{oportunidad}/etapa', [CrmController::class, 'updateEtapa'])->name('crm.etapa');
        Route::post('/crm/{oportunidad}/actividad', [CrmController::class, 'addActividad'])->name('crm.actividad');
    });

    Route::middleware('permission:pos.vender')->group(function () {
        Route::get('/mercadopago/visor', [MercadoPagoVisorController::class, 'index'])->name('mercadopago.visor');
    });

    Route::middleware('permission:empresas.gestionar')->group(function () {
        Route::get('/empresas', [EmpresasAdminController::class, 'index'])->name('empresas.index');
        Route::post('/empresas', [EmpresasAdminController::class, 'store'])->name('empresas.store');
        Route::put('/empresas/{empresa}', [EmpresasAdminController::class, 'update'])->name('empresas.update');
    });

    Route::middleware('permission:roles.gestionar')->group(function () {
        Route::get('/roles', [RolController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RolController::class, 'store'])->name('roles.store');
        Route::put('/roles/{rol}', [RolController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{rol}', [RolController::class, 'destroy'])->name('roles.destroy');
    });

    Route::middleware('permission:compras.ver')->group(function () {
        Route::get('/compras', [CompraController::class, 'index'])->name('compras.index');
        Route::get('/compras/nueva', [CompraController::class, 'create'])->name('compras.create');
        Route::post('/compras', [CompraController::class, 'store'])->name('compras.store');
        Route::post('/compras/desde-ocr', [CompraController::class, 'desdeOcr'])->name('compras.desde-ocr');
        Route::get('/compras/{compra}', [CompraController::class, 'show'])->whereNumber('compra')->name('compras.show');
        Route::post('/compras/{compra}/anular', [CompraController::class, 'anular'])->name('compras.anular');
    });

    Route::middleware('permission:presupuestos.ver')->group(function () {
        Route::get('/presupuestos', [PresupuestoController::class, 'index'])->name('presupuestos.index');
        Route::get('/presupuestos/nuevo', [PresupuestoController::class, 'create'])->name('presupuestos.create');
        Route::post('/presupuestos', [PresupuestoController::class, 'store'])->name('presupuestos.store');
        Route::get('/presupuestos/{presupuesto}', [PresupuestoController::class, 'show'])->whereNumber('presupuesto')->name('presupuestos.show');
        Route::post('/presupuestos/{presupuesto}/anular', [PresupuestoController::class, 'anular'])->name('presupuestos.anular');
        Route::post('/presupuestos/{presupuesto}/aprobar', [PresupuestoController::class, 'aprobar'])->name('presupuestos.aprobar');
        Route::post('/presupuestos/{presupuesto}/rechazar', [PresupuestoController::class, 'rechazar'])->name('presupuestos.rechazar');
        Route::post('/presupuestos/{presupuesto}/pedido', [PresupuestoController::class, 'marcarPedido'])->name('presupuestos.pedido');
    });

    Route::middleware('permission:retenciones.ver')->group(function () {
        Route::get('/retenciones', [RetencionController::class, 'index'])->name('retenciones.index');
        Route::post('/retenciones', [RetencionController::class, 'store'])->name('retenciones.store');
        Route::post('/retenciones/{retencion}/anular', [RetencionController::class, 'anular'])->name('retenciones.anular');
        Route::get('/retenciones/txt', [RetencionController::class, 'exportarTxt'])->name('retenciones.txt');
        Route::get('/retenciones/zip', [RetencionController::class, 'exportarZip'])->name('retenciones.zip');
    });

    // Integración Tiendanube
    Route::middleware('permission:empresa.editar')->prefix('integraciones')->group(function () {
        Route::get('/tiendanube', [TiendanubeController::class, 'index'])->name('tiendanube.index');
        Route::post('/tiendanube/connect', [TiendanubeController::class, 'connect'])->name('tiendanube.connect');
        Route::get('/tiendanube/callback', [TiendanubeController::class, 'callback'])->name('tiendanube.callback');
        Route::delete('/tiendanube', [TiendanubeController::class, 'disconnect'])->name('tiendanube.disconnect');
        Route::patch('/tiendanube/config', [TiendanubeController::class, 'updateConfig'])->name('tiendanube.config');
        Route::post('/tiendanube/test', [TiendanubeController::class, 'testConnection'])->name('tiendanube.test');
        Route::get('/tiendanube/logs', [TiendanubeController::class, 'logs'])->name('tiendanube.logs');
        Route::get('/tiendanube/productos', [TiendanubeController::class, 'productos'])->name('tiendanube.productos');
        Route::post('/tiendanube/sync/products', [TiendanubeController::class, 'syncProducts'])->name('tiendanube.sync.products');
        Route::post('/tiendanube/sync/stock', [TiendanubeController::class, 'syncStock'])->name('tiendanube.sync.stock');
        Route::post('/tiendanube/import/orders', [TiendanubeController::class, 'importOrders'])->name('tiendanube.import.orders');
        Route::post('/tiendanube/import/products', [TiendanubeController::class, 'importProducts'])->name('tiendanube.import.products');

        // Shopify
        Route::get('/shopify', [ShopifyController::class, 'index'])->name('shopify.index');
        Route::post('/shopify', [ShopifyController::class, 'store'])->name('shopify.store');
        Route::delete('/shopify', [ShopifyController::class, 'disconnect'])->name('shopify.disconnect');
        Route::patch('/shopify/config', [ShopifyController::class, 'updateConfig'])->name('shopify.config');
        Route::post('/shopify/test', [ShopifyController::class, 'testConnection'])->name('shopify.test');
        Route::get('/shopify/logs', [ShopifyController::class, 'logs'])->name('shopify.logs');
        Route::post('/shopify/import/products', [ShopifyController::class, 'importProducts'])->name('shopify.import.products');
        Route::post('/shopify/sync/products', [ShopifyController::class, 'syncProducts'])->name('shopify.sync.products');

        Route::get('/whatsapp', [YcloudController::class, 'index'])->name('ycloud.index');
        Route::post('/whatsapp', [YcloudController::class, 'store'])->name('ycloud.store');
        Route::delete('/whatsapp', [YcloudController::class, 'disconnect'])->name('ycloud.disconnect');
        Route::patch('/whatsapp/config', [YcloudController::class, 'updateConfig'])->name('ycloud.config');
        Route::post('/whatsapp/test', [YcloudController::class, 'testConnection'])->name('ycloud.test');
        Route::post('/whatsapp/probar', [YcloudController::class, 'probar'])->name('ycloud.probar');
        Route::get('/whatsapp/mensajes', [YcloudController::class, 'mensajes'])->name('ycloud.mensajes');
        Route::post('/whatsapp/conversaciones/{conversacion}/reanudar', [YcloudController::class, 'reanudarBot'])->name('ycloud.reanudar');

        Route::get('/n8n', [N8nController::class, 'index'])->name('n8n.index');
        Route::post('/n8n', [N8nController::class, 'store'])->name('n8n.store');
        Route::post('/n8n/probar', [N8nController::class, 'probar'])->name('n8n.probar');
    });
});
