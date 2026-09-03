<?php
/**
 * admin/usuarios_delete.php
 * Elimina un usuario (Perfil + Usuarios + Tecnicos en cascada) por cédula.
 * No permite eliminar otros Administradores ni al propio admin en sesión.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Administrador', '..');

$cedula = trim($_GET['cedula'] ?? '');

if (!empty($cedula)) {
    // Verificar que no sea admin ni el mismo usuario en sesión
    $stmt = $conn->prepare(
        "SELECT Rol FROM Perfiles WHERE Cedula = ? LIMIT 1"
    );
    $stmt->bind_param('s', $cedula);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $esAdmin   = ($row['Rol'] ?? '') === 'Administrador';
    $esMismo   = $cedula === $_SESSION['cedula'];

    if (!$esAdmin && !$esMismo) {
        // Eliminar (cascada elimina Usuarios y Tecnicos)
        $del = $conn->prepare("DELETE FROM Perfiles WHERE Cedula = ?");
        $del->bind_param('s', $cedula);
        $del->execute();
        $del->close();
    }
}

header('Location: usuarios.php?ok=delete');
exit;
