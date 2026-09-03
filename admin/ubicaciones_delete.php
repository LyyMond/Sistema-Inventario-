<?php
/**
 * admin/ubicaciones_delete.php
 * Eliminar ubicación. Evita eliminar si tiene bienes asociados (FK).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Administrador', '..');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    // Check if there are items using this location
    $chk = $conn->prepare("SELECT ID FROM Inventario WHERE Ubicacion_ID = ? LIMIT 1");
    $chk->bind_param('i', $id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $chk->close();
        header('Location: ubicaciones.php?ok=err_del');
        exit;
    }
    $chk->close();

    $stmt = $conn->prepare("DELETE FROM Ubicaciones WHERE ID = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

header('Location: ubicaciones.php?ok=delete');
exit;
