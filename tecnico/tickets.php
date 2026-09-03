<?php
/**
 * tecnico/tickets.php
 * Lista completa de TODOS los tickets del sistema.
 * El técnico puede filtrar por mes/año, buscar y acceder al detalle de cada ticket.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Tecnico', '..');

$flash = '';
if (isset($_GET['ok'])) {
    $msgs = [
        'assigned' => 'Te has asignado al ticket correctamente.',
        'updated'  => 'Estado del ticket actualizado.',
        'created'  => 'Ticket creado correctamente.'
    ];
    $flash = $msgs[$_GET['ok']] ?? '';
}

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

// Todos los tickets del mes seleccionado con información del técnico asignado, ordenados por prioridad y fecha
$stmtT = $conn->prepare(
    "SELECT t.Tck_ID, i.Descripcion AS Bien, t.Des_Falla, t.Prioridad, t.Estado,
            t.Nombre AS Solicitante, t.Cre_Tic, p.Nombre AS Tecnico
     FROM Tickets t
     INNER JOIN Inventario i ON t.Cod_bien = i.Cod_bien
     LEFT JOIN Tecnicos tc ON t.CIT = tc.CIT
     LEFT JOIN Perfiles p  ON tc.Cedula = p.Cedula
     WHERE YEAR(t.Cre_Tic) = ? AND MONTH(t.Cre_Tic) = ?
     ORDER BY
       CASE t.Prioridad WHEN 'Urgente' THEN 1 WHEN 'Media' THEN 2 ELSE 3 END,
       t.Cre_Tic ASC"
);
$stmtT->bind_param('ii', $selAnio, $selMes);
$stmtT->execute();
$tickets = $stmtT->get_result();
$stmtT->close();

$mesesNombres = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$pageTitle = 'Todos los tickets';
$basePath  = '..';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div><h1>Gestión de tickets</h1><p>Vista completa de todas las solicitudes del sistema.</p></div>
</div>

<?php if ($flash): ?>
<div class="alert alert-success" data-autohide><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="bento-grid" style="grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
    <!-- Selector de mes -->
    <div class="bento-card" style="padding: 1.25rem 1.5rem;">
        <form method="GET" action="tickets.php" id="filterForm" style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <label class="form-label" for="mes_anio" style="margin: 0; font-weight: 700; font-size: 0.9rem;">Seleccionar Mes de Tickets:</label>
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
</div>

<div class="bento-card">
    <div class="card-header">
        <span class="card-title">Tickets de: <?= $mesesNombres[$selMes] ?> del <?= $selAnio ?></span>
    </div>
    <div class="table-search">
        <input type="text" class="form-control" placeholder="Buscar tickets..."
               data-search-table="tablaTickets" style="max-width:360px">
    </div>
    <div class="table-wrapper">
        <table class="data-table" id="tablaTickets">
            <thead>
                <tr><th>#</th><th>Bien</th><th>Descripción</th><th>Prioridad</th><th>Estado</th>
                    <th>Solicitante</th><th>Técnico</th><th>Fecha</th><th>Acciones</th></tr>
            </thead>
            <tbody>
            <?php while ($t = $tickets->fetch_assoc()): ?>
            <tr>
                <td><?= $t['Tck_ID'] ?></td>
                <td><?= htmlspecialchars($t['Bien']) ?></td>
                <td><?= htmlspecialchars($t['Des_Falla']) ?></td>
                <td><span class="badge badge-<?= strtolower($t['Prioridad']) ?>"><?= $t['Prioridad'] ?></span></td>
                <td><span class="badge badge-<?= strtolower(str_replace(' ','-',$t['Estado'])) ?>"><?= $t['Estado'] ?></span></td>
                <td><?= htmlspecialchars($t['Solicitante'] ?? '—') ?></td>
                <td><?= htmlspecialchars($t['Tecnico'] ?? 'Sin asignar') ?></td>
                <td><?= date('d/m/Y', strtotime($t['Cre_Tic'])) ?></td>
                <td>
                    <div class="table-actions">
                        <a href="ticket_estado.php?id=<?= $t['Tck_ID'] ?>" class="btn btn-outline btn-sm">Gestionar</a>
                        <a href="ticket_historial.php?id=<?= $t['Tck_ID'] ?>" class="btn btn-secondary btn-sm">Historial</a>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php if ($tickets->num_rows === 0): ?>
            <tr><td colspan="9" class="text-center text-muted" style="padding:2rem">No hay tickets registrados en este mes.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
