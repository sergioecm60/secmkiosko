@echo off
REM ===================================================================
REM  Kiosco — genera el respaldo de datos de ejemplo en datos\ejemplo\
REM
REM  Este respaldo es el unico que se versiona en git. Los respaldos
REM  reales (Ajustes > Respaldos) NO se suben nunca.
REM ===================================================================
setlocal

set "PROYECTO=%~dp0"

REM Busca el PHP de Laragon: ..\..\bin\php\*\php.exe
set "PHP="
for /f "delims=" %%d in ('dir /b /o-d "%PROYECTO%..\..\bin\php" 2^>nul') do (
    if exist "%PROYECTO%..\..\bin\php\%%d\php.exe" (
        set "PHP=%PROYECTO%..\..\bin\php\%%d\php.exe"
        goto :listo
    )
)

:listo
if not defined PHP (
    where php >nul 2>&1
    if errorlevel 1 (
        echo.
        echo   No se encontro PHP.
        echo   Abri Laragon y presiona Start All, o usa Tools ^> Path ^> Add Laragon to Path.
        echo.
        pause
        exit /b 1
    )
    set "PHP=php"
)

echo.
echo   Generando respaldo de ejemplo...
echo.

"%PHP%" "%PROYECTO%respaldar-ejemplo.php"
set "CODIGO=%ERRORLEVEL%"

echo.
if "%CODIGO%"=="0" (
    echo   Listo. Para subirlo a git:
    echo       git add datos/ejemplo/
    echo       git commit -m "datos de ejemplo"
    echo       git push
) else (
    echo   No se pudo generar. Verifica que Laragon tenga MySQL encendido.
)
echo.
pause
