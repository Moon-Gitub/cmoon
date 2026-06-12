<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\ListaPrecioController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\UsuarioController;
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
});
