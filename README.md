# Punata Vacuna Segura ❄️

Sistema inteligente de monitoreo y control en tiempo real para la cadena de frío de biológicos y vacunas en centros de salud, basado en contenedores **Docker** y nodos telemétricos **ESP32-S3**.

---

## 📌 Arquitectura del Sistema

* **Microcontrolador:** ESP32-S3 (Dual-Core Xtensa LX7, Wi-Fi 2.4 GHz + BLE).
* **Sensores:**
  * **DS18B20:** Sonda digital sumergible de temperatura (protocolo 1-Wire, Pin GPIO 4).
  * **MC-38:** Sensor magnético reed switch de contacto seco para detección de apertura/cierre de puerta (Pin GPIO 5).
* **Servidor de Aplicación:** PHP 8.2 con Apache levantado sobre Docker.
* **Base de Datos:** MariaDB 10.11 en contenedor con persistencia de volúmenes.
* **Seguridad:** Autenticación de ingesta de telemetría mediante token criptográfico SHA-256 (`mbedtls`).
* **Alertas y Notificaciones:** Notificaciones inmediatas en panel web y envío automático de correos institucionales vía Gmail SMTP ante desviaciones térmicas (< 2 °C o > 8 °C).

---

## 🚀 Despliegue con Docker

El sistema está configurado para ejecutarse en puertos no conflictivos con XAMPP u otros servicios:
* **Puerto Web:** `8090` (evita conflicto con el puerto 80)
* **Puerto Base de Datos:** `3308` (evita conflicto con el puerto 3306)

### Levantar los contenedores:
```bash
docker compose up -d
```
O simplemente ejecuta en Windows con doble clic: `iniciar_sistema.bat`.

### Detener los contenedores:
```bash
docker compose down
```
O con doble clic en: `detener_sistema.bat`.

---

## 🌐 Acceso al Panel Web

* **URL Local:** [http://localhost:8090](http://localhost:8090)
* **URL en Red LAN (para el ESP32):** `http://<IP_DE_LA_LAPTOP>:8090`
* **Usuario Administrador:** `ludwinggunnarpv24@gmail.com`
* **Contraseña por defecto:** `admin123`

---

## 📡 Conexión y Firmware del ESP32-S3

El firmware completo para cargar en **Arduino IDE** se encuentra en:
📂 `esp32_firmware/esp32_firmware.ino`

### Características del Firmware:
* **Portal Cautivo (WiFiManager Integrado):** Si el ESP32 cambia de red o no puede conectarse, levanta su propio punto de acceso Wi-Fi (`ESP32-VACUNA-CONFIG`) en `http://192.168.4.1`, permitiendo configurar SSID, Contraseña y la nueva IP de la Laptop sin necesidad de recompilar código.
* **Activación Forzada del Portal:** Mantener presionado el botón **BOOT (GPIO 0)** durante 3 segundos.
* **Detección de Conexión en Tiempo Real:** Latido de vida (heartbeat) periódico cada 5 segundos y detección de estado de puerta instantáneo ante apertura o cierre.

---

## 📧 Notificaciones por Correo Electrónico

Configura tus credenciales creando un archivo `.env` a partir de `.env.example`:
```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=465
SMTP_USER=tu_correo@gmail.com
SMTP_PASS=tu_contraseña_de_aplicacion
SMTP_FROM=tu_correo@gmail.com
```

---

## 🛠️ Tecnologías Utilizadas

* **C++ / Arduino IDE / ESP-IDF** (Firmware ESP32-S3).
* **PHP 8.2** (Backend e Ingesta REST API).
* **MariaDB 10.11 / MySQL** (Modelo relacional de datos).
* **Docker & Docker Compose** (Contenedorización).
* **HTML5 / CSS3 / JavaScript Vanilla** (Dashboard en tiempo real sin dependencias externas pesadas).
