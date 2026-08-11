<?php

namespace App\Support;

use App\Rules\PasswordNoReutilizada;
use App\Models\User;
use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    /**
     * Reglas de complejidad exigidas a toda contrasena del sistema.
     *
     * Se definen en un solo lugar para que el alta de usuarios, el
     * restablecimiento por administrador, el cambio forzado y el cambio
     * voluntario no puedan divergir entre si.
     *
     * @param  User|null  $usuario  cuando se indica, tambien se impide reutilizar sus contrasenas recientes
     * @return array<int, mixed>
     */
    public static function rules(?User $usuario = null): array
    {
        $reglas = [
            'required',
            'string',
            'confirmed',
            Password::min(10)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols(),
        ];

        if ($usuario !== null) {
            $reglas[] = new PasswordNoReutilizada($usuario);
        }

        return $reglas;
    }

    /**
     * Genera una contrasena temporal que cumple la politica anterior.
     * Se usa cuando un administrador restablece la clave de un usuario.
     */
    public static function generarTemporal(): string
    {
        $minusculas = 'abcdefghijkmnopqrstuvwxyz';
        $mayusculas = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $numeros = '23456789';
        $simbolos = '!@#$%&*?';

        $caracteres = [
            $minusculas[random_int(0, strlen($minusculas) - 1)],
            $mayusculas[random_int(0, strlen($mayusculas) - 1)],
            $numeros[random_int(0, strlen($numeros) - 1)],
            $simbolos[random_int(0, strlen($simbolos) - 1)],
        ];

        $todos = $minusculas.$mayusculas.$numeros.$simbolos;

        for ($i = count($caracteres); $i < 12; $i++) {
            $caracteres[] = $todos[random_int(0, strlen($todos) - 1)];
        }

        shuffle($caracteres);

        return implode('', $caracteres);
    }
}
