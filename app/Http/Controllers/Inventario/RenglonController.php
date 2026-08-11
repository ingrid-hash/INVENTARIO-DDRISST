<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Renglon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;


class RenglonController extends Controller
{
    public function index(): Response
    {
        $renglones = Renglon::query()
            ->ordenados()
            ->withCount('bienes')
            ->get()
            ->map(fn (Renglon $renglon) => [
                'id' => $renglon->id,
                'codigo' => $renglon->codigo,
                'nombre' => $renglon->nombre,
                'orden' => $renglon->orden,
                'activo' => $renglon->activo,
                'bienes' => $renglon->bienes_count,
            ]);

        return Inertia::render('inventario/cuentas/index', [
            'renglones' => $renglones,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $renglon = Renglon::create($datos);

        AuditLog::registrar(
            evento: 'cuenta.creada',
            descripcion: sprintf('Se creó la cuenta %s %s', $renglon->codigo, $renglon->nombre),
            modelo: $renglon,
        );

        return back()->with('status', sprintf('Cuenta %s creada.', $renglon->codigo));
    }

    public function update(Request $request, Renglon $renglon): RedirectResponse
    {
        $datos = $this->validar($request, $renglon);

        $anterior = $renglon->codigo.' '.$renglon->nombre;
        $renglon->update($datos);

        AuditLog::registrar(
            evento: 'cuenta.actualizada',
            descripcion: sprintf('La cuenta "%s" pasó a "%s %s"', $anterior, $renglon->codigo, $renglon->nombre),
            modelo: $renglon,
        );

        return back()->with('status', 'Cuenta actualizada.');
    }

    public function destroy(Renglon $renglon): RedirectResponse
    {
        if ($renglon->bienes()->exists()) {
            throw ValidationException::withMessages([
                'renglon' => sprintf(
                    'No se puede eliminar la cuenta %s porque tiene %d bien(es) clasificados. Desactívela en lugar de eliminarla.',
                    $renglon->codigo,
                    $renglon->bienes()->count(),
                ),
            ]);
        }

        AuditLog::registrar(
            evento: 'cuenta.eliminada',
            descripcion: sprintf('Se eliminó la cuenta %s', $renglon->codigo),
            modelo: $renglon,
        );

        $renglon->delete();

        return back()->with('status', 'Cuenta eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?Renglon $renglon = null): array
    {
        return $request->validate([
            // Formato oficial del renglon presupuestario: 1232.03, 1237.00
            'codigo' => [
                'required',
                'string',
                'regex:/^\d{4}\.\d{2}$/',
                Rule::unique('renglones', 'codigo')->ignore($renglon?->id),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'activo' => ['boolean'],
        ], [
            'codigo.regex' => 'La cuenta debe escribirse como 1232.03 (cuatro dígitos, punto, dos dígitos).',
        ], [
            'codigo' => 'código de cuenta',
            'nombre' => 'nombre de la cuenta',
        ]);
    }
}
