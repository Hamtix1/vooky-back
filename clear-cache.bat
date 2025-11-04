@echo off
echo ============================================
echo Limpiando cache de Laravel...
echo ============================================
echo.

cd /d c:\laragon\www\vooky\vooky-back

echo [1/4] Limpiando cache de configuracion...
php artisan config:clear

echo [2/4] Limpiando cache general...
php artisan cache:clear

echo [3/4] Limpiando cache de rutas...
php artisan route:clear

echo [4/4] Limpiando cache de vistas...
php artisan view:clear

echo.
echo ============================================
echo Cache limpiado exitosamente!
echo ============================================
echo.
echo Ahora reinicia Laragon (Stop All y Start All)
echo.
pause
