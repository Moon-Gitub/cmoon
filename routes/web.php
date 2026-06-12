<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CuentaCorrienteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\ListaPrecioController;
use App\Http\Controllers\MedioPagoController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProveedorController;
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
        Route::get('/productos/{producto}/stock', [ProductoController::class, 'stock'])->name('productos.stock');
        Route::post('/productos/{producto}/stock', [ProductoController::class, 'ajustarStock'])->name('productos.stock.ajustar');
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
});
