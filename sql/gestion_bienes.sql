-- ============================================================
-- Sistema de Gestión de Tickets e Inventario
-- Archivo: sql/gestion_bienes.sql
-- Descripción: Script completo de creación de la base de datos,
--              tablas y datos de prueba iniciales.
-- Motor: MySQL / MariaDB (compatible con Laragon)
-- ============================================================

-- Crear la base de datos si no existe
DROP DATABASE IF EXISTS gestion_bienes;
CREATE DATABASE IF NOT EXISTS gestion_bienes
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Usar la base de datos
USE gestion_bienes;

-- ============================================================
-- 1. Tabla Perfiles
-- Almacena los datos personales y el rol de cada usuario.
-- La cédula es la llave primaria y sirve como identificador
-- único de cada persona en todo el sistema.
-- ============================================================
CREATE TABLE IF NOT EXISTS Perfiles (
    Cedula   VARCHAR(8)                                   PRIMARY KEY,
    Rol      ENUM('Administrador', 'Operador', 'Tecnico') NOT NULL,
    Activo   BOOLEAN                                      DEFAULT TRUE,
    Nombre   VARCHAR(100)                                 NOT NULL,
    Cre_Per  DATETIME                                     DEFAULT CURRENT_TIMESTAMP,
    Upd_Per  DATETIME                                     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 2. Tabla Tecnicos
-- Relaciona a los perfiles con rol "Tecnico" con un ID
-- interno (CIT) que se usa para asignar técnicos a tickets.
-- ============================================================
CREATE TABLE IF NOT EXISTS Tecnicos (
    CIT    INT         AUTO_INCREMENT PRIMARY KEY,
    Cedula VARCHAR(8)  NOT NULL,
    FOREIGN KEY (Cedula) REFERENCES Perfiles(Cedula)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 3. Tabla Ubicaciones
-- Catálogo de ubicaciones (salas) institucionales.
-- ============================================================
CREATE TABLE IF NOT EXISTS Ubicaciones (
    ID      INT          AUTO_INCREMENT PRIMARY KEY,
    Nombre  VARCHAR(100) NOT NULL,
    Cre_Ubi DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 3.5. Tabla Inventario
-- Catálogo de bienes institucionales con campos detallados.
-- ============================================================
CREATE TABLE IF NOT EXISTS Inventario (
    ID             INT          AUTO_INCREMENT PRIMARY KEY,
    Cod_bien       VARCHAR(50)  UNIQUE NOT NULL,
    Cantidad       INT          DEFAULT 1,
    Num_Bien       VARCHAR(50),
    Descripcion    VARCHAR(200) NOT NULL,
    Inv_Codigo     VARCHAR(50),
    Inv_Concepto   VARCHAR(100),
    Valor_Unitario DECIMAL(10,2),
    Valor_Total    DECIMAL(10,2),
    Ubicacion_ID   INT,
    Fecha_Agregado DATETIME     DEFAULT CURRENT_TIMESTAMP,
    Upd_Inv        DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (Ubicacion_ID) REFERENCES Ubicaciones(ID) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 4. Tabla Tickets
-- Cada ticket representa una solicitud de soporte vinculada
-- a un bien del inventario. El campo CIT vincula al técnico
-- asignado (NULL si aún no se ha asignado).
-- Estados: Inicio → En proceso → Finalizado
-- ============================================================
CREATE TABLE IF NOT EXISTS Tickets (
    Tck_ID    INT          AUTO_INCREMENT PRIMARY KEY,
    Cod_bien  VARCHAR(50)  NOT NULL,
    Des_Falla VARCHAR(100),
    Prioridad ENUM('Baja', 'Media', 'Urgente')              DEFAULT 'Baja',
    Estado    ENUM('Inicio', 'En proceso', 'Finalizado')    DEFAULT 'Inicio',
    Nombre    VARCHAR(100),                -- Nombre del solicitante
    Cre_Tic   DATETIME                    DEFAULT CURRENT_TIMESTAMP,
    CIT       INT,                         -- Técnico asignado (FK)
    Sugerencias VARCHAR(120),
    FOREIGN KEY (Cod_bien) REFERENCES Inventario(Cod_bien) ON DELETE CASCADE,
    FOREIGN KEY (CIT)      REFERENCES Tecnicos(CIT)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 5. Tabla Tickets_Historial
-- Registra cada cambio de estado de un ticket como un log
-- inmutable. Est_An = estado anterior, Est_Ac = estado nuevo.
-- ============================================================
CREATE TABLE IF NOT EXISTS Tickets_Historial (
    ID      INT         AUTO_INCREMENT PRIMARY KEY,
    Tck_ID  INT         NOT NULL,
    Est_Ac  VARCHAR(50),   -- Estado actual (después del cambio)
    Est_An  VARCHAR(50),   -- Estado anterior (antes del cambio)
    Fecha   DATETIME    DEFAULT CURRENT_TIMESTAMP,
    CIT     INT,           -- Técnico asignado / responsable
    FOREIGN KEY (Tck_ID) REFERENCES Tickets(Tck_ID) ON DELETE CASCADE,
    FOREIGN KEY (CIT)    REFERENCES Tecnicos(CIT) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 6. Tabla Usuarios
-- Guarda las credenciales de acceso y las respuestas de seguridad.
-- ============================================================
CREATE TABLE IF NOT EXISTS Usuarios (
    Cedula     VARCHAR(8)  NOT NULL,
    Contrasena VARCHAR(64) NOT NULL,   -- SHA2-256 hash (64 chars hex)
    Pregunta1_Rpta VARCHAR(255) NOT NULL,
    Pregunta2_Rpta VARCHAR(255) NOT NULL,
    Pregunta3_Rpta VARCHAR(255) NOT NULL,
    Pregunta4_Rpta VARCHAR(255) NOT NULL,
    Pregunta5_Rpta VARCHAR(255) NOT NULL,
    PRIMARY KEY (Cedula, Contrasena),
    FOREIGN KEY (Cedula) REFERENCES Perfiles(Cedula) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DATOS DE PRUEBA INICIALES
-- Contraseña de todos los usuarios de prueba: "123456"
-- SHA2('123456', 256) = '8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92'
-- ============================================================

-- Perfiles de prueba
INSERT IGNORE INTO Perfiles (Cedula, Rol, Nombre) VALUES
    ('00000001', 'Administrador', 'Admin Principal'),
    ('00000002', 'Tecnico',       'Carlos Técnico'),
    ('00000003', 'Operador',      'María Operadora'),
    ('00000004', 'Tecnico',       'Luis Técnico');

-- Credenciales de prueba (contraseña: 123456, respuestas de seguridad por defecto)
INSERT IGNORE INTO Usuarios (Cedula, Contrasena, Pregunta1_Rpta, Pregunta2_Rpta, Pregunta3_Rpta, Pregunta4_Rpta, Pregunta5_Rpta) VALUES
    ('00000001', '8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92', 'apodo', 'ciudad', 'perro', 'colegio', 'amigo'),
    ('00000002', '8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92', 'apodo', 'ciudad', 'perro', 'colegio', 'amigo'),
    ('00000003', '8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92', 'apodo', 'ciudad', 'perro', 'colegio', 'amigo'),
    ('00000004', '8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92', 'apodo', 'ciudad', 'perro', 'colegio', 'amigo');

-- Registrar técnicos
INSERT IGNORE INTO Tecnicos (Cedula) VALUES
    ('00000002'),
    ('00000004');

-- Ubicaciones reales
INSERT IGNORE INTO Ubicaciones (ID, Nombre) VALUES
    (1, 'Coordinación General'),
    (2, 'Dormitorio de coordinación'),
    (3, 'Dormitorio caballeros'),
    (4, 'Dormitorio damas'),
    (5, 'Secretaría'),
    (6, 'Recepción'),
    (7, 'Sala situacional'),
    (8, 'Sala de vigilancia'),
    (9, 'Sala de atención y despacho'),
    (10, 'Comedor'),
    (11, 'Transformador Paud Mounted'),
    (12, 'Cuarto de potencia'),
    (13, 'Cuarto de batería'),
    (14, 'Cuarto de hidroneumático'),
    (15, 'Motogenerador'),
    (16, 'Cuarto de tablero'),
    (17, 'Administrativo 01'),
    (18, 'Administrativo 02'),
    (19, 'Administrativo 03'),
    (20, 'Data center'),
    (21, 'Hall-Recepción'),
    (22, 'Lavamopa'),
    (23, 'Cupaz'),
    (24, 'Depósito'),
    (25, 'Tecnología'),
    (26, 'Unidad de aires'),
    (27, 'Uri'),
    (28, 'Bienes y materiales totales');
