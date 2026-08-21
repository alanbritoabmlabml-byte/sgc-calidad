<#
    Actualiza SGC Calidad en un servidor Windows con IIS.

    Uso, desde una consola de PowerShell como administrador, en la carpeta del proyecto:

        .\deploy.ps1

    Si PHP o Composer no estan en el PATH, pasalos por parametro:

        .\deploy.ps1 -Php "C:\php\php.exe" -Composer "C:\composer\composer.phar"

    Pone el sistema en mantenimiento, baja los cambios, instala dependencias,
    corre migraciones y regenera los caches. Si algo falla, saca el sistema del
    modo mantenimiento para no dejarlo caido.

    El certificado publico (/c/{token}) sigue respondiendo durante todo el
    proceso: un cliente puede estar escaneando el QR de un lote justo ahora.
#>

[CmdletBinding()]
param(
    [string]$Php = 'php',
    [string]$Composer = 'composer',
    [switch]$SaltarAssets
)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

function Escribir($mensaje) {
    Write-Host "==> $mensaje" -ForegroundColor Cyan
}

function Ejecutar($descripcion, [scriptblock]$bloque) {
    Escribir $descripcion
    & $bloque
    if ($LASTEXITCODE -ne 0) {
        throw "$descripcion fallo con codigo $LASTEXITCODE"
    }
}

# Composer puede venir como .phar, que se invoca a traves de php.
$composerEsPhar = $Composer -like '*.phar'

$enMantenimiento = $false

try {
    Escribir 'Verificando que no haya cambios locales sin confirmar'
    $sucio = git status --porcelain
    if ($sucio) {
        Write-Host '    Hay cambios locales en el servidor. Revisalos antes de desplegar:' -ForegroundColor Yellow
        git status --short
        throw 'Arbol de trabajo sucio'
    }

    Escribir 'Poniendo el sistema en mantenimiento'
    & $Php artisan down --secret="despliegue" | Out-Null
    $enMantenimiento = $true

    Ejecutar 'Bajando cambios del repositorio' { git pull --ff-only }

    if ($composerEsPhar) {
        Ejecutar 'Instalando dependencias de PHP' {
            & $Php $Composer install --no-dev --optimize-autoloader --no-interaction
        }
    } else {
        Ejecutar 'Instalando dependencias de PHP' {
            & $Composer install --no-dev --optimize-autoloader --no-interaction
        }
    }

    if ($SaltarAssets) {
        Escribir 'Se omite la compilacion de assets: se conserva el public/build actual'
    } elseif (Get-Command npm -ErrorAction SilentlyContinue) {
        Ejecutar 'Instalando dependencias de node' { npm ci --silent }
        Ejecutar 'Compilando assets' { npm run build }
    } else {
        Escribir 'npm no esta instalado: se conserva el public/build actual'
    }

    Ejecutar 'Corriendo migraciones' { & $Php artisan migrate --force }

    Ejecutar 'Regenerando cache de configuracion' { & $Php artisan config:cache }
    Ejecutar 'Regenerando cache de rutas' { & $Php artisan route:cache }
    Ejecutar 'Regenerando cache de vistas' { & $Php artisan view:cache }

    Escribir 'Levantando el sistema'
    & $Php artisan up | Out-Null
    $enMantenimiento = $false

    Write-Host ''
    Write-Host 'Despliegue terminado.' -ForegroundColor Green
    Write-Host 'Verifica que un QR abra el certificado desde un celular con datos moviles.' -ForegroundColor Green
}
catch {
    Write-Host ''
    Write-Host "El despliegue fallo: $($_.Exception.Message)" -ForegroundColor Red

    if ($enMantenimiento) {
        Write-Host 'Sacando el sistema del modo mantenimiento...' -ForegroundColor Yellow
        & $Php artisan up | Out-Null
    }

    exit 1
}
