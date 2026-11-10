-- ============================================================
-- PUNATA VACUNA SEGURA - DATOS SEMILLA (LIMPIO - 1 SOLO ESP32)
-- ============================================================

USE punata_vacuna_segura;

-- ------------------------------------------------------------
-- 1. GEOGRAFIA (Cochabamba / Punata)
-- ------------------------------------------------------------
INSERT INTO departamento (nombre) VALUES ('Cochabamba');
INSERT INTO provincia (id_departamento, nombre) VALUES (1, 'Punata');
INSERT INTO municipio (id_provincia, nombre) VALUES (1, 'Punata');

INSERT INTO localidad (id_municipio, nombre) VALUES
 (1, 'Punata'),
 (1, 'La Capilla'),
 (1, 'Barrientos Grande'),
 (1, 'Chirusi'),
 (1, 'Laguna Centro'),
 (1, 'San José'),
 (1, 'Tambillo'),
 (1, 'Sulty Grande'),
 (1, 'Kjochi'),
 (1, 'Villa Carmen');

-- ------------------------------------------------------------
-- 2. ESTABLECIMIENTOS DE SALUD
-- ------------------------------------------------------------
INSERT INTO institucion_salud
 (id_localidad, nombre, tipo_institucion, nivel_atencion, direccion, telefono, estado) VALUES
 (2, 'Centro de Salud La Capilla', 'Centro de Salud', 'Primer nivel rural',
  'La Capilla, Municipio de Punata, Cochabamba', '4662101', 1),
 (1, 'Hospital Manuel Ascencio Villarroel de Punata', 'Hospital', 'Segundo nivel',
  'Av. Principal, Punata, Cochabamba', '4662000', 1);

-- ------------------------------------------------------------
-- 3. UNIDADES DE MEDIDA Y TIPOS DE SENSOR
-- ------------------------------------------------------------
INSERT INTO unidad_medida (nombre, simbolo) VALUES
 ('Grado Celsius', '°C'),
 ('Voltio', 'V'),
 ('Estado', 'ON/OFF'),
 ('Segundo', 's');

INSERT INTO tipo_sensor (id_unidad_medida, nombre, descripcion) VALUES
 (1, 'TEMPERATURA',    'Sonda digital de temperatura para refrigerador de vacunas'),
 (2, 'PUERTA',         'Sensor magnetico MC-38 (reed switch) de apertura de puerta'),
 (3, 'ENERGIA',        'Sensor de presencia de red electrica AC');

-- ------------------------------------------------------------
-- 4. DISPOSITIVOS (ÚNICO NODO ESP32-S3 REAL)
-- ------------------------------------------------------------
INSERT INTO dispositivo (id_institucion, codigo, modelo, numero_serie, fecha_instalacion, estado) VALUES
 (1, 'NODO-REMOTO-01', 'ESP32-S3', 'ESP32-S3-PUNATA-01', CURDATE(), 'ACTIVO');

-- Sensores del único nodo ESP32: DS18B20 (Pin 4) y MC-38 (Pin 5)
INSERT INTO sensor (id_dispositivo, id_tipo_sensor, modelo, numero_serie, fecha_instalacion, estado) VALUES
 (1, 1, 'DS18B20 digital 1-Wire', 'DS18B20-LC-1', CURDATE(), 'ACTIVO'),
 (1, 2, 'MC-38 Magnético Puerta', 'REED-LC-1',    CURDATE(), 'ACTIVO'),
 (1, 3, 'Red Eléctrica AC',        'ACOP-LC-1',    CURDATE(), 'ACTIVO');

INSERT INTO ubicacion_dispositivo (id_dispositivo, id_institucion, fecha_inicio) VALUES
 (1, 1, NOW());

-- ------------------------------------------------------------
-- 5. CATALOGOS DE ALARMAS Y PROTOCOLOS DE RESPUESTA
-- ------------------------------------------------------------
INSERT INTO tipo_alarma (nombre, descripcion) VALUES
 ('TEMPERATURA_ALTA',  'Temperatura por encima del limite autorizado (> 8°C)'),
 ('TEMPERATURA_BAJA',  'Temperatura por debajo del limite autorizado (< 2°C, riesgo de congelacion)'),
 ('PUERTA_ABIERTA',    'Puerta del refrigerador abierta mas del tiempo permitido'),
 ('CORTE_ENERGIA',     'Corte o variacion de la red electrica'),
 ('SIN_COMUNICACION',  'El nodo dejo de transmitir');

INSERT INTO estado_alarma (nombre) VALUES
 ('ACTIVA'),
 ('NOTIFICADA'),
 ('EN_ATENCION'),
 ('RESUELTA'),
 ('FALSA_ALARMA');

INSERT INTO protocolo_respuesta (id_tipo_alarma, nombre, descripcion) VALUES
 (1, 'Protocolo temperatura alta',
  '1. Verificar puerta cerrada y sonda bien ubicada. 2. Revisar energia y compresor. 3. Si la temperatura no retorna en 15 min, trasladar las vacunas al refrigerador de respaldo.'),
 (2, 'Protocolo temperatura baja',
  '1. Verificar si hay senales de congelamiento en el contenido. 2. Revisar calibracion de la sonda. 3. No aplicar vacunas afectadas.'),
 (3, 'Protocolo puerta abierta',
  '1. Cerrar la puerta de inmediato. 2. Registrar duracion de la apertura.'),
 (4, 'Protocolo corte de energia',
  '1. Confirmar el corte con el sensor de energia. 2. Activar respaldo (bateria/UPS). 3. Mantener refrigerador cerrado.'),
 (5, 'Protocolo sin comunicacion',
  '1. Revisar estado de la red WiFi y alimentacion del ESP32. 2. Reconectar.');

