# Imagen del sistema de inventario para publicarlo en Render.
#
# Render no tiene un entorno de PHP propio: sus lenguajes nativos son Node,
# Python, Ruby, Go, Rust y Elixir. Para PHP el despliegue se hace con Docker, y
# eso es lo que arma este archivo.

# --- Primera etapa: compilar las pantallas ---------------------------------
# El paquete de JavaScript no viaja por git, asi que se compila aqui.
FROM node:22-bookworm-slim AS pantallas

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build


# --- Segunda etapa: el sistema --------------------------------------------
FROM php:8.3-cli

# Las extensiones que necesita el sistema:
#   pdo_pgsql, pgsql  la base de datos
#   zip, gd           leer los Excel y dibujar el membrete de los PDF
#   bcmath, intl      cuentas y formato de numeros
#   opcache           que no vuelva a compilar el PHP en cada visita
#
# libpq-dev son las cabeceras de PostgreSQL, que PHP necesita para compilar
# pdo_pgsql; postgresql-client son los programas, y de ahi sale el pg_dump con
# el que el sistema hace los respaldos. Hacen falta los dos.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        libpq-dev \
        postgresql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql zip gd bcmath intl opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Sin esto PHP arranca con sus valores minimos: 128 MB de memoria y 2 MB de
# subida, con lo que no se genera un PDF ni se carga un Excel.
COPY docker/php.ini /usr/local/etc/php/conf.d/inventario.ini

WORKDIR /app

# Las dependencias primero y el codigo despues: asi un cambio en el codigo no
# obliga a volver a bajar todo Composer en cada despliegue.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=pantallas /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
    && chmod +x docker/entrada.sh \
    # Las carpetas de trabajo de Laravel van vacias en el repositorio, asi que
    # no viajan en la imagen. Si no existen, al arrancar falla con "Please
    # provide a valid cache path".
    && mkdir -p storage/framework/cache/data \
               storage/framework/sessions \
               storage/framework/views \
               storage/framework/testing \
               storage/logs \
               storage/app/private \
               bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

ENV PORT=8080
EXPOSE 8080

CMD ["./docker/entrada.sh"]
