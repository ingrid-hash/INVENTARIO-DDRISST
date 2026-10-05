#!/usr/bin/env bash
#
# Lo que pasa cada vez que el servicio arranca en Render.
set -e

# Render publica la direccion del servicio aqui. Laravel la necesita para armar
# bien los enlaces y las rutas de los archivos compilados.
if [ -n "${RENDER_EXTERNAL_URL}" ]; then
    export APP_URL="${RENDER_EXTERNAL_URL}"
fi

echo "==> Migrando la base de datos"
php artisan migrate --force

# Los semilleros se pueden repetir sin romper nada: todos usan updateOrCreate o
# firstOrCreate, asi que vuelven a dejar los permisos, las cuentas, los
# renglones y los formatos de certificacion en su sitio sin duplicar.
echo "==> Sembrando permisos y catalogos"
php artisan db:seed --force

php artisan storage:link || true

echo "==> Guardando en memoria la configuracion y las rutas"
php artisan optimize

echo "==> Listo, escuchando en el puerto ${PORT:-8080}"
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
