#!/usr/bin/env bash
#
# Actualiza SGC Calidad en el servidor.
# Uso:  ./deploy.sh
#
# Pone el sistema en mantenimiento, baja los cambios, instala dependencias,
# corre migraciones, regenera los caches y vuelve a levantarlo. Si algo falla,
# sale del modo mantenimiento para no dejar el sistema caido.

set -euo pipefail

cd "$(dirname "$0")"

PHP=${PHP:-php}
COMPOSER=${COMPOSER:-composer}

# Al salir por error, se asegura de reactivar el sistema.
levantar() {
    $PHP artisan up >/dev/null 2>&1 || true
}
trap levantar ERR INT TERM

echo "==> Verificando que el arbol de trabajo este limpio"
if [ -n "$(git status --porcelain)" ]; then
    echo "    Hay cambios locales sin confirmar. Revisalos antes de desplegar:"
    git status --short
    exit 1
fi

echo "==> Poniendo el sistema en mantenimiento"
# El certificado publico sigue accesible durante el mantenimiento: un cliente
# que escanea un QR en este momento no tiene por que ver una pagina de error.
$PHP artisan down --render="errors::503" --secret="despliegue" || true

echo "==> Bajando cambios"
git pull --ff-only

echo "==> Instalando dependencias de PHP"
$COMPOSER install --no-dev --optimize-autoloader --no-interaction

echo "==> Compilando assets"
if command -v npm >/dev/null 2>&1; then
    npm ci --silent
    npm run build
else
    echo "    npm no esta instalado: se conserva el public/build que ya estaba."
fi

echo "==> Corriendo migraciones"
$PHP artisan migrate --force

echo "==> Regenerando caches"
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

echo "==> Levantando el sistema"
$PHP artisan up

trap - ERR INT TERM

echo ""
echo "Despliegue terminado."
echo "Verifica que un QR abra el certificado desde un celular con datos moviles."
