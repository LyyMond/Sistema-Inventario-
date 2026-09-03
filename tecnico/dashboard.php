<?php
/**
 * tecnico/dashboard.php
 * Panel principal del Técnico.
 * Muestra todos los tickets del sistema, con KPIs y accesos rápidos.
 * El técnico puede tomar tickets sin asignar y ver el historial.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Tecnico', '..');

$cit = $_SESSION['cit'] ?? null;

// Estadísticas globales
$totalTickets    = $conn->query("SELECT COUNT(*) AS c FROM Tickets")->fetch_assoc()['c'];
$sinAsignar      = $conn->query("SELECT COUNT(*) AS c FROM Tickets WHERE CIT IS NULL AND Estado='Inicio'")->fetch_assoc()['c'];
$misTickets      = $cit ? $conn->query("SELECT COUNT(*) AS c FROM Tickets WHERE CIT=$cit")->fetch_assoc()['c'] : 0;
$misFinalizados  = $cit ? $conn->query("SELECT COUNT(*) AS c FROM Tickets WHERE CIT=$cit AND Estado='Finalizado'")->fetch_assoc()['c'] : 0;

// Últimos 5 tickets sin asignar
$sinAsignarRes = $conn->query(
    "SELECT t.Tck_ID, i.Descripcion AS Bien, t.Des_Falla, t.Prioridad, t.Nombre AS Solicitante, t.Cre_Tic
     FROM Tickets t INNER JOIN Inventario i ON t.Cod_bien=i.Cod_bien
     WHERE t.CIT IS NULL AND t.Estado='Inicio'
     ORDER BY t.Prioridad DESC, t.Cre_Tic ASC LIMIT 5"
);

$pageTitle = 'Inicio';
$basePath  = '..';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Panel del técnico</h1>
        <p>Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?>. Gestiona los tickets asignados.</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="tickets.php" class="btn btn-outline">Ver todos los tickets</a>
        <a href="ticket_nuevo.php" class="btn btn-primary">+ Nuevo Ticket</a>
    </div>
</div>

<div class="bento-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.5rem">
    <div class="stat-card">
        <div class="stat-label">Total tickets</div>
        <div class="stat-number"><?= $totalTickets ?></div>
    </div>
    <div class="stat-card red">
        <div class="stat-label">Sin asignar</div>
        <div class="stat-number"><?= $sinAsignar ?></div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Mis tickets</div>
        <div class="stat-number"><?= $misTickets ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Mis finalizados</div>
        <div class="stat-number"><?= $misFinalizados ?></div>
    </div>
</div>

<div class="bento-card">
    <div class="card-header">
        <span class="card-title">Tickets disponibles para tomar</span>
        <a href="tickets.php" class="btn btn-outline btn-sm">Ver todos</a>
    </div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr><th>#</th><th>Bien</th><th>Descripción</th><th>Prioridad</th><th>Solicitante</th><th>Fecha</th><th>Acción</th></tr>
            </thead>
            <tbody>
            <?php while ($t = $sinAsignarRes->fetch_assoc()): ?>
            <tr>
                <td><?= $t['Tck_ID'] ?></td>
                <td><?= htmlspecialchars($t['Bien']) ?></td>
                <td><?= htmlspecialchars($t['Des_Falla']) ?></td>
                <td><span class="badge badge-<?= strtolower($t['Prioridad']) ?>"><?= $t['Prioridad'] ?></span></td>
                <td><?= htmlspecialchars($t['Solicitante']) ?></td>
                <td><?= date('d/m/Y', strtotime($t['Cre_Tic'])) ?></td>
                <td><a href="ticket_estado.php?id=<?= $t['Tck_ID'] ?>" class="btn btn-primary btn-sm">Tomar</a></td>
            </tr>
            <?php endwhile; ?>
            <?php if ($sinAsignarRes->num_rows === 0): ?>
            <tr><td colspan="7" class="text-center text-muted" style="padding:1.5rem">No hay tickets disponibles.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
