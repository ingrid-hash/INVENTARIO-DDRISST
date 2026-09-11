<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RespaldoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RespaldoController extends Controller
{
    public function __construct(private readonly RespaldoService $respaldos) {}

    public function index(): Response
    {
        return Inertia::render('admin/respaldos/index', [
            'respaldos' => $this->respaldos->listar(),
            'disponible' => $this->respaldos->disponible(),
            'base_datos' => config('database.connections.pgsql.database'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nota' => ['nullable', 'string', 'max:255'],
        ], attributes: ['nota' => 'nota']);

        try {
            $nombre = $this->respaldos->generar($datos['nota'] ?? null);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['respaldo' => $e->getMessage()]);
        }

        return back()->with('status', sprintf(
            'Respaldo %s generado. Descárguelo y guárdelo fuera de este equipo.',
            $nombre,
        ));
    }

    public function descargar(string $nombre): BinaryFileResponse
    {
        $ruta = $this->respaldos->rutaDe($nombre);

        abort_if($ruta === null, 404);

        return response()->download($ruta);
    }

    public function destroy(string $nombre): RedirectResponse
    {
        abort_unless($this->respaldos->eliminar($nombre), 404);

        return back()->with('status', 'Respaldo eliminado.');
    }
}
