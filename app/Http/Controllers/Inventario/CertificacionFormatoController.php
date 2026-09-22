<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CertificacionFormato;
use App\Services\CertificacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Combinaciones de firma de la certificacion. Se administran desde la misma
 * pantalla de emision, en Configuracion.
 */
class CertificacionFormatoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $formato = CertificacionFormato::create($datos + [
            'orden' => (int) CertificacionFormato::max('orden') + 1,
            'activo' => true,
        ]);

        AuditLog::registrar(
            evento: 'certificacion.formato_creado',
            descripcion: 'Se agregó la combinación de firmas «'.$formato->nombre.'»',
            datos: ['formato_id' => $formato->id],
        );

        return back()->with('status', 'Combinación agregada.');
    }

    public function update(Request $request, CertificacionFormato $formato): RedirectResponse
    {
        $formato->update($this->validar($request, $formato));

        AuditLog::registrar(
            evento: 'certificacion.formato_editado',
            descripcion: 'Se modificó la combinación de firmas «'.$formato->nombre.'»',
            datos: ['formato_id' => $formato->id],
        );

        return back()->with('status', 'Combinación actualizada.');
    }

    public function destroy(CertificacionFormato $formato): RedirectResponse
    {
        // Las cuatro del formato oficial se pueden desactivar, no borrar: son
        // las que respaldan las certificaciones ya emitidas.
        if ($formato->predeterminado) {
            $formato->update(['activo' => ! $formato->activo]);

            return back()->with(
                'status',
                $formato->activo ? 'Combinación habilitada.' : 'Combinación oculta.'
            );
        }

        if ($formato->certificaciones()->exists()) {
            $formato->update(['activo' => false]);

            return back()->with('status', 'Combinación oculta: ya tiene certificaciones emitidas.');
        }

        $nombre = $formato->nombre;
        $formato->delete();

        AuditLog::registrar(
            evento: 'certificacion.formato_eliminado',
            descripcion: 'Se eliminó la combinación de firmas «'.$nombre.'»',
        );

        return back()->with('status', 'Combinación eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?CertificacionFormato $formato = null): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'cargo_apertura' => ['required', 'string', 'max:120'],
            'genero' => ['required', 'in:f,m'],
            'firmante_nombre' => ['required', 'string', 'max:120'],
            'firmante_cargo' => ['required', 'string', 'max:120'],
            'vobo_nombre' => ['required', 'string', 'max:120'],
            'vobo_cargo' => ['required', 'string', 'max:120'],
            'institucion' => ['nullable', 'string', 'max:160'],
        ], attributes: [
            'nombre' => 'nombre de la combinación',
            'cargo_apertura' => 'cargo en la línea de apertura',
            'genero' => 'género',
            'vobo_nombre' => 'nombre de quien da el visto bueno',
            'vobo_cargo' => 'cargo de quien da el visto bueno',
        ]);

        $repetido = CertificacionFormato::where('nombre', $datos['nombre'])
            ->when($formato, fn ($q) => $q->whereKeyNot($formato->id))
            ->exists();

        if ($repetido) {
            throw ValidationException::withMessages([
                'nombre' => 'Ya hay otra combinación con ese nombre.',
            ]);
        }

        $datos['institucion'] = trim((string) ($datos['institucion'] ?? '')) !== ''
            ? $datos['institucion']
            : CertificacionService::INSTITUCION;

        return $datos;
    }
}
