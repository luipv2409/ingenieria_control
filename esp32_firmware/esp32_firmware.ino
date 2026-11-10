/*
 * =========================================================================================
 * SISTEMA PUNATA VACUNA SEGURA - NODO ESP32-S3
 * =========================================================================================
 * Sensores conectados (confirmados por usuario):
 *   - DS18B20 -> Pin 4 (con resistencia PULL-UP de 4.7k a 3.3V)
 *   - MC-38   -> Pin 5 (contacto a GND, usa INPUT_PULLUP)
 *
 * Portal Cautivo Web (WiFiManager):
 *   - Si no hay WiFi o falla la conexión, o si mantienes pulsado el botón BOOT (GPIO 0)
 *     durante 3 segundos, crea la red WiFi: "ESP32-VACUNA-CONFIG".
 *   - Te conectas desde tu laptop o celular y entras a: http://192.168.4.1
 *   - Configuras la red WiFi y la IP de la laptop sin necesidad de recompilar.
 *
 * Librerías a instalar en Arduino IDE:
 *   - OneWire (Paul Stoffregen)
 *   - DallasTemperature (Miles Burton)
 *   - ArduinoJson (Benoit Blanchon)
 * =========================================================================================
 */

#include <WiFi.h>
#include <WebServer.h>
#include <DNSServer.h>
#include <HTTPClient.h>
#include <Preferences.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <ArduinoJson.h>
#include <mbedtls/sha256.h>
#include <time.h>

// ================= PINES DEFINIDOS =================
#define DS18B20_PIN     4   // Pin de datos para sensor de temperatura DS18B20
#define MC38_PIN        5   // Pin de señal para sensor magnético de puerta MC-38
#define BOOT_BUTTON_PIN 0   // Botón BOOT integrado en la placa para abrir el portal
#define LED_INDICATOR   2   // LED onboard (o GPIO 2)

// Clave secreta compartida con el backend PHP (definida en database.php)
const char* API_SECRET = "punata-vacuna-segura-2026";

// Instancias de hardware
OneWire oneWire(DS18B20_PIN);
DallasTemperature sensors(&oneWire);
Preferences prefs;
WebServer server(80);
DNSServer dnsServer;

// Variables de configuración persistentes en la memoria flash
String cfg_ssid       = "";
String cfg_pass       = "";
String cfg_server_ip  = "192.168.1.36"; // IP de la Laptop
int    cfg_server_port = 8090;          // Puerto del contenedor Docker
String cfg_nodo_codigo = "NODO-REMOTO-01";
String cfg_sensor_serie = "DS18B20-LC-1";
int    cfg_intervalo_seg = 5;           // Envío cada 5 segundos

// Estado operativo
bool modoConfiguracion = false;
unsigned long ultimoEnvio = 0;
bool ultimoEstadoPuerta = false; // false = CERRADA, true = ABIERTA
unsigned long tiempoPulsadoBoot = 0;

// ================= CÁLCULO DE TOKEN SHA-256 =================
String calcularTokenDispositivo(String codigo) {
  codigo.toUpperCase();
  String entrada = codigo + API_SECRET;

  byte hash[32];
  mbedtls_sha256_context ctx;
  mbedtls_sha256_init(&ctx);
  mbedtls_sha256_starts(&ctx, 0); // 0 = SHA-256
  mbedtls_sha256_update(&ctx, (const unsigned char*)entrada.c_str(), entrada.length());
  mbedtls_sha256_finish(&ctx, hash);
  mbedtls_sha256_free(&ctx);

  char hashStr[65];
  for (int i = 0; i < 32; i++) {
    sprintf(hashStr + (i * 2), "%02x", hash[i]);
  }
  hashStr[64] = 0;
  return String(hashStr);
}

// Obtener fecha/hora si se sincronizó con NTP
String obtenerFechaHoraActual() {
  struct tm timeinfo;
  if (!getLocalTime(&timeinfo, 100)) {
    return "";
  }
  char buffer[30];
  strftime(buffer, sizeof(buffer), "%Y-%m-%d %H:%M:%S", &timeinfo);
  return String(buffer);
}

