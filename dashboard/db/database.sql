-- Crear la base de datos
DROP DATABASE IF EXISTS prosper;
CREATE DATABASE prosper CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE prosper;

-- -----------------------------------------------------
-- Tabla `roles` (Roles de usuario)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
  id_rol INT AUTO_INCREMENT PRIMARY KEY,
  nombre_rol VARCHAR(50) NOT NULL UNIQUE
);

-- -----------------------------------------------------
-- Tabla `usuarios`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
  id_usuario INT AUTO_INCREMENT PRIMARY KEY,
  id_rol INT NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  correo VARCHAR(150) NOT NULL UNIQUE,
  contrasena VARCHAR(255) NOT NULL,
  tema_preferido VARCHAR(20) DEFAULT 'light',
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
);

-- -----------------------------------------------------
-- Tabla `movimientos` (Transacciones)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS movimientos (
  id_movimiento INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,
  tipo ENUM('ingreso', 'gasto', 'ahorro') NOT NULL,
  monto DECIMAL(12, 2) NOT NULL,
  fecha DATE NOT NULL,
  categoria VARCHAR(50) NOT NULL,
  descripcion VARCHAR(255),
  metodo_pago VARCHAR(50),
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
);

-- Índices para optimizar consultas del dashboard (balance, estadísticas mensuales)
CREATE INDEX idx_movimientos_fecha ON movimientos(fecha);
CREATE INDEX idx_movimientos_usuario_tipo ON movimientos(id_usuario, tipo);

-- -----------------------------------------------------
-- Tabla `objetivos` (Metas de Ahorro)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS objetivos (
  id_objetivo INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  monto_objetivo DECIMAL(12, 2) NOT NULL,
  monto_actual DECIMAL(12, 2) DEFAULT 0.00,
  fecha_limite DATE NOT NULL,
  icono VARCHAR(10) DEFAULT '🎯',
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
);

-- -----------------------------------------------------
-- Tabla `gastos_fijos`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS gastos_fijos (
  id_gasto_fijo INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  monto DECIMAL(12, 2) NOT NULL,
  monto_pagado_mes DECIMAL(12, 2) DEFAULT 0,
  dia_vencimiento INT DEFAULT 1,
  icono VARCHAR(50) DEFAULT '🏠',
  fecha_ultimo_pago DATE NULL,
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
);

-- -----------------------------------------------------
-- Tabla `notificaciones`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS notificaciones (
  id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,
  tipo ENUM('urgent', 'warning', 'info', 'success') NOT NULL,
  icono VARCHAR(10),
  titulo VARCHAR(255) NOT NULL,
  mensaje TEXT NOT NULL,
  categoria VARCHAR(50),
  leida TINYINT(1) DEFAULT 0,
  accion_texto VARCHAR(50),
  accion_url VARCHAR(255),
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
);

-- =====================================================
-- SEEDERS (Datos iniciales de prueba)
-- =====================================================

-- Insertar roles
INSERT IGNORE INTO roles (id_rol, nombre_rol) VALUES 
(1, 'admin'),
(2, 'usuario');

-- Insertar usuario de prueba
INSERT IGNORE INTO usuarios (id_usuario, id_rol, nombre, correo, contrasena, tema_preferido)
VALUES (1, 2, 'Usuario Feliz', 'usuario@finanzas.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dark');
-- (La contraseña hash corresponde a 'password')

-- Insertar un movimiento de prueba
INSERT INTO movimientos (id_usuario, tipo, monto, fecha, categoria, descripcion, metodo_pago)
VALUES 
(1, 'ingreso', 3500000.00, CURDATE(), 'Salario', 'Pago de nómina quincenal', 'transferencia'),
(1, 'gasto', 120000.00, CURDATE(), 'Alimentación', 'Mercado supermercado', 'tarjeta_debito'),
(1, 'gasto', 45000.00, CURDATE(), 'Transporte', 'Gasolina', 'efectivo');

-- Insertar un objetivo de prueba
INSERT INTO objetivos (id_usuario, nombre, monto_objetivo, monto_actual, fecha_limite, icono)
VALUES 
(1, 'Fondo de Emergencia', 5000000.00, 1500000.00, DATE_ADD(CURDATE(), INTERVAL 6 MONTH), '🛡️');

-- Insertar un gasto fijo de prueba
INSERT INTO gastos_fijos (id_usuario, nombre, monto, dia_vencimiento, icono)
VALUES 
(1, 'Alquiler', 500.00, 5, '🏠');
