<?php

namespace App\Http\Controllers;

use App\Models\CajaSesion;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $hoy = Venta::where('estado', 'completada')->whereDate('fecha', today());

        $sesionAbierta = CajaSesion::with('caja')
            ->where('user_id', auth()->id())
            ->where('estado', 'abierta')
            ->latest('abierta_at')
            ->first();

        $ultimos7 = Venta::where('estado', 'completada')
            ->where('fecha', '>=', now()->subDays(6)->startOfDay())
            ->select(DB::raw('DATE(fecha) as dia'), DB::raw('SUM(total) as total'))
            ->groupBy('dia')->orderBy('dia')
            ->pluck('total', 'dia');

        return view('dashboard', [
            'ventasHoyTotal' => (float) (clone $hoy)->sum('total'),
            'ventasHoyCantidad' => (clone $hoy)->count(),
            'sesionAbierta' => $sesionAbierta,
            'productosActivos' => Producto::where('activo', true)->count(),
            'usuariosActivos' => User::where('activo', true)->count(),
            'ultimasVentas' => Venta::with('cliente')->latest('fecha')->limit(8)->get(),
            'ultimos7' => $ultimos7,
        ]);
    }
}
