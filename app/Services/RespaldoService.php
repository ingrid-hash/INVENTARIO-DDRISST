<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Respaldo de la base de datos con pg_dump.
 *
 * El sistema se instala en cada equipo con su propia base, asi que el respaldo
 * tambien es local: cada instalacion resguarda sus propios datos. Los archivos
 * quedan en storage y se pueden descargar para llevarlos a otro medio, que es
 * lo que de verdad protege la informacion.
 */
class RespaldoService
{
    /** Carpeta dentro del disco privado donde se guardan los respaldos. */
    public const CARPETA = 'respaldos';

    /** Un respaldo no deberia tardar mas que esto ni en la base mas grande. */
    private const SEGUNDOS_MAXIMO = 600;

    /**
     * Genera el respaldo y devuelve el nombre del archivo creado.
     *
     * @throws RuntimeException si no encuentra pg_dump o si el volcado falla
     */
    public function generar(?string $nota = null): string
    {
        $binario = $this->ubicarPgDump();
        $conexion = config('database.connections.pgsql');

        $nombre = sprintf(
            '%s-%s.sql',
            $conexion['database'],
            now()->format('Y-m-d-His'),
        );

        Storage::disk('local')->makeDirectory(self::CARPETA);
        $destino = Storage::disk('local')->path(self::CARPETA.'/'.$nombre);

        $proceso = new Process([
            $binario,
            '--host='.$conexion['host'],
            '--port='.$conexion['port'],
            '--username='.$conexion['username'],
            '--dbname='.$conexion['database'],
            '--file='.$destino,

            // Sin dueno ni privilegios: asi el respaldo se puede restaurar en
            // otro equipo donde el usuario de la base se llame distinto.
            '--no-owner',
            '--no-privileges',
        ]);

        // La contrasena va por el entorno del proceso y no en la linea de
        // comandos, donde quedaria visible para cualquiera que liste procesos.
        $proceso->setEnv($this->entorno((string) $conexion['password']));
        $proceso->setTimeout(self::SEGUNDOS_MAXIMO);
        $proceso->run();

        if (! $proceso->isSuccessful()) {
            @unlink($destino);

            throw new RuntimeException(
                'pg_dump no pudo generar el respaldo: '.$this->aUtf8(trim($proceso->getErrorOutput()))
            );
        }

        if (! is_file($destino) || filesize($destino) === 0) {
            @unlink($destino);

            throw new RuntimeException('El respaldo se generó vacío.');
        }

        AuditLog::registrar(
            evento: 'respaldo.generado',
            descripcion: sprintf('Se generó el respaldo %s (%s)', $nombre, $this->tamano(filesize($destino))),
            datos: array_filter(['archivo' => $nombre, 'nota' => $nota]),
        );

        return $nombre;
    }

    /**
     * Los respaldos guardados, del mas reciente al mas antiguo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listar(): array
    {
        $disco = Storage::disk('local');

        if (! $disco->directoryExists(self::CARPETA)) {
            return [];
        }

        $archivos = collect($disco->files(self::CARPETA))
            ->filter(fn (string $ruta) => str_ends_with($ruta, '.sql'))
            ->map(fn (string $ruta) => [
                'nombre' => basename($ruta),
                'bytes' => $disco->size($ruta),
                'tamano' => $this->tamano($disco->size($ruta)),
                'generado_en' => date('d/m/Y H:i', $disco->lastModified($ruta)),
                'marca' => $disco->lastModified($ruta),
            ])
            ->sortByDesc('marca')
            ->values();

        return $archivos->all();
    }

    /** La ruta del archivo, si existe y si el nombre es de los nuestros. */
    public function rutaDe(string $nombre): ?string
    {
        // Solo el nombre, nunca una ruta: evita que un nombre con ".." saque
        // el acceso de la carpeta de respaldos.
        $nombre = basename($nombre);

        if (! str_ends_with($nombre, '.sql')) {
            return null;
        }

        $disco = Storage::disk('local');
        $ruta = self::CARPETA.'/'.$nombre;

        return $disco->fileExists($ruta) ? $disco->path($ruta) : null;
    }

