@echo off
chcp 65001 >nul

set "output=estructura_proyecto.txt"

echo ========================================
echo   GENERANDO REPORTE DE ESTRUCTURA
echo ========================================
echo.
echo Analizando proyecto...

:: Crear el archivo
echo ESTRUCTURA DEL PROYECTO > "%output%"
echo ================================================ >> "%output%"
echo Fecha: %date% %time% >> "%output%"
echo Ruta: %CD% >> "%output%"
echo ================================================ >> "%output%"
echo. >> "%output%"

:: Arbol visual
echo [ARBOL DE DIRECTORIOS] >> "%output%"
echo. >> "%output%"
tree /F /A >> "%output%" 2>nul

echo. >> "%output%"
echo ================================================ >> "%output%"
echo [LISTA COMPLETA DE RUTAS] >> "%output%"
echo ================================================ >> "%output%"
dir /s /b >> "%output%" 2>nul

echo.
echo Listo! Archivo generado: %output%
echo.
echo El archivo se encuentra en:
echo %CD%\%output%
echo.
echo Puedes abrirlo con cualquier editor de texto.
echo.
echo Presiona cualquier tecla para salir...
pause >nul
