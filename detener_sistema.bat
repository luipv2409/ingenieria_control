@echo off
title Detener Punata Vacuna Segura - Docker
color 0C

echo ===================================================
echo   DETENIENDO CONTENEDORES DE PUNATA VACUNA SEGURA
echo ===================================================
echo.

docker compose down

echo.
echo Los contenedores han sido detenidos correctamente.
pause

