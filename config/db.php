<?php
/**
 * config/db.php
 * ---------------------------------------------------------
 * Conexión a la base de datos mediante MySQLi.
 * Se usa MySQLi (sin PDO) para no requerir extensiones
 * adicionales. Laragon incluye MySQLi por defecto.
 *
 * Uso en otros archivos:
 *   require_once __DIR__ . '/../config/db.php';
 *   // $conn ya está disponible como objeto MySQLi
 * ---------------------------------------------------------
 */

// Parámetros de conexión (ajustar si se cambia el entorno)
define('DB_HOST',     'localhost');
define('DB_USER',     'root');       // Usuario por defecto en Laragon
define('DB_PASS',     '');           // Contraseña vacía por defecto en Laragon
define('DB_NAME',     'gestion_bienes');
define('DB_CHARSET',  'utf8mb4');

// Crear la conexión
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar si hubo error de conexión
if ($conn->connect_error) {
    // En producción podrías registrar el error en un log en lugar de mostrarlo
    die('<div style="font-family:sans-serif;padding:2rem;color:#c0392b;">
        <h2>Error de conexión a la base de datos</h2>
        <p>No se pudo conectar a MySQL. Verifica que Laragon esté corriendo y que la base de datos <strong>gestion_bienes</strong> exista.</p>
        <p><small>Detalle: ' . htmlspecialchars($conn->connect_error) . '</small></p>
    </div>');
}

// Establecer el juego de caracteres para la conexión
$conn->set_charset(DB_CHARSET);