// ================= MODO PORTAL CAUTIVO DE CONFIGURACIÓN =================
void iniciarModoConfiguracion() {
  modoConfiguracion = true;
  WiFi.disconnect();
  WiFi.mode(WIFI_AP);
  
  String apName = "ESP32-VACUNA-CONFIG";
  WiFi.softAP(apName.c_str());
  IPAddress apIP(192, 168, 4, 1);
  WiFi.softAPConfig(apIP, apIP, IPAddress(255, 255, 255, 0));

  dnsServer.start(53, "*", apIP); // Redirige automáticamente peticiones DNS al portal

  // Página web del portal
  server.on("/", HTTP_GET, []() {
    String html = R"rawhtml(
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Configuración ESP32 - Cadena de Frío</title>
<style>
  * { box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
  body { background: #f0f4f8; margin: 0; padding: 20px; display: flex; justify-content: center; }
  .card { background: white; max-width: 440px; width: 100%; border-radius: 12px; padding: 24px; box-shadow: 0 4px 14px rgba(0,0,0,0.08); }
  h2 { color: #1e3a8a; margin-top: 0; text-align: center; }
  .sub { font-size: 13px; color: #64748b; text-align: center; margin-bottom: 20px; }
  label { display: block; font-weight: 600; font-size: 13px; margin: 10px 0 4px; color: #334155; }
  input { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; }
  input:focus { outline: none; border-color: #2563eb; }
  .btn { margin-top: 20px; width: 100%; background: #2563eb; color: white; border: none; padding: 12px; font-size: 15px; font-weight: bold; border-radius: 6px; cursor: pointer; }
  .btn:hover { background: #1d4ed8; }
  .box { background: #eff6ff; border-left: 4px solid #3b82f6; padding: 10px; font-size: 12px; color: #1e40af; margin-bottom: 12px; border-radius: 4px; }
</style>
</head>
<body>
<div class="card">
  <h2>❄ Punata Vacuna Segura</h2>
  <div class="sub">Configuración de Red e IP del Servidor Docker</div>
  <div class="box">
    Cambia la red WiFi o la IP de la laptop fácilmente desde tu celular o computadora.
  </div>
  <form method="POST" action="/guardar">
    <label>Nombre de Red WiFi (SSID):</label>
    <input type="text" name="ssid" value=")rawhtml" + cfg_ssid + R"rawhtml(" required placeholder="Ej: RedDeMiCasa">

    <label>Contraseña WiFi:</label>
    <input type="password" name="pass" value=")rawhtml" + cfg_pass + R"rawhtml(" placeholder="Contraseña">

    <label>IP de la Laptop (Servidor Docker):</label>
    <input type="text" name="ip" value=")rawhtml" + cfg_server_ip + R"rawhtml(" required placeholder="Ej: 192.168.1.36">

    <label>Puerto HTTP:</label>
    <input type="number" name="port" value=")rawhtml" + String(cfg_server_port) + R"rawhtml(" required placeholder="8090">

    <label>Código del Dispositivo:</label>
    <input type="text" name="codigo" value=")rawhtml" + cfg_nodo_codigo + R"rawhtml(" required>

    <label>Serie Sensor DS18B20:</label>
    <input type="text" name="sensor" value=")rawhtml" + cfg_sensor_serie + R"rawhtml(" required>

    <label>Intervalo de Transmisión (segundos):</label>
    <input type="number" name="intervalo" value=")rawhtml" + String(cfg_intervalo_seg) + R"rawhtml(" min="2" max="60" required>

    <button class="btn" type="submit">Guardar y Conectar</button>
  </form>
</div>
</body>
</html>
)rawhtml";
    server.send(200, "text/html", html);
  });

  // Guardar en Preferences y reiniciar
  server.on("/guardar", HTTP_POST, []() {
    if (server.hasArg("ssid")) cfg_ssid = server.arg("ssid");
    if (server.hasArg("pass")) cfg_pass = server.arg("pass");
    if (server.hasArg("ip"))   cfg_server_ip = server.arg("ip");
    if (server.hasArg("port")) cfg_server_port = server.arg("port").toInt();
    if (server.hasArg("codigo")) cfg_nodo_codigo = server.arg("codigo");
    if (server.hasArg("sensor")) cfg_sensor_serie = server.arg("sensor");
    if (server.hasArg("intervalo")) cfg_intervalo_seg = server.arg("intervalo").toInt();

    prefs.begin("pvs_cfg", false);
    prefs.putString("ssid", cfg_ssid);
    prefs.putString("pass", cfg_pass);
    prefs.putString("ip", cfg_server_ip);
    prefs.putInt("port", cfg_server_port);
    prefs.putString("codigo", cfg_nodo_codigo);
    prefs.putString("sensor", cfg_sensor_serie);
    prefs.putInt("intervalo", cfg_intervalo_seg);
    prefs.end();

    String html = R"rawhtml(
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Guardado</title>
<style>body{font-family:sans-serif;text-align:center;padding:40px;background:#f0fdf4;color:#166534;}</style>
</head>
<body>
  <h2>✔ Parámetros Guardados</h2>
  <p>El ESP32 se reiniciará para conectarse a: <strong>)rawhtml" + cfg_ssid + R"rawhtml(</strong></p>
  <p>Enviando datos a: <strong>http://)rawhtml" + cfg_server_ip + ":" + String(cfg_server_port) + R"rawhtml(</strong></p>
</body>
</html>
)rawhtml";
    server.send(200, "text/html", html);
    delay(1500);
    ESP.restart();
  });

  server.onNotFound([]() {
    server.sendHeader("Location", "http://192.168.4.1/", true);
    server.send(302, "text/plain", "");
  });

  server.begin();
  Serial.println("\n[PORTAL] ¡Modo de Configuración Activo!");
  Serial.println("[PORTAL] 1. Conéctate a la red WiFi: ESP32-VACUNA-CONFIG");
  Serial.println("[PORTAL] 2. Abre en tu navegador: http://192.168.4.1");
}

// ================= TRANSMISIÓN HTTP POST HACIA DOCKER =================
void enviarTelemetria(float temperatura, bool puertaAbierta, bool sensorError) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[HTTP] WiFi no conectado. Omitiendo envio.");
    return;
  }

  HTTPClient http;
  String url = "http://" + cfg_server_ip + ":" + String(cfg_server_port) + "/api/ingesta.php";
  
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(4000);

  // Construir JSON
  StaticJsonDocument<512> doc;
  doc["codigo"] = cfg_nodo_codigo;
  doc["token"]  = calcularTokenDispositivo(cfg_nodo_codigo);

  // Array de lecturas de temperatura
  JsonArray lecturas = doc.createNestedArray("lecturas");
  JsonObject l1 = lecturas.createNestedObject();
  l1["sensor"] = cfg_sensor_serie;
  
  if (sensorError) {
    l1["valor"] = 0.0;
    l1["calidad"] = "ERROR";
  } else {
    l1["valor"] = serialized(String(temperatura, 2));
    l1["calidad"] = (temperatura > -50.0 && temperatura < 85.0) ? "VALIDO" : "ERROR";
  }
  
  String fechaActual = obtenerFechaHoraActual();
  if (fechaActual != "") {
    l1["fecha"] = fechaActual;
  }

  // Estado de puerta magnética (MC-38)
  JsonObject p = doc.createNestedObject("puerta");
  p["abierta"] = puertaAbierta;
  if (fechaActual != "") {
    p["fecha"] = fechaActual;
  }

  // Estado de energía
  JsonObject en = doc.createNestedObject("energia");
  en["estado"] = "PRESENTE";
  en["voltaje"] = 220.0;
  if (fechaActual != "") {
    en["fecha"] = fechaActual;
  }

  String requestBody;
  serializeJson(doc, requestBody);

  Serial.print("[HTTP POST] -> ");
  Serial.println(url);
  Serial.println(requestBody);

  int httpCode = http.POST(requestBody);
  if (httpCode > 0) {
    String response = http.getString();
    Serial.printf("[HTTP OK - %d] Respuesta: %s\n", httpCode, response.c_str());
  } else {
    Serial.printf("[HTTP ERROR] %s\n", http.errorToString(httpCode).c_str());
  }
  http.end();
}

// ================= SETUP =================
void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println("\n=== PUNATA VACUNA SEGURA - ESP32-S3 ===");

  // Iniciar pines de hardware
  pinMode(MC38_PIN, INPUT_PULLUP);
  pinMode(BOOT_BUTTON_PIN, INPUT_PULLUP);
  pinMode(LED_INDICATOR, OUTPUT);
  digitalWrite(LED_INDICATOR, LOW);

  // Iniciar sensor DS18B20
  sensors.begin();
  sensors.setWaitForConversion(true);
  Serial.println("Sensores iniciados (DS18B20 en Pin 4, MC-38 en Pin 5)");

  // Cargar configuración guardada de la memoria Flash NVS
  prefs.begin("pvs_cfg", true);
  cfg_ssid        = prefs.getString("ssid", "");
  cfg_pass        = prefs.getString("pass", "");
  cfg_server_ip   = prefs.getString("ip", "192.168.1.36");
  cfg_server_port = prefs.getInt("port", 8090);
  cfg_nodo_codigo = prefs.getString("codigo", "NODO-REMOTO-01");
  cfg_sensor_serie= prefs.getString("sensor", "DS18B20-LC-1");
  cfg_intervalo_seg = prefs.getInt("intervalo", 5);
  prefs.end();

  // Si no hay red guardada o se presiona el botón BOOT al encender -> Portal Cautivo
  if (cfg_ssid == "" || digitalRead(BOOT_BUTTON_PIN) == LOW) {
    Serial.println("[INICIO] Modo configuracion requerido.");
    iniciarModoConfiguracion();
    return;
  }

  // Intentar conectar al WiFi guardado
  Serial.print("[WIFI] Conectando a red: ");
  Serial.println(cfg_ssid);
  WiFi.mode(WIFI_STA);
  WiFi.begin(cfg_ssid.c_str(), cfg_pass.c_str());

  int intentos = 0;
  while (WiFi.status() != WL_CONNECTED && intentos < 25) {
    delay(500);
    Serial.print(".");
    digitalWrite(LED_INDICATOR, !digitalRead(LED_INDICATOR));
    intentos++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[WIFI] ¡Conectado exitosamente!");
    Serial.print("[WIFI] IP asignada al ESP32: ");
    Serial.println(WiFi.localIP());
    digitalWrite(LED_INDICATOR, HIGH);

    // Sincronizar hora por NTP (UTC-4 Bolivia)
    configTime(-4 * 3600, 0, "pool.ntp.org", "time.google.com");
  } else {
    Serial.println("\n[WIFI] No se pudo conectar. Iniciando portal cautivo...");
    iniciarModoConfiguracion();
  }

  // Estado inicial del MC-38: HIGH = ABIERTA, LOW = CERRADA
  ultimoEstadoPuerta = (digitalRead(MC38_PIN) == HIGH);
}

// ================= LOOP =================
void loop() {
  // 1. Manejo del Portal Cautivo (si está en modo AP)
  if (modoConfiguracion) {
    dnsServer.processNextRequest();
    server.handleClient();

    // Parpadeo rápido del LED indicador
    static unsigned long tLed = 0;
    if (millis() - tLed > 200) {
      tLed = millis();
      digitalWrite(LED_INDICATOR, !digitalRead(LED_INDICATOR));
    }
    return;
  }

  // 2. Mantener pulsado botón BOOT por 3 segundos para reconfigurar WiFi/IP
  if (digitalRead(BOOT_BUTTON_PIN) == LOW) {
    if (tiempoPulsadoBoot == 0) tiempoPulsadoBoot = millis();
    if (millis() - tiempoPulsadoBoot > 3000) {
      Serial.println("\n[BOOT] Boton presionado 3s. Abriendo portal de configuracion...");
      iniciarModoConfiguracion();
      return;
    }
  } else {
    tiempoPulsadoBoot = 0;
  }

  // 3. Lectura del sensor magnético MC-38 (Detección de eventos en tiempo real)
  // LOW = Imán pegado (puerta cerrada)
  // HIGH = Imán separado (puerta abierta)
  bool estadoPuertaActual = (digitalRead(MC38_PIN) == HIGH);
  bool cambioPuerta = (estadoPuertaActual != ultimoEstadoPuerta);

  // 4. Temporizador de envío periódico o inmediato ante apertura/cierre
  unsigned long ahora = millis();
  if (ahora - ultimoEnvio >= (unsigned long)(cfg_intervalo_seg * 1000) || cambioPuerta) {
    ultimoEnvio = ahora;
    ultimoEstadoPuerta = estadoPuertaActual;

    // Leer temperatura del DS18B20
    sensors.requestTemperatures();
    float temperatura = sensors.getTempCByIndex(0);
    bool sensorError = (temperatura == DEVICE_DISCONNECTED_C);

    Serial.print("Temperatura: ");
    if (sensorError) {
      Serial.println("Error de sensor (desconectado)");
    } else {
      Serial.print(temperatura);
      Serial.println(" °C");
    }

    Serial.print("Puerta: ");
    Serial.println(estadoPuertaActual ? "ABIERTA" : "CERRADA");

    // Enviar datos vía WiFi a Docker
    enviarTelemetria(temperatura, estadoPuertaActual, sensorError);
  }

  delay(50);
}
