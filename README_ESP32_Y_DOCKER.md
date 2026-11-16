# Guía de Puesta en Marcha: Docker y ESP32-S3 (Punata Vacuna Segura)

## 1. Acceso al Sistema Web en Docker

El sistema ya se encuentra levantado y operativo en contenedores Docker independientes sin colisionar con ningún servicio previo (XAMPP, PostgreSQL u otros):

- **URL de Acceso Web**: [http://localhost:8090](http://localhost:8090)
- **URL en Red Local (para el ESP32 y otros dispositivos)**: `http://<IP_DE_TU_LAPTOP>:8090` (Ejemplo actual: `http://192.168.1.36:8090`)
- **Puerto Web**: `8090` (Mapeado a Apache en el contenedor)
- **Puerto Base de Datos MySQL/MariaDB**: `3308` (Para evitar conflicto con el puerto 3306 de XAMPP)

### Credenciales de acceso al Panel Web:
- **Correo**: `ludwinggunnarpv24@gmail.com`
- **Contraseña**: `admin123`

> 📘 **Documentación Maestra Completa:** Consulta [DOCUMENTACION_SISTEMA_COMPLETA.md](DOCUMENTACION_SISTEMA_COMPLETA.md) para el informe técnico, manuales y presentación.

---

## 2. Sensores: ¿Necesitas alguno adicional?

**Respuesta: NO necesitas ningún sensor adicional.**
Con los dos sensores que tienes (**DS18B20** y **MC-38**) tienes cubierto el **100% de los requerimientos críticos** para el monitoreo de la cadena de frío de vacunas:

1. **DS18B20**: Monitoreo térmico de precisión dentro del refrigerador (rango seguro 2 °C a 8 °C).
2. **MC-38**: Sensor magnético de contacto seco tipo Reed Switch para detección inmediata de apertura y cierre de la puerta.

> **Componente pasivo requerido:**
> Solo necesitas **1 resistencia de 4.7 kΩ** (o entre 4.7 kΩ y 10 kΩ) que actúa como Pull-Up para la línea de datos del DS18B20.  
> El sensor **MC-38 NO requiere resistencia externa** porque el código del ESP32 habilita la resistencia interna `INPUT_PULLUP`.

*(Nota: La base de datos tiene un campo para sensor de corte de energía eléctrica AC, pero el firmware del ESP32 reporta automáticamente `PRESENTE (220V)` mientras el nodo esté encendido, por lo que no es necesario adquirir un módulo adicional).*

---

## 3. Conexión de Pines en el ESP32-S3

| Componente | Pin del Componente | Pin en ESP32-S3 | Nota |
| :--- | :--- | :--- | :--- |
| **DS18B20** | VCC (Rojo) | **3.3V** | Alimentación |
| **DS18B20** | GND (Negro) | **GND** | Tierra común |
| **DS18B20** | DATA (Amarillo/Blanco) | **GPIO 4** | Conectar resistencia de **4.7kΩ entre DATA y 3.3V** |
| **MC-38 (Puerta)** | Cable 1 | **GPIO 5** | Señal de contacto seco (usa PULL-UP interno) |
| **MC-38 (Puerta)** | Cable 2 | **GND** | Cierra a tierra cuando la puerta está cerrada |
| **Botón Configuración** | Botón integrado | **GPIO 0 (BOOT)** | Viene soldado en la placa ESP32-S3 |

---

## 4. Configuración Dinámica de Red WiFi e IP (Portal Cautivo)

Cada vez que cambies de laptop o de red WiFi, las direcciones IP van a cambiar. Para solucionar esto sin tener que modificar código ni recompilar:

1. **Encendido:**
   - Si el ESP32 no tiene WiFi configurado, o no logra conectarse tras unos segundos, o si **mantienes presionado el botón BOOT (GPIO 0) durante 3 segundos**, el ESP32 creará su propia red WiFi:
     - **Nombre de red (SSID):** `ESP32-VACUNA-CONFIG`
     - **Contraseña:** Abierta (sin contraseña)

2. **Ingreso al Portal:**
   - Conéctate a esa red WiFi desde tu celular o laptop.
   - Automáticamente se abrirá el portal cautivo; si no se abre solo, entra en tu navegador a:
     `http://192.168.4.1`

3. **Formulario:**
   - Selecciona o escribe el **Nombre de tu Red WiFi** y la **Contraseña**.
   - Ingresa la **IP de la Laptop** donde está corriendo Docker (ejemplo: `192.168.1.36`).
   - Ingresa el **Puerto** (`8090`).
   - Haz clic en **"Guardar y Conectar"**.

El ESP32 guardará la configuración en su memoria flash no volátil (`Preferences`), se reiniciará automáticamente y empezará a enviar lecturas a la nueva IP.

---

## 5. Carga del Código en Arduino IDE

El archivo con el código fuente se encuentra en:  
`c:\xampp\htdocs\ingenieria_control\esp32_firmware\esp32_firmware.ino`

### Pasos en Arduino IDE:
1. Abre **Arduino IDE**.
2. Ve a **Programa > Incluir Librería > Administrar Bibliotecas...** e instala:
   - `OneWire` (por Paul Stoffregen)
   - `DallasTemperature` (por Miles Burton)
   - `ArduinoJson` (por Benoit Blanchon)
3. Selecciona tu placa en **Herramientas > Placa > ESP32 Arduino > ESP32S3 Dev Module** (o la variante de tu ESP32-S3).
4. Selecciona el puerto COM correspondiente en **Herramientas > Puerto**.
5. Abre el archivo `esp32_firmware/esp32_firmware.ino` y presiona **Subir (Upload)**.
6. Abre el **Monitor Serie** a `115200 baudios` para ver los registros y mensajes de confirmación de envío.

