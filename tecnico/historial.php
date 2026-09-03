<?php
/**
 * tecnico/historial.php
 * Historial general de tickets para el Técnico.
 * Muestra tickets de todos los usuarios agrupados por mes/año con selector.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Tecnico', '..');

// Obtener meses y años disponibles
$monthsRes = $conn->query("SELECT DISTINCT YEAR(Cre_Tic) AS anio, MONTH(Cre_Tic) AS mes FROM Tickets ORDER BY anio DESC, mes DESC");
$months = [];
if ($monthsRes) {
    while ($row = $monthsRes->fetch_assoc()) {
        $months[] = $row;
    }
}

// Resolver mes y año seleccionado
if (isset($_GET['mes_anio'])) {
    $parts = explode('-', $_GET['mes_anio']);
    if (count($parts) === 2) {
        $selMes  = (int)$parts[0];
        $selAnio = (int)$parts[1];
    }
}

if (!isset($selMes) || !isset($selAnio)) {
    if (!empty($months)) {
        $selMes  = (int)$months[0]['mes'];
        $selAnio = (int)$months[0]['anio'];
    } else {
        $selMes  = (int)date('m');
        $selAnio = (int)date('Y');
    }
}

// Obtener tickets del mes seleccionado
$stmtT = $conn->prepare(
    "SELECT t.Tck_ID, t.Des_Falla, t.Prioridad, t.Estado, t.Nombre AS Solicitante, t.Cre_Tic,
            i.Descripcion AS Bien,
            pt.Nombre AS TecnicoActual
     FROM Tickets t
     INNER JOIN Inventario i ON t.Cod_bien = i.Cod_bien
     LEFT JOIN Tecnicos tc ON t.CIT = tc.CIT
     LEFT JOIN Perfiles pt ON tc.Cedula = pt.Cedula
     WHERE YEAR(t.Cre_Tic) = ? AND MONTH(t.Cre_Tic) = ?
     ORDER BY t.Cre_Tic DESC"
);
$stmtT->bind_param('ii', $selAnio, $selMes);
$stmtT->execute();
$tickets = $stmtT->get_result();
$stmtT->close();

// Obtener historial de todos los tickets del mes seleccionado
$stmtH = $conn->prepare(
    "SELECT h.Tck_ID, h.Est_An, h.Est_Ac, h.Fecha, p.Nombre AS TecnicoNombre
     FROM Tickets_Historial h
     INNER JOIN Tickets t ON h.Tck_ID = t.Tck_ID
     LEFT JOIN Tecnicos tc ON h.CIT = tc.CIT
     LEFT JOIN Perfiles p ON tc.Cedula = p.Cedula
     WHERE YEAR(t.Cre_Tic) = ? AND MONTH(t.Cre_Tic) = ?
     ORDER BY h.Tck_ID ASC, h.Fecha ASC"
);
$stmtH->bind_param('ii', $selAnio, $selMes);
$stmtH->execute();
$resH = $stmtH->get_result();
$historiaPorTicket = [];
while ($rowH = $resH->fetch_assoc()) {
    $historiaPorTicket[$rowH['Tck_ID']][] = $rowH;
}
$stmtH->close();

$mesesNombres = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$pageTitle = 'Historial de Tickets';
$basePath  = '..';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Historial de Tickets</h1>
        <p>Visualiza el flujo de estados de todos los tickets del sistema.</p>
    </div>
</div>

<div class="bento-grid" style="grid-template-columns: 1fr; gap: 1.5rem;">
    <!-- Selector de mes -->
    <div class="bento-card" style="padding: 1.25rem 1.5rem;">
        <form method="GET" action="historial.php" id="filterForm" style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <label class="form-label" for="mes_anio" style="margin: 0; font-weight: 700; font-size: 0.9rem;">Seleccionar Mes de Historial:</label>
            <select name="mes_anio" id="mes_anio" class="form-control" style="max-width: 260px; margin: 0;" onchange="document.getElementById('filterForm').submit();">
                <?php if (empty($months)): ?>
                    <option value="<?= $selMes ?>-<?= $selAnio ?>" selected><?= $mesesNombres[$selMes] ?> <?= $selAnio ?></option>
                <?php else: ?>
                    <?php foreach ($months as $m): ?>
                        <option value="<?= $m['mes'] ?>-<?= $m['anio'] ?>" <?= ($selMes==$m['mes'] && $selAnio==$m['anio']) ? 'selected' : '' ?>>
                            <?= $mesesNombres[$m['mes']] ?> <?= $m['anio'] ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <noscript>
                <button type="submit" class="btn btn-secondary btn-sm">Filtrar</button>
            </noscript>
        </form>
    </div>

    <!-- Tabla de historial -->
    <div class="bento-card">
        <div class="card-header">
            <span class="card-title">
                Historial de: <?= $mesesNombres[$selMes] ?> del <?= $selAnio ?>
            </span>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">Ticket</th>
                        <th style="width: 180px;">Bien</th>
                        <th style="width: 140px;">Solicitante / Fecha</th>
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
                        <div style="font-weight: 500;"><?= htmlspecialchars($t['Solicitante'] ?? '—') ?></div>
                        <div style="font-size: 0.78rem; color: var(--text-muted);"><?= date('d/m/Y H:i', strtotime($t['Cre_Tic'])) ?></div>
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
                        No hay tickets registrados para el mes seleccionado.
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
