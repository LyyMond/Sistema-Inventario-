<?php
/**
 * operador/historial.php
 * Historial de tickets del Operador.
 * Muestra únicamente los tickets creados por el propio operador y su historial de estados.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Operador', '..');

$cedula = $_SESSION['cedula'];

// Obtener todos los tickets de este operador
$stmtT = $conn->prepare(
    "SELECT t.Tck_ID, t.Des_Falla, t.Prioridad, t.Estado, t.Cre_Tic,
            i.Descripcion AS Bien,
            pt.Nombre AS TecnicoActual
     FROM Tickets t
     INNER JOIN Inventario i ON t.Cod_bien = i.Cod_bien
     LEFT JOIN Tecnicos tc ON t.CIT = tc.CIT
     LEFT JOIN Perfiles pt ON tc.Cedula = pt.Cedula
     WHERE t.Nombre = (SELECT Nombre FROM Perfiles WHERE Cedula = ? LIMIT 1)
     ORDER BY t.Cre_Tic DESC"
);
$stmtT->bind_param('s', $cedula);
$stmtT->execute();
$tickets = $stmtT->get_result();
$stmtT->close();

// Obtener historial de todos los tickets de este operador
$stmtH = $conn->prepare(
    "SELECT h.Tck_ID, h.Est_An, h.Est_Ac, h.Fecha, p.Nombre AS TecnicoNombre
     FROM Tickets_Historial h
     INNER JOIN Tickets t ON h.Tck_ID = t.Tck_ID
     LEFT JOIN Tecnicos tc ON h.CIT = tc.CIT
     LEFT JOIN Perfiles p ON tc.Cedula = p.Cedula
     WHERE t.Nombre = (SELECT Nombre FROM Perfiles WHERE Cedula = ? LIMIT 1)
     ORDER BY h.Tck_ID ASC, h.Fecha ASC"
);
$stmtH->bind_param('s', $cedula);
$stmtH->execute();
$resH = $stmtH->get_result();
$historiaPorTicket = [];
while ($rowH = $resH->fetch_assoc()) {
    $historiaPorTicket[$rowH['Tck_ID']][] = $rowH;
}
$stmtH->close();

$pageTitle = 'Mi Historial de Tickets';
$basePath  = '..';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Mi Historial de Tickets</h1>
        <p>Historial de solicitudes creadas por ti y su evolución de estados.</p>
    </div>
</div>

<div class="bento-grid" style="grid-template-columns: 1fr; gap: 1.5rem;">
    <!-- Tabla de historial -->
    <div class="bento-card">
        <div class="card-header">
            <span class="card-title">Mis Solicitudes y Estados anteriores</span>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">Ticket</th>
                        <th style="width: 200px;">Bien</th>
                        <th style="width: 140px;">Fecha Reporte</th>
                        <th style="width: 130px;">Técnico / Estado</th>
                        <th>Flujo e Historial de Cambios</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($t = $tickets->fetch_assoc()): 
                    $tckId = $t['Tck_ID'];
                    $tckHist = $historiaPorTicket[$tckId] ?? [];
                ?>
                <tr>
                    <td>
                        <strong>#<?= $tckId ?></strong>
                    </td>
                    <td>
                        <div style="font-weight: 600;"><?= htmlspecialchars($t['Bien']) ?></div>
                        <div style="font-size: 0.78rem; color: var(--text-muted);"><?= htmlspecialchars($t['Des_Falla']) ?></div>
                    </td>
                    <td>
                        <div style="font-size: 0.85rem; font-weight: 500;"><?= date('d/m/Y H:i', strtotime($t['Cre_Tic'])) ?></div>
                    </td>
                    <td>
                        <div style="margin-bottom: 0.25rem;">
                            <span class="badge badge-<?= strtolower(str_replace(' ', '-', $t['Estado'])) ?>"><?= $t['Estado'] ?></span>
                        </div>
                        <div style="font-size: 0.78rem; color: var(--text-muted);">
                            <?= htmlspecialchars($t['TecnicoActual'] ?? 'Sin asignar') ?>
                        </div>
                    </td>
                    <td>
                        <div style="display: flex; flex-direction: column; gap: 0.35rem; padding-left: 0.5rem; border-left: 2px solid var(--border-color);">
                        <?php if (empty($tckHist)): ?>
                            <div style="color: var(--text-muted); font-size: 0.78rem; font-style: italic;">Sin cambios de estado registrados</div>
                        <?php else: ?>
                            <?php foreach ($tckHist as $change): ?>
                                <?php if ($change['Est_An'] === 'N/A'): ?>
                                    <div style="color: var(--text-muted); font-size: 0.78rem;">
                                        <strong>Creado</strong> el <?= date('d/m/Y H:i', strtotime($change['Fecha'])) ?>
                                    </div>
                                <?php else: ?>
                                    <div style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; flex-wrap: wrap;">
                                        <span class="badge badge-<?= strtolower(str_replace(' ', '-', $change['Est_An'])) ?>" style="font-size: 0.65rem; padding: 0.1rem 0.4rem;"><?= $change['Est_An'] ?></span>
                                        <span style="color: var(--text-muted);">&rarr;</span>
                                        <span class="badge badge-<?= strtolower(str_replace(' ', '-', $change['Est_Ac'])) ?>" style="font-size: 0.65rem; padding: 0.1rem 0.4rem;"><?= $change['Est_Ac'] ?></span>
                                        <span style="color: var(--text-muted); font-size: 0.75rem; white-space: nowrap;"><?= date('d/m/Y H:i', strtotime($change['Fecha'])) ?></span>
                                        <?php if ($change['TecnicoNombre']): ?>
                                            <span style="color: var(--text-secondary); font-size: 0.75rem; font-weight: 500;">(por <?= htmlspecialchars($change['TecnicoNombre']) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($tickets->num_rows === 0): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted" style="padding: 3rem;">
                        No has registrado ningún ticket aún.
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
