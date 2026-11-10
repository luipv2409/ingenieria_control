# Simulador de envío ESP32-S3 (Prueba local antes de conectar el hardware físico)
# Ejecutar en PowerShell: .\simulador_esp32.ps1

$ServerHost = "127.0.0.1"
$ServerPort = 8090
$CodigoNodo = "NODO-REMOTO-01"
$SecretKey  = "punata-vacuna-segura-2026"

# Calcular token SHA-256
$stringToHash = $CodigoNodo.ToUpper() + $SecretKey
$hasher = [System.Security.Cryptography.SHA256]::Create()
$hashBytes = $hasher.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($stringToHash))
$token = [System.BitConverter]::ToString($hashBytes).Replace("-", "").ToLower()

Write-Host "Iniciando simulador de nodo ESP32 [$CodigoNodo] hacia http://$ServerHost`:$ServerPort" -ForegroundColor Cyan
Write-Host "Presiona Ctrl+C para detener la simulación.`n" -ForegroundColor Yellow

$puertaAbierta = $false
$contador = 0

while ($true) {
    $contador++
    # Temperatura simulada entre 3.5 °C y 6.5 °C (con pequeñas variaciones)
    $tempSimulada = [math]::Round(4.2 + (Get-Random -Minimum -10 -Maximum 15) / 10.0, 2)
    
    # Cada 6 lecturas simulamos una apertura o cierre de puerta
    if ($contador % 6 -eq 0) {
        $puertaAbierta = -not $puertaAbierta
        Write-Host ">> CAMBIO EN PUERTA: $(if ($puertaAbierta) { 'ABIERTA' } else { 'CERRADA' })" -ForegroundColor Magenta
    }

    $payload = @{
        codigo   = $CodigoNodo
        token    = $token
        lecturas = @(
            @{
                sensor  = "DS18B20-LC-1"
                valor   = $tempSimulada
                calidad = "VALIDO"
            }
        )
        puerta   = @{
            abierta = $puertaAbierta
        }
        energia  = @{
            estado  = "PRESENTE"
            voltaje = 220.0
        }
    } | ConvertTo-Json -Depth 5

    try {
        $resp = Invoke-RestMethod -Uri "http://$ServerHost`:$ServerPort/api/ingesta.php" -Method Post -Body $payload -ContentType "application/json"
        Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Enviado: Temp = $tempSimulada °C | Puerta = $(if ($puertaAbierta) { 'ABIERTA' } else { 'CERRADA' }) | Respuesta: OK (insertados: $($resp.insertados))" -ForegroundColor Green
    } catch {
        Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Error al enviar: $_" -ForegroundColor Red
    }

    Start-Sleep -Seconds 4
}

