@echo off
title Iniciar Punata Vacuna Segura - Docker
color 0A

echo ===================================================
echo   INICIANDO PUNATA VACUNA SEGURA EN DOCKER
echo ===================================================
echo.

docker compose up -d

echo.
echo ===================================================
echo   SISTEMA ACTIVO Y OPERATIVO
echo ===================================================
echo.
echo   - Panel Web:     http://localhost:8090
echo   - Usuario:       ludwinggunnarpv24@gmail.com
echo   - Clave:         admin123
echo.
echo ---------------------------------------------------
echo   TUS DIRECCIONES IP LOCALES (Para configurar el ESP32):
powershell -Command "Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.InterfaceAlias -notlike '*Loopback*' -and $_.IPAddress -notlike '169.*' -and $_.IPAddress -notlike '172.*' } | Select-Object -ExpandProperty IPAddress | ForEach-Object { Write-Host '   >> http://' + $_ + ':8090' -ForegroundColor Yellow }"
echo ---------------------------------------------------
echo.

start http://localhost:8090
pause

