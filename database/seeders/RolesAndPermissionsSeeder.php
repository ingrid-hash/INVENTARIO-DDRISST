<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permisos del sistema. El nombre sigue siempre el formato
     * "modulo.accion" para que la pantalla de asignacion los agrupe sola.
     *
     * @var array<int, string>
     */
    private const PERMISOS = [
        // Seguridad
        'usuarios.ver',
        'usuarios.crear',
        'usuarios.editar',
        'usuarios.eliminar',
        'usuarios.resetear_password',
        'roles.ver',
        'roles.crear',
        'roles.editar',
        'roles.eliminar',
        'permisos.ver',
        'permisos.crear',
        'permisos.editar',
        'permisos.eliminar',
        'bitacora.ver',
        'respaldos.ver',
        'respaldos.crear',

        // Catalogos
        'unidades.ver',
        'unidades.crear',
        'unidades.editar',
        'unidades.eliminar',
        'renglones.ver',
        'renglones.crear',
        'renglones.editar',
        'renglones.eliminar',
        'empleados.ver',
        'empleados.crear',
        'empleados.editar',
        'empleados.eliminar',

        // Bienes
        'bienes.ver',
        'bienes.crear',
        'bienes.editar',
        'bienes.eliminar',
        'bienes.asignar_cuenta',

        // Tarjetas de responsabilidad
        'tarjetas.ver',
        'tarjetas.crear',
        'tarjetas.editar',
        'tarjetas.imprimir',
        'tarjetas.regenerar',

        // Retractar la marca de impresion de un bien marcado por error. Es una
        // correccion sobre un documento firmado, por eso va aparte de editar.
        'tarjetas.desmarcar_impresion',

        // Asignaciones y adiciones
        'asignaciones.ver',
        'asignaciones.crear',
        'asignaciones.cerrar',
        'adiciones.ver',
        'adiciones.crear',

        // Bajas: solicitar y autorizar deben poder separarse, para que quien
        // pide la baja no sea quien la aprueba.
        'bajas.ver',
        'bajas.solicitar',
        'bajas.autorizar',

        // Importacion de archivos de Excel
        'importaciones.ver',
        'importaciones.ejecutar',
        'importaciones.revertir',
        'perfiles_importacion.ver',
        'perfiles_importacion.editar',

        // Reportes
        'reportes.ver',
        'reportes.exportar',

        // Certificaciones de inventario. Configurar es aparte porque toca las
        // firmas del documento, no la emision del dia a dia.
        'certificaciones.ver',
        'certificaciones.emitir',
        'certificaciones.configurar',
    ];

    /**
     * Permisos que un Admin no debe tener: tocan la estructura del sistema o
     * exigen separacion de funciones.
     *
     * @var array<int, string>
     */
    private const SOLO_SUPERADMIN = [
        // El respaldo se lleva toda la base: usuarios, contrasenas cifradas y
        // bitacora. Por eso nace reservado al Superadministrador, aunque el
        // permiso se puede ceder al Administrador desde la pantalla de roles.
        'respaldos.ver',
        'respaldos.crear',

        'permisos.crear',
        'permisos.editar',
        'permisos.eliminar',
        'importaciones.revertir',
    ];

    /**
     * Lo que puede hacer el rol Usuario: consultar e imprimir, nada mas.
     *
     * @var array<int, string>
     */
    private const PERMISOS_USUARIO = [
        'bienes.ver',
        'unidades.ver',
        'renglones.ver',
        'empleados.ver',
        'tarjetas.ver',
        'tarjetas.imprimir',
        'asignaciones.ver',
        'adiciones.ver',
        'bajas.ver',
        'reportes.ver',
        'certificaciones.ver',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISOS as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        // El Superadmin tiene acceso total por el Gate::before de
        // AppServiceProvider; se le asignan igual para que se vean en pantalla.
        Role::findOrCreate('Superadmin', 'web')->syncPermissions(self::PERMISOS);

        Role::findOrCreate('Admin', 'web')->syncPermissions(
            array_values(array_diff(self::PERMISOS, self::SOLO_SUPERADMIN))
        );

        Role::findOrCreate('Usuario', 'web')->syncPermissions(self::PERMISOS_USUARIO);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info('Permisos sembrados: '.count(self::PERMISOS));
    }
}
