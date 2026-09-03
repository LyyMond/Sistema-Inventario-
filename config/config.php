<?php
/**
 * config/config.php
 * ---------------------------------------------------------
 * Constantes globales del sistema.
 * Aquí se centralizan todos los valores configurables para
 * facilitar cambios sin tocar múltiples archivos.
 * ---------------------------------------------------------
 */

// Código secreto requerido para registrar un Administrador.
// CAMBIA ESTE VALOR antes de poner el sistema en producción.
define('ADMIN_SECRET_CODE', 'INST2024ADMIN');

// Nombre del sistema (aparece en encabezados y título)
define('APP_NAME', 'Sistema de Gestión de Tickets');

// Versión del sistema
define('APP_VERSION', '1.0.0');

// Zona horaria del servidor
date_default_timezone_set('America/Caracas');
