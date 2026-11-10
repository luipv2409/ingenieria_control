-- ============================================================
-- PUNATA VACUNA SEGURA
-- Base de datos: punata_vacuna_segura
-- Generada desde el modelo MySQL Workbench:
--   "diagramas_puntada_vacuna (2).mwb"
-- MySQL 5.7+ / MariaDB 10.4+ (XAMPP)
-- ============================================================

CREATE DATABASE IF NOT EXISTS punata_vacuna_segura
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_general_ci;

USE punata_vacuna_segura;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- GEOGRAFIA
-- ------------------------------------------------------------

DROP TABLE IF EXISTS departamento;
CREATE TABLE departamento (
  id_departamento INT(11) NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(100) NOT NULL,
  PRIMARY KEY (id_departamento),
  UNIQUE KEY nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS provincia;
CREATE TABLE provincia (
  id_provincia      INT(11) NOT NULL AUTO_INCREMENT,
  id_departamento   INT(11) NOT NULL,
  nombre            VARCHAR(100) NOT NULL,
  PRIMARY KEY (id_provincia),
  KEY fk_provincia_departamento (id_departamento),
  CONSTRAINT fk_provincia_departamento
    FOREIGN KEY (id_departamento) REFERENCES departamento (id_departamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS municipio;
CREATE TABLE municipio (
  id_municipio    INT(11) NOT NULL AUTO_INCREMENT,
  id_provincia    INT(11) NOT NULL,
  nombre          VARCHAR(100) NOT NULL,
  PRIMARY KEY (id_municipio),
  KEY fk_municipio_provincia (id_provincia),
  CONSTRAINT fk_municipio_provincia
    FOREIGN KEY (id_provincia) REFERENCES provincia (id_provincia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS localidad;
CREATE TABLE localidad (
  id_localidad    INT(11) NOT NULL AUTO_INCREMENT,
  id_municipio    INT(11) NOT NULL,
  nombre          VARCHAR(150) NOT NULL,
  PRIMARY KEY (id_localidad),
  KEY fk_localidad_municipio (id_municipio),
  CONSTRAINT fk_localidad_municipio
    FOREIGN KEY (id_municipio) REFERENCES municipio (id_municipio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- ESTABLECIMIENTOS DE SALUD
-- ------------------------------------------------------------

DROP TABLE IF EXISTS institucion_salud;
CREATE TABLE institucion_salud (
  id_institucion    INT(11) NOT NULL AUTO_INCREMENT,
  id_localidad      INT(11) NOT NULL,
  nombre            VARCHAR(150) NOT NULL,
  tipo_institucion  VARCHAR(80) NULL,
  nivel_atencion    VARCHAR(50) NULL,
  direccion         VARCHAR(250) NULL,
  telefono          VARCHAR(30) NULL,
  estado            TINYINT(1) NULL DEFAULT 1,
  PRIMARY KEY (id_institucion),
  KEY fk_institucion_localidad (id_localidad),
  CONSTRAINT fk_institucion_localidad
    FOREIGN KEY (id_localidad) REFERENCES localidad (id_localidad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- CATALOGOS DE SENSORES
-- ------------------------------------------------------------

DROP TABLE IF EXISTS unidad_medida;
CREATE TABLE unidad_medida (
  id_unidad_medida  INT(11) NOT NULL AUTO_INCREMENT,
  nombre            VARCHAR(50) NOT NULL,
  simbolo           VARCHAR(20) NULL,
  PRIMARY KEY (id_unidad_medida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS tipo_sensor;
CREATE TABLE tipo_sensor (
  id_tipo_sensor    INT(11) NOT NULL AUTO_INCREMENT,
  id_unidad_medida  INT(11) NOT NULL,
  nombre            VARCHAR(80) NOT NULL,
  descripcion       VARCHAR(200) NULL,
  PRIMARY KEY (id_tipo_sensor),
  KEY fk_sensor_unidad (id_unidad_medida),
  CONSTRAINT fk_sensor_unidad
    FOREIGN KEY (id_unidad_medida) REFERENCES unidad_medida (id_unidad_medida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- DISPOSITIVOS Y SENSORES
-- ------------------------------------------------------------

DROP TABLE IF EXISTS dispositivo;
CREATE TABLE dispositivo (
  id_dispositivo      INT(11) NOT NULL AUTO_INCREMENT,
  id_institucion      INT(11) NOT NULL,
  codigo              VARCHAR(50) NOT NULL,
  modelo              VARCHAR(100) NULL,
  numero_serie        VARCHAR(100) NULL,
  fecha_instalacion   DATE NULL,
  estado              VARCHAR(50) NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (id_dispositivo),
  UNIQUE KEY codigo (codigo),
  UNIQUE KEY numero_serie (numero_serie),
  KEY fk_dispositivo_institucion (id_institucion),
  CONSTRAINT fk_dispositivo_institucion
    FOREIGN KEY (id_institucion) REFERENCES institucion_salud (id_institucion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS sensor;
CREATE TABLE sensor (
  id_sensor           INT(11) NOT NULL AUTO_INCREMENT,
  id_dispositivo      INT(11) NOT NULL,
  id_tipo_sensor      INT(11) NOT NULL,
  modelo              VARCHAR(100) NULL,
  numero_serie        VARCHAR(100) NULL,
  fecha_instalacion   DATE NULL,
  estado              VARCHAR(50) NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (id_sensor),
  UNIQUE KEY numero_serie (numero_serie),
  KEY fk_sensor_dispositivo (id_dispositivo),
  KEY fk_sensor_tipo (id_tipo_sensor),
  CONSTRAINT fk_sensor_dispositivo
    FOREIGN KEY (id_dispositivo) REFERENCES dispositivo (id_dispositivo),
  CONSTRAINT fk_sensor_tipo
    FOREIGN KEY (id_tipo_sensor) REFERENCES tipo_sensor (id_tipo_sensor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS ubicacion_dispositivo;
CREATE TABLE ubicacion_dispositivo (
  id_ubicacion_dispositivo INT(11) NOT NULL AUTO_INCREMENT,
  id_dispositivo           INT(11) NOT NULL,
  id_institucion           INT(11) NOT NULL,
  fecha_inicio             DATETIME NOT NULL,
  fecha_fin                DATETIME NULL,
  PRIMARY KEY (id_ubicacion_dispositivo),
  KEY fk_ubicacion_dispositivo (id_dispositivo),
  KEY fk_ubicacion_institucion (id_institucion),
  CONSTRAINT fk_ubicacion_dispositivo
    FOREIGN KEY (id_dispositivo) REFERENCES dispositivo (id_dispositivo),
  CONSTRAINT fk_ubicacion_institucion
    FOREIGN KEY (id_institucion) REFERENCES institucion_salud (id_institucion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS calibracion_sensor;
CREATE TABLE calibracion_sensor (
  id_calibracion     INT(11) NOT NULL AUTO_INCREMENT,
  id_sensor          INT(11) NOT NULL,
  fecha_calibracion  DATETIME NOT NULL,
  valor_referencia   DECIMAL(10,3) NULL,
  observacion        VARCHAR(250) NULL,
  responsable        VARCHAR(150) NULL,
  PRIMARY KEY (id_calibracion),
  KEY fk_calibracion_sensor (id_sensor),
  CONSTRAINT fk_calibracion_sensor
    FOREIGN KEY (id_sensor) REFERENCES sensor (id_sensor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- CATALOGOS DE ALARMAS
-- ------------------------------------------------------------

DROP TABLE IF EXISTS tipo_alarma;
CREATE TABLE tipo_alarma (
  id_tipo_alarma  INT(11) NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(100) NOT NULL,
  descripcion     VARCHAR(200) NULL,
  PRIMARY KEY (id_tipo_alarma)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS estado_alarma;
CREATE TABLE estado_alarma (
  id_estado_alarma  INT(11) NOT NULL AUTO_INCREMENT,
  nombre            VARCHAR(50) NOT NULL,
  PRIMARY KEY (id_estado_alarma)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS protocolo_respuesta;
CREATE TABLE protocolo_respuesta (
  id_protocolo    INT(11) NOT NULL AUTO_INCREMENT,
  id_tipo_alarma  INT(11) NOT NULL,
  nombre          VARCHAR(150) NOT NULL,
  descripcion     TEXT NULL,
  PRIMARY KEY (id_protocolo),
  KEY fk_protocolo_tipo_alarma (id_tipo_alarma),
  CONSTRAINT fk_protocolo_tipo_alarma
    FOREIGN KEY (id_tipo_alarma) REFERENCES tipo_alarma (id_tipo_alarma)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- CONFIGURACION DE CONTROL
-- ------------------------------------------------------------

DROP TABLE IF EXISTS configuracion_control;
CREATE TABLE configuracion_control (
  id_configuracion       INT(11) NOT NULL AUTO_INCREMENT,
  id_institucion         INT(11) NOT NULL,
  temperatura_min        DECIMAL(5,2) NOT NULL,
  temperatura_max        DECIMAL(5,2) NOT NULL,
  tiempo_alerta_minutos  INT(11) NULL DEFAULT 5,
  fecha_inicio           DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_fin              DATETIME NULL,
  estado                 TINYINT(1) NULL DEFAULT 1,
  PRIMARY KEY (id_configuracion),
  KEY fk_configuracion_institucion (id_institucion),
  CONSTRAINT fk_configuracion_institucion
    FOREIGN KEY (id_institucion) REFERENCES institucion_salud (id_institucion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- TELEMETRIA
-- ------------------------------------------------------------

DROP TABLE IF EXISTS lectura_sensor;
CREATE TABLE lectura_sensor (
  id_lectura     BIGINT(20) NOT NULL AUTO_INCREMENT,
  id_sensor      INT(11) NOT NULL,
  fecha_hora     DATETIME NOT NULL,
  valor          DECIMAL(10,3) NOT NULL,
  calidad_dato   VARCHAR(50) NULL DEFAULT 'VALIDO',
  PRIMARY KEY (id_lectura),
  KEY fk_lectura_sensor (id_sensor),
  KEY idx_lectura_fecha (fecha_hora),
  CONSTRAINT fk_lectura_sensor
    FOREIGN KEY (id_sensor) REFERENCES sensor (id_sensor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS estado_energia;
CREATE TABLE estado_energia (
  id_estado_energia  BIGINT(20) NOT NULL AUTO_INCREMENT,
  id_dispositivo     INT(11) NOT NULL,
  fecha_hora         DATETIME NOT NULL,
  estado             VARCHAR(50) NOT NULL,
  voltaje            DECIMAL(10,2) NULL,
  PRIMARY KEY (id_estado_energia),
  KEY fk_energia_dispositivo (id_dispositivo),
  KEY idx_energia_fecha (fecha_hora),
  CONSTRAINT fk_energia_dispositivo
    FOREIGN KEY (id_dispositivo) REFERENCES dispositivo (id_dispositivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS evento_puerta;
CREATE TABLE evento_puerta (
  id_evento_puerta   BIGINT(20) NOT NULL AUTO_INCREMENT,
  id_sensor          INT(11) NOT NULL,
  fecha_inicio       DATETIME NOT NULL,
  fecha_fin          DATETIME NULL,
  duracion_segundos  INT(11) NULL,
  estado             VARCHAR(50) NULL,
  PRIMARY KEY (id_evento_puerta),
  KEY fk_evento_puerta_sensor (id_sensor),
  KEY idx_puerta_inicio (fecha_inicio),
  CONSTRAINT fk_evento_puerta_sensor
    FOREIGN KEY (id_sensor) REFERENCES sensor (id_sensor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- ALARMAS Y RESPUESTA
-- ------------------------------------------------------------

DROP TABLE IF EXISTS alarma;
CREATE TABLE alarma (
  id_alarma        BIGINT(20) NOT NULL AUTO_INCREMENT,
  id_tipo_alarma   INT(11) NOT NULL,
  id_estado_alarma INT(11) NOT NULL,
  id_dispositivo   INT(11) NOT NULL,
  fecha_hora       DATETIME NOT NULL,
  nivel            VARCHAR(50) NULL,
  descripcion      VARCHAR(250) NULL,
  PRIMARY KEY (id_alarma),
  KEY fk_alarma_tipo (id_tipo_alarma),
  KEY fk_alarma_estado (id_estado_alarma),
  KEY fk_alarma_dispositivo (id_dispositivo),
  KEY idx_alarma_fecha (fecha_hora),
  CONSTRAINT fk_alarma_tipo
    FOREIGN KEY (id_tipo_alarma) REFERENCES tipo_alarma (id_tipo_alarma),
  CONSTRAINT fk_alarma_estado
    FOREIGN KEY (id_estado_alarma) REFERENCES estado_alarma (id_estado_alarma),
  CONSTRAINT fk_alarma_dispositivo
    FOREIGN KEY (id_dispositivo) REFERENCES dispositivo (id_dispositivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS notificacion;
CREATE TABLE notificacion (
  id_notificacion  BIGINT(20) NOT NULL AUTO_INCREMENT,
  id_alarma        BIGINT(20) NOT NULL,
  id_usuario       INT(11) NOT NULL,
  fecha_envio      DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  medio            VARCHAR(50) NULL,
  estado           VARCHAR(50) NULL,
  PRIMARY KEY (id_notificacion),
  KEY fk_notificacion_alarma (id_alarma),
  KEY fk_notificacion_usuario (id_usuario),
  CONSTRAINT fk_notificacion_alarma
    FOREIGN KEY (id_alarma) REFERENCES alarma (id_alarma),
  CONSTRAINT fk_notificacion_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS accion_respuesta;
CREATE TABLE accion_respuesta (
  id_accion      BIGINT(20) NOT NULL AUTO_INCREMENT,
  id_alarma      BIGINT(20) NOT NULL,
  id_usuario     INT(11) NOT NULL,
  id_protocolo   INT(11) NOT NULL,
  fecha_hora     DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  observacion    TEXT NULL,
  PRIMARY KEY (id_accion),
  KEY fk_accion_alarma (id_alarma),
  KEY fk_accion_usuario (id_usuario),
  KEY fk_accion_protocolo (id_protocolo),
  CONSTRAINT fk_accion_alarma
    FOREIGN KEY (id_alarma) REFERENCES alarma (id_alarma),
  CONSTRAINT fk_accion_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario),
  CONSTRAINT fk_accion_protocolo
    FOREIGN KEY (id_protocolo) REFERENCES protocolo_respuesta (id_protocolo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- VACUNAS
-- ------------------------------------------------------------

DROP TABLE IF EXISTS vacuna;
CREATE TABLE vacuna (
  id_vacuna         INT(11) NOT NULL AUTO_INCREMENT,
  nombre            VARCHAR(150) NOT NULL,
  fabricante        VARCHAR(150) NULL,
  temperatura_min   DECIMAL(5,2) NULL,
  temperatura_max   DECIMAL(5,2) NULL,
  descripcion       TEXT NULL,
  PRIMARY KEY (id_vacuna)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS lote_vacuna;
CREATE TABLE lote_vacuna (
  id_lote             INT(11) NOT NULL AUTO_INCREMENT,
  id_vacuna           INT(11) NOT NULL,
  codigo_lote         VARCHAR(100) NOT NULL,
  fecha_fabricacion   DATE NULL,
  fecha_vencimiento   DATE NULL,
  cantidad_inicial    INT(11) NOT NULL,
  estado              VARCHAR(50) NULL,
  PRIMARY KEY (id_lote),
  UNIQUE KEY codigo_lote (codigo_lote),
  KEY fk_lote_vacuna (id_vacuna),
  CONSTRAINT fk_lote_vacuna
    FOREIGN KEY (id_vacuna) REFERENCES vacuna (id_vacuna)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS almacenamiento_vacuna;
CREATE TABLE almacenamiento_vacuna (
  id_almacenamiento  BIGINT(20) NOT NULL AUTO_INCREMENT,
  id_lote            INT(11) NOT NULL,
  id_dispositivo     INT(11) NOT NULL,
  fecha_ingreso      DATETIME NOT NULL,
  fecha_salida       DATETIME NULL,
  cantidad           INT(11) NOT NULL,
  PRIMARY KEY (id_almacenamiento),
  KEY fk_almacenamiento_lote (id_lote),
  KEY fk_almacenamiento_dispositivo (id_dispositivo),
  CONSTRAINT fk_almacenamiento_lote
    FOREIGN KEY (id_lote) REFERENCES lote_vacuna (id_lote),
  CONSTRAINT fk_almacenamiento_dispositivo
    FOREIGN KEY (id_dispositivo) REFERENCES dispositivo (id_dispositivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS movimiento_vacuna;
CREATE TABLE movimiento_vacuna (
  id_movimiento     BIGINT(20) NOT NULL AUTO_INCREMENT,
  id_lote           INT(11) NOT NULL,
  id_usuario        INT(11) NOT NULL,
  tipo_movimiento   VARCHAR(50) NOT NULL,
  cantidad          INT(11) NOT NULL,
  fecha_hora        DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  observacion       TEXT NULL,
  PRIMARY KEY (id_movimiento),
  KEY fk_movimiento_lote (id_lote),
  KEY fk_movimiento_usuario (id_usuario),
  CONSTRAINT fk_movimiento_lote
    FOREIGN KEY (id_lote) REFERENCES lote_vacuna (id_lote),
  CONSTRAINT fk_movimiento_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- SEGURIDAD: USUARIOS, ROLES, PERMISOS, AUDITORIA
-- ------------------------------------------------------------

DROP TABLE IF EXISTS usuario;
CREATE TABLE usuario (
  id_usuario       INT(11) NOT NULL AUTO_INCREMENT,
  nombre           VARCHAR(100) NOT NULL,
  apellido         VARCHAR(100) NOT NULL,
  carnet           VARCHAR(30) NULL,
  correo           VARCHAR(150) NULL,
  password_hash    VARCHAR(255) NOT NULL,
  telefono         VARCHAR(30) NULL,
  estado           TINYINT(1) NULL DEFAULT 1,
  fecha_registro   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_usuario),
  UNIQUE KEY carnet (carnet),
  UNIQUE KEY correo (correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS rol;
CREATE TABLE rol (
  id_rol       INT(11) NOT NULL AUTO_INCREMENT,
  nombre       VARCHAR(80) NOT NULL,
  descripcion  VARCHAR(200) NULL,
  PRIMARY KEY (id_rol),
  UNIQUE KEY nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS permiso;
CREATE TABLE permiso (
  id_permiso    INT(11) NOT NULL AUTO_INCREMENT,
  nombre        VARCHAR(100) NOT NULL,
  descripcion   VARCHAR(200) NULL,
  PRIMARY KEY (id_permiso),
  UNIQUE KEY nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS usuario_rol;
CREATE TABLE usuario_rol (
  id_usuario_rol  INT(11) NOT NULL AUTO_INCREMENT,
  id_usuario      INT(11) NOT NULL,
  id_rol          INT(11) NOT NULL,
  PRIMARY KEY (id_usuario_rol),
  KEY fk_usuario_rol_usuario (id_usuario),
  KEY fk_usuario_rol_rol (id_rol),
  CONSTRAINT fk_usuario_rol_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario),
  CONSTRAINT fk_usuario_rol_rol
    FOREIGN KEY (id_rol) REFERENCES rol (id_rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS rol_permiso;
CREATE TABLE rol_permiso (
  id_rol_permiso  INT(11) NOT NULL AUTO_INCREMENT,
  id_rol          INT(11) NOT NULL,
  id_permiso      INT(11) NOT NULL,
  PRIMARY KEY (id_rol_permiso),
  KEY fk_rp_rol (id_rol),
  KEY fk_rp_permiso (id_permiso),
  CONSTRAINT fk_rp_rol
    FOREIGN KEY (id_rol) REFERENCES rol (id_rol),
  CONSTRAINT fk_rp_permiso
    FOREIGN KEY (id_permiso) REFERENCES permiso (id_permiso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS auditoria_usuario;
CREATE TABLE auditoria_usuario (
  id_auditoria  INT(11) NOT NULL AUTO_INCREMENT,
  id_usuario    INT(11) NULL,
  accion        VARCHAR(150) NOT NULL,
  modulo        VARCHAR(100) NULL,
  fecha_hora    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_origen     VARCHAR(50) NULL,
  PRIMARY KEY (id_auditoria),
  KEY fk_auditoria_usuario (id_usuario),
  CONSTRAINT fk_auditoria_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
