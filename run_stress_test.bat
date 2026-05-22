@echo off
echo ============================================================
echo  SYNTImeat — Stress Test
echo  %DATE% %TIME%
echo ============================================================
cd /d C:\laragon\www\syntimeat

echo Ejecutando stress test...
php stress_test.php > stress_output.txt 2>&1

echo.
echo Resultado guardado en: stress_output.txt
echo.
echo === ULTIMAS LINEAS DEL OUTPUT ===
echo.
type stress_output.txt
echo.
echo ============================================================
echo  Presiona cualquier tecla para cerrar...
pause > nul
