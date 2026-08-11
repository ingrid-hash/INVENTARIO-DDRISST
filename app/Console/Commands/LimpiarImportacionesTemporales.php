<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Borra los archivos de Excel que quedaron a medio importar.
 *
 * Cuando alguien sube un libro y abandona la pantalla sin confirmar la carga, el
 * archivo se queda en la carpeta temporal. Sin esta limpieza el disco del
 * servidor se llenaria con libros de varios megabytes que ya nadie va a usar.
 */
class LimpiarImportacionesTemporales extends Command
{
    protected $signature = 'inventario:limpiar-importaciones {--horas=24 : Antigüedad mínima del archivo}';

    protected $description = 'Elimina los archivos de importación subidos que nunca se confirmaron';

    public function handle(): int
    {
        $horas = max(1, (int) $this->option('horas'));
        $limite = now()->subHours($horas)->getTimestamp();

        $archivos = Storage::files('importaciones');
        $borrados = 0;
        $liberado = 0;

        foreach ($archivos as $archivo) {
            if (Storage::lastModified($archivo) > $limite) {
                continue;
            }

            $liberado += Storage::size($archivo);
            Storage::delete($archivo);
            $borrados++;
        }

        $this->info(sprintf(
            '%d archivo(s) eliminados de %d revisados; %s liberados.',
            $borrados,
            count($archivos),
            $this->formatearTamano($liberado),
        ));

        return self::SUCCESS;
    }

    private function formatearTamano(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1024 / 1024, 1).' MB';
    }
}
