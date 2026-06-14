<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\CuentaCorrienteController;
use App\Http\Controllers\EmpresasAdminController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\PresupuestoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmisorController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\FacturacionController;
use App\Http\Controllers\InformeController;
use App\Http\Controllers\ListaPrecioController;
use App\Http\Controllers\MedioPagoController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\RetencionController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

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

    Route::middleware('permission:empresa.ver')->group(function () {
        Route::get('/empresa', [EmpresaController::class, 'edit'])->name('empresa.edit');
        Route::put('/empresa', [EmpresaController::class, 'update'])->name('empresa.update');
    });

    Route::middleware('permission:productos.ver')->group(function () {
        Route::get('/productos/importar', [ProductoController::class, 'importarForm'])->name('productos.importar');
        Route::post('/productos/importar', [ProductoController::class, 'importar'])->name('productos.importar.procesar');
        Route::get('/productos/plantilla-csv', [ProductoController::class, 'plantillaCsv'])->name('productos.plantilla');
        Route::get('/productos/{producto}/stock', [ProductoController::class, 'stock'])->name('productos.stock');
        Route::post('/productos/{producto}/stock', [ProductoController::class, 'ajustarStock'])->name('productos.stock.ajustar');
        Route::get('/productos/{producto}/combo', [ProductoController::class, 'combo'])->name('productos.combo');
        Route::post('/productos/{producto}/combo', [ProductoController::class, 'agregarComponente'])->name('productos.combo.agregar');
        Route::delete('/productos/{producto}/combo/{componente}', [ProductoController::class, 'quitarComponente'])->name('productos.combo.quitar');
        Route::resource('productos', ProductoController::class)->except('show');
    });

    Route::middleware('permission:categorias.ver')->group(function () {
        Route::resource('categorias', CategoriaController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    Route::middleware('permission:listas-precio.ver')->group(function () {
        Route::resource('listas-precio', ListaPrecioController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['listas-precio' => 'listaPrecio']);
    });

    Route::middleware('permission:clientes.ver')->group(function () {
        Route::resource('clientes', ClienteController::class)->except('show');
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
    });

    Route::middleware('permission:pos.vender')->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos');
        Route::get('/pos/catalogo', [PosController::class, 'catalogo'])->name('pos.catalogo');
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
        Route::get('/ventas/{venta}/ticket', [VentaController::class, 'ticket'])->name('ventas.ticket');
    });

    Route::middleware('permission:cajas.ver')->group(function () {
        Route::get('/cajas', [CajaController::class, 'index'])->name('cajas.index');
        Route::post('/cajas', [CajaController::class, 'store'])->name('cajas.store');
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
        Route::get('/facturacion/{comprobante}/nota', [FacturacionController::class, 'notaForm'])
            ->whereNumber('comprobante')->name('facturacion.nota');
        Route::post('/facturacion/{comprobante}/nota', [FacturacionController::class, 'notaStore'])
            ->whereNumber('comprobante')->name('facturacion.nota.store');
        Route::post('/ventas/{venta}/facturar', [FacturacionController::class, 'facturar'])->name('ventas.facturar');
        Route::post('/facturacion/facturar-lote', [FacturacionController::class, 'facturarLote'])->name('facturacion.facturar-lote');
        Route::post('/facturacion/{comprobante}/reintentar', [FacturacionController::class, 'reintentar'])->name('facturacion.reintentar');
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

    Route::middleware('permission:informes.ver')->group(function () {
        Route::get('/informes/ventas', [InformeController::class, 'ventas'])->name('informes.ventas');
        Route::get('/informes/stock', [InformeController::class, 'stock'])->name('informes.stock');
        Route::get('/informes/libro-iva', [InformeController::class, 'libroIva'])->name('informes.libro-iva');
        Route::get('/informes/cuentas-corrientes', [InformeController::class, 'cuentasCorrientes'])->name('informes.cuentas-corrientes');
        Route::get('/informes/cajas', [InformeController::class, 'cajas'])->name('informes.cajas');
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
        Route::get('/compras/{compra}', [CompraController::class, 'show'])->whereNumber('compra')->name('compras.show');
        Route::post('/compras/{compra}/anular', [CompraController::class, 'anular'])->name('compras.anular');
    });

    Route::middleware('permission:presupuestos.ver')->group(function () {
        Route::get('/presupuestos', [PresupuestoController::class, 'index'])->name('presupuestos.index');
        Route::get('/presupuestos/nuevo', [PresupuestoController::class, 'create'])->name('presupuestos.create');
        Route::post('/presupuestos', [PresupuestoController::class, 'store'])->name('presupuestos.store');
        Route::get('/presupuestos/{presupuesto}', [PresupuestoController::class, 'show'])->whereNumber('presupuesto')->name('presupuestos.show');
        Route::post('/presupuestos/{presupuesto}/anular', [PresupuestoController::class, 'anular'])->name('presupuestos.anular');
    });

    Route::middleware('permission:retenciones.ver')->group(function () {
        Route::get('/retenciones', [RetencionController::class, 'index'])->name('retenciones.index');
        Route::post('/retenciones', [RetencionController::class, 'store'])->name('retenciones.store');
        Route::post('/retenciones/{retencion}/anular', [RetencionController::class, 'anular'])->name('retenciones.anular');
        Route::get('/retenciones/txt', [RetencionController::class, 'exportarTxt'])->name('retenciones.txt');
    });
});
