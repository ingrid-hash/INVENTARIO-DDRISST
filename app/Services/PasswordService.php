<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PasswordHistory;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;


class PasswordService
{
    /**
     * @param  string  $motivo  inicial | auto_cambio | reset_admin | cambio_forzado
     */
    public function cambiar(User $usuario, string $nuevaPassword, string $motivo, bool $debeCambiar = false): void
    {
        DB::transaction(function () use ($usuario, $nuevaPassword, $motivo, $debeCambiar) {
            $hash = Hash::make($nuevaPassword);

            $usuario->forceFill([
                'password' => $hash,
                'debe_cambiar_password' => $debeCambiar,
                'password_cambiado_en' => now(),
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => null,
            ])->save();

            PasswordHistory::create([
                'user_id' => $usuario->id,
                'cambiado_por' => Auth::id(),
                'password_hash' => $hash,
                'motivo' => $motivo,
                'ip' => Request::ip(),
                'user_agent' => substr((string) Request::userAgent(), 0, 255),
            ]);

            AuditLog::registrar(
                evento: 'password.'.$motivo,
                descripcion: $this->descripcionPara($usuario, $motivo),
                modelo: $usuario,
            );
        });
    }

    /**
     * Restablecimiento hecho por un administrador: genera una contrasena
     * temporal y obliga al usuario a cambiarla en su proximo ingreso.
     */
    public function restablecerPorAdministrador(User $usuario, string $passwordTemporal): void
    {
        $this->cambiar($usuario, $passwordTemporal, motivo: 'reset_admin', debeCambiar: true);
    }

    private function descripcionPara(User $usuario, string $motivo): string
    {
        $autor = Auth::user();

        return match ($motivo) {
            'inicial' => sprintf('Contraseña inicial asignada a %s', $usuario->username),
            'auto_cambio' => sprintf('%s cambió su propia contraseña', $usuario->username),
            'reset_admin' => sprintf(
                '%s restableció la contraseña de %s',
                $autor?->username ?? 'El sistema',
                $usuario->username,
            ),
            'cambio_forzado' => sprintf('%s definió su nueva contraseña tras un restablecimiento', $usuario->username),
            default => sprintf('Cambio de contraseña de %s', $usuario->username),
        };
    }
}
