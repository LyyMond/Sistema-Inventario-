<?php
/**
 * operador/dashboard.php
 * Panel principal del Operador.
 * Muestra KPIs de sus propios tickets y accesos rápidos.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Operador', '..');

$cedula = $_SESSION['cedula'];

// Estadísticas del operador
$qT = $conn->prepare("SELECT COUNT(*) AS c FROM Tickets WHERE Nombre = (SELECT Nombre FROM Perfiles WHERE Cedula = ? LIMIT 1)");
$qT->bind_param('s', $cedula);
$qT->execute();
$total = $qT->get_result()->fetch_assoc()['c'] ?? 0;
$qT->close();

$stmtSt = $conn->prepare(
    "SELECT Estado, COUNT(*) AS c FROM Tickets
     WHERE Nombre=(SELECT Nombre FROM Perfiles WHERE Cedula=?) GROUP BY Estado"
);
$stmtSt->bind_param('s', $cedula);
$stmtSt->execute();
$resSt = $stmtSt->get_result();
$porEstado = [];
while ($r = $resSt->fetch_assoc()) { $porEstado[$r['Estado']] = $r['c']; }
$stmtSt->close();

// Últimos 5 tickets
$stmtUlt = $conn->prepare(
    "SELECT t.Tck_ID, i.Descripcion AS Bien, t.Des_Falla, t.Prioridad, t.Estado, t.Cre_Tic
     FROM Tickets t INNER JOIN Inventario i ON t.Cod_bien=i.Cod_bien
     WHERE t.Nombre=(SELECT Nombre FROM Perfiles WHERE Cedula=?)
     ORDER BY t.Cre_Tic DESC LIMIT 5"
);
$stmtUlt->bind_param('s', $cedula);
$stmtUlt->execute();
$ultTickets = $stmtUlt->get_result();
$stmtUlt->close();

$pageTitle = 'Inicio';
$basePath  = '..';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?></h1>
        <p>Panel de operador — gestiona tus solicitudes de soporte.</p>
    </div>
    <a href="ticket_nuevo.php" class="btn btn-primary">+ Nuevo Ticket</a>
</div>

<div class="bento-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem">
    <div class="stat-card">
        <div class="stat-label">Mis tickets</div>
        <div class="stat-number"><?= $total ?></div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">En proceso</div>
        <div class="stat-number"><?= $porEstado['En proceso'] ?? 0 ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Finalizados</div>
        <div class="stat-number"><?= $porEstado['Finalizado'] ?? 0 ?></div>
    </div>
</div>


<div class="bento-card" style="margin-top:1.25rem">
    <div class="card-header"><span class="card-title">Mis últimas solicitudes</span></div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead><tr><th>#</th><th>Bien</th><th>Descripción</th><th>Prioridad</th><th>Estado</th><th>Fecha</th><th></th></tr></thead>
            <tbody>
            <?php while ($t = $ultTickets->fetch_assoc()): ?>
            <tr>
                <td><?= $t['Tck_ID'] ?></td>
                <td><?= htmlspecialchars($t['Bien']) ?></td>
                <td><?= htmlspecialchars($t['Des_Falla']) ?></td>
                <td><span class="badge badge-<?= strtolower($t['Prioridad']) ?>"><?= $t['Prioridad'] ?></span></td>
                <td><span class="badge badge-<?= strtolower(str_replace(' ','-',$t['Estado'])) ?>"><?= $t['Estado'] ?></span></td>
                <td><?= date('d/m/Y', strtotime($t['Cre_Tic'])) ?></td>
                <td><a href="ticket_historial.php?id=<?= $t['Tck_ID'] ?>" class="btn btn-outline btn-sm">Historial</a></td>
            </tr>
            <?php endwhile; ?>
            <?php if ($ultTickets->num_rows === 0): ?>
            <tr><td colspan="7" class="text-center text-muted" style="padding:1.5rem">Aún no has creado tickets.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
