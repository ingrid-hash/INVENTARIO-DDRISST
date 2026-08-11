<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $registros = AuditLog::query()
            ->with('user:id,username,name')
            ->when($request->string('evento')->toString(), fn ($q, string $evento) => $q->where('evento', $evento))
            ->when($request->string('buscar')->toString(), function ($q, string $buscar) {
                $q->where(function ($sub) use ($buscar) {
                    $sub->where('descripcion', 'ilike', "%{$buscar}%")
                        ->orWhere('usuario_email', 'ilike', "%{$buscar}%")
                        ->orWhere('ip', 'ilike', "%{$buscar}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AuditLog $registro) => [
                'id' => $registro->id,
                'evento' => $registro->evento,
                'descripcion' => $registro->descripcion,
                'usuario' => $registro->user?->username ?? $registro->usuario_email ?? 'Desconocido',
                'ip' => $registro->ip,
                'fecha' => $registro->created_at->format('d/m/Y H:i:s'),
            ]);

        return Inertia::render('admin/bitacora/index', [
            'registros' => $registros,
            'eventos' => AuditLog::query()->distinct()->orderBy('evento')->pluck('evento'),
            'filtros' => [
                'evento' => $request->string('evento')->toString(),
                'buscar' => $request->string('buscar')->toString(),
            ],
        ]);
    }
}