    public function eliminar(string $nombre): bool
    {
        $ruta = $this->rutaDe($nombre);

        if ($ruta === null) {
            return false;
        }

        Storage::disk('local')->delete(self::CARPETA.'/'.basename($nombre));

        AuditLog::registrar(
            evento: 'respaldo.eliminado',
            descripcion: sprintf('Se eliminó el respaldo %s', basename($nombre)),
            datos: ['archivo' => basename($nombre)],
        );

        return true;
    }

    /**
     * Donde esta pg_dump en este equipo.
     *
     * Se busca en tres lugares porque en las instalaciones del MSPAS no esta
     * en el PATH: primero lo que diga el archivo de configuracion, luego el
     * PATH, y por ultimo las carpetas donde el instalador de PostgreSQL lo
     * deja en Windows.
     */
    public function ubicarPgDump(): string
    {
        $configurado = config('database.pg_dump');

        if (is_string($configurado) && $configurado !== '' && is_file($configurado)) {
            return $configurado;
        }

        $enPath = (new \Symfony\Component\Process\ExecutableFinder)->find('pg_dump');

        if ($enPath !== null) {
            return $enPath;
        }

        foreach (glob('C:\\Program Files\\PostgreSQL\\*\\bin\\pg_dump.exe') ?: [] as $candidato) {
            if (is_file($candidato)) {
                return $candidato;
            }
        }

        throw new RuntimeException(
            'No se encontró pg_dump en este equipo. Indique su ruta con PG_DUMP_PATH '
            .'en el archivo .env, por ejemplo C:\\Program Files\\PostgreSQL\\18\\bin\\pg_dump.exe'
        );
    }

    /** Indica si el respaldo se puede generar, sin llegar a generarlo. */
    public function disponible(): bool
    {
        try {
            $this->ubicarPgDump();

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * El entorno con el que se ejecuta pg_dump.
     *
     * Las variables se toman con getenv() y se pasan explicitamente en lugar de
     * confiar en que el proceso las herede. La razon es concreta: cuando el
     * sistema se levanta con "php artisan serve", Laravel arma el entorno del
     * servidor a partir de $_ENV, y con la configuracion de PHP que traen las
     * instalaciones de Windows ($_ENV viene vacio) el servidor queda sin
     * SystemRoot. Sin esa variable, pg_dump 18 no puede pedirle numeros
     * aleatorios a Windows y falla al generar su llave interna.
     *
     * @return array<string, string>
     */
    private function entorno(string $password): array
    {
        $entorno = ['PGPASSWORD' => $password];

        foreach (['SystemRoot', 'windir', 'COMSPEC', 'PATH', 'PATHEXT',
            'TEMP', 'TMP', 'USERPROFILE', 'APPDATA', 'LOCALAPPDATA'] as $variable) {
            $valor = getenv($variable);

            if ($valor !== false && $valor !== '') {
                $entorno[$variable] = $valor;
            }
        }

        // Si ni asi aparece, se usa la ruta habitual de Windows: sin esto el
        // respaldo no se puede generar.
        if (! isset($entorno['SystemRoot']) && PHP_OS_FAMILY === 'Windows') {
            $entorno['SystemRoot'] = 'C:\\Windows';
        }

        return $entorno;
    }

    /**
     * Pasa a UTF-8 lo que escribe pg_dump.
     *
     * En Windows sus mensajes salen en la codificacion de la consola, no en
     * UTF-8. Si ese texto llega tal cual a un mensaje de error, la respuesta
     * revienta al serializarse con "Malformed UTF-8 characters" y el usuario
     * ve un error del sistema en vez del motivo real del fallo.
     */
    private function aUtf8(string $texto): string
    {
        if ($texto === '' || mb_check_encoding($texto, 'UTF-8')) {
            return $texto;
        }

        return mb_convert_encoding($texto, 'UTF-8', ['Windows-1252', 'ISO-8859-1']);
    }

    private function tamano(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        return max(1, (int) round($bytes / 1024)).' KB';
    }
}