-- ------------------------------------------------------------
-- 6. CONFIGURACION DE CONTROL (Rango OPS/OMS: 2 °C a 8 °C)
-- ------------------------------------------------------------
INSERT INTO configuracion_control
 (id_institucion, temperatura_min, temperatura_max, tiempo_alerta_minutos, fecha_inicio, estado) VALUES
 (1, 2.00, 8.00, 5, NOW(), 1),
 (2, 2.00, 8.00, 5, NOW(), 1);

-- ------------------------------------------------------------
-- 7. VACUNAS
-- ------------------------------------------------------------
INSERT INTO vacuna (nombre, fabricante, temperatura_min, temperatura_max, descripcion) VALUES
 ('BCG',                 'Instituto Butantan',       2.00, 8.00, 'Tuberculosis'),
 ('Pentavalente',        'Serum Institute of India', 2.00, 8.00, 'Difteria, tétanos, tos ferina, hepatitis B, haemophilus'),
 ('Polio inactivada IPV','Sanofi Pasteur',           2.00, 8.00, 'Poliomielitis'),
 ('Neumococo PCV13',     'Pfizer',                   2.00, 8.00, 'Enfermedad neumocócica'),
 ('Rotavirus',           'GSK',                      2.00, 8.00, 'Gastroenteritis por rotavirus'),
 ('SRP (Triple viral)',  'Instituto Seroterápico',   2.00, 8.00, 'Sarampión, rubéola, parotiditis'),
 ('Hepatitis B',         'Sinopharm',                2.00, 8.00, 'Hepatitis B');

INSERT INTO lote_vacuna (id_vacuna, codigo_lote, fecha_fabricacion, fecha_vencimiento, cantidad_inicial, estado) VALUES
 (1, 'BCG-2025-014', DATE_SUB(CURDATE(), INTERVAL 2 MONTH), DATE_ADD(CURDATE(), INTERVAL 10 MONTH), 50, 'ACTIVO'),
 (2, 'PENT-2025-088', DATE_SUB(CURDATE(), INTERVAL 1 MONTH), DATE_ADD(CURDATE(), INTERVAL 11 MONTH), 100, 'ACTIVO');

INSERT INTO almacenamiento_vacuna (id_lote, id_dispositivo, fecha_ingreso, cantidad) VALUES
 (1, 1, NOW(), 50),
 (2, 1, NOW(), 100);

-- ------------------------------------------------------------
-- 8. ROLES, PERMISOS Y USUARIOS
-- ------------------------------------------------------------
INSERT INTO rol (nombre, descripcion) VALUES
 ('ADMINISTRADOR',        'Acceso total al sistema'),
 ('COORDINADOR',          'Coordina la red de monitoreo y alarmas'),
 ('TECNICO',              'Instalacion y calibracion de dispositivos'),
 ('SUPERVISOR_SANITARIO', 'Valida protocolos y alarmas'),
 ('OPERADOR',             'Operador local del centro de salud');

INSERT INTO permiso (nombre, descripcion) VALUES
 ('ver_panel',          'Ver el tablero general'),
 ('ver_lecturas',       'Ver historial de lecturas y graficas'),
 ('ver_alarmas',        'Ver alarmas'),
 ('responder_alarmas',  'Registrar acciones de respuesta a alarmas'),
 ('gestionar_dispositivos', 'Alta y mantenimiento de dispositivos y sensores'),
 ('gestionar_vacunas',  'Gestionar vacunas, lotes y movimientos'),
 ('gestionar_usuarios', 'Alta y baja de usuarios y roles'),
 ('ver_reportes',       'Exportar reportes e indicadores'),
 ('configurar_sistema', 'Modificar rangos y configuracion de control'),
 ('auditar',            'Consultar la auditoria del sistema');

-- Matriz rol-permiso
INSERT INTO rol_permiso (id_rol, id_permiso) VALUES
 (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),
 (2,1),(2,2),(2,3),(2,4),(2,6),(2,8),
 (3,1),(3,2),(3,3),(3,5),
 (4,1),(4,2),(4,3),(4,4),(4,6),(4,8),(4,9),
 (5,1),(5,2),(5,3),(5,4);

-- Usuario Administrador principal (contraseña: admin123)
INSERT INTO usuario (nombre, apellido, carnet, correo, password_hash, telefono, estado) VALUES
 ('Gunnar Ludwing', 'Pecho Vallejos', '6543210', 'ludwinggunnarpv24@gmail.com',
  '$2y$10$FmTVSjhZEvLMnGIEghboyuJ2EdY8NyopiOW.jvfFQHF2Dd6suSTNO', '71234567', 1);

INSERT INTO usuario_rol (id_usuario, id_rol) VALUES
 (1, 1),
 (1, 2);

-- Auditoría inicial de instalación
INSERT INTO auditoria_usuario (id_usuario, accion, modulo, ip_origen) VALUES
 (1, 'SISTEMA_INICIALIZADO', 'Sistema', '127.0.0.1');
