<?php
/**
 * admin/inventario_delete.php
 * Elimina un bien del inventario por ID.
 * Solo acepta GET con ?id=N (la confirmación se hace via modal JS en inventario.php).
 * Al eliminar un bien, los tickets asociados se eliminan en cascada (ON DELETE CASCADE).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Administrador', '..');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM Inventario WHERE ID = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

header('Location: inventario.php?ok=delete');
exit;
