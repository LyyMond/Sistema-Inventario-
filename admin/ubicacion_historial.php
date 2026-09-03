<?php
/**
 * admin/ubicacion_historial.php
 * Muestra el historial de tickets de todos los bienes de una sala específica.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Administrador', '..');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    header('Location: inventario.php');
    exit;
}

// Obtener detalles de la ubicación
$stmtU = $conn->prepare("SELECT Nombre FROM Ubicaciones WHERE ID = ? LIMIT 1");
$stmtU->bind_param('i', $id);
$stmtU->execute();
$resU = $stmtU->get_result();
if ($resU->num_rows === 0) {
    header('Location: inventario.php');
    exit;
}
$ubicacion = $resU->fetch_assoc()['Nombre'];
$stmtU->close();

// Obtener tickets de los bienes en esa ubicación
$sql = "SELECT t.Tck_ID, t.Cod_bien, t.Des_Falla, t.Prioridad, t.Estado, t.Cre_Tic, 
               i.Descripcion AS NombreBien, p.Nombre AS NomTecnico
        FROM Tickets t
        JOIN Inventario i ON t.Cod_bien = i.Cod_bien
        LEFT JOIN Tecnicos tec ON t.CIT = tec.CIT
        LEFT JOIN Perfiles p ON tec.Cedula = p.Cedula
        WHERE i.Ubicacion_ID = ?
        ORDER BY t.Cre_Tic DESC";

$stmtT = $conn->prepare($sql);
$stmtT->bind_param('i', $id);
$stmtT->execute();
$tickets = $stmtT->get_result();

$pageTitle = 'Historial de Sala';
$basePath  = '..';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Historial de Sala: <?= htmlspecialchars($ubicacion) ?></h1>
        <p>Historial de reparaciones (tickets) de los bienes en esta ubicación.</p>
    </div>
    <a href="inventario.php?ubicacion=<?= $id ?>" class="btn btn-secondary">← Volver al Inventario</a>
</div>

<div class="bento-card">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th># Ticket</th>
                    <th>Cod. Bien</th>
                    <th>Bien Afectado</th>
                    <th>Falla Reportada</th>
                    <th>Prioridad</th>
                    <th>Estado</th>
                    <th>Técnico Asignado</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($t = $tickets->fetch_assoc()): ?>
            <tr>
                <td><strong>#<?= str_pad($t['Tck_ID'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                <td><?= htmlspecialchars($t['Cod_bien']) ?></td>
                <td><?= htmlspecialchars($t['NombreBien']) ?></td>
                <td><?= htmlspecialchars($t['Des_Falla']) ?></td>
                <td><span class="badge priority-<?= strtolower($t['Prioridad']) ?>"><?= $t['Prioridad'] ?></span></td>
                <td><span class="badge status-<?= str_replace(' ', '-', strtolower($t['Estado'])) ?>"><?= $t['Estado'] ?></span></td>
                <td><?= $t['NomTecnico'] ? htmlspecialchars($t['NomTecnico']) : '<em class="text-muted">Sin asignar</em>' ?></td>
                <td><?= date('d/m/Y H:i', strtotime($t['Cre_Tic'])) ?></td>
            </tr>
            <?php endwhile; ?>
            <?php if ($tickets->num_rows === 0): ?>
            <tr><td colspan="8" class="text-center text-muted" style="padding:2rem">No hay historial de tickets para esta sala.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php 
$stmtT->close();
require_once __DIR__ . '/../includes/footer.php'; 
?>
