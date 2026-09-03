<?php
/**
 * operador/tickets.php
 * Lista de tickets del operador autenticado.
 * Muestra el estado actual y un enlace al historial de cada ticket.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Operador', '..');

$cedula = $_SESSION['cedula'];

$flash = '';
if (isset($_GET['ok']) && $_GET['ok'] === 'created') {
    $flash = 'Ticket creado correctamente. Un técnico lo atenderá pronto.';
}

// Obtener tickets del operador (por nombre)
$stmt = $conn->prepare(
    "SELECT t.Tck_ID, i.Descripcion AS Bien, t.Des_Falla, t.Prioridad,
            t.Estado, t.Cre_Tic, t.Sugerencias,
            p.Nombre AS Tecnico
     FROM Tickets t
     INNER JOIN Inventario i ON t.Cod_bien = i.Cod_bien
     LEFT JOIN Tecnicos tc ON t.CIT = tc.CIT
     LEFT JOIN Perfiles p  ON tc.Cedula = p.Cedula
     WHERE t.Nombre = (SELECT Nombre FROM Perfiles WHERE Cedula = ?)
     ORDER BY t.Cre_Tic DESC"
);
$stmt->bind_param('s', $cedula);
$stmt->execute();
$tickets = $stmt->get_result();
$stmt->close();

$pageTitle = 'Mis tickets';
$basePath  = '..';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div><h1>Mis tickets</h1><p>Historial de todas tus solicitudes de soporte.</p></div>
    <a href="ticket_nuevo.php" class="btn btn-primary">+ Nuevo ticket</a>
</div>

<?php if ($flash): ?>
<div class="alert alert-success" data-autohide><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="bento-card">
    <div class="table-search">
        <input type="text" class="form-control" placeholder="Buscar ticket..."
               data-search-table="tablaTickets" style="max-width:360px">
    </div>
    <div class="table-wrapper">
        <table class="data-table" id="tablaTickets">
            <thead>
                <tr><th>#</th><th>Bien</th><th>Descripción</th><th>Prioridad</th>
                    <th>Estado</th><th>Técnico</th><th>Fecha</th><th>Historial</th></tr>
            </thead>
            <tbody>
            <?php while ($t = $tickets->fetch_assoc()): ?>
            <tr>
                <td><?= $t['Tck_ID'] ?></td>
                <td><?= htmlspecialchars($t['Bien']) ?></td>
                <td><?= htmlspecialchars($t['Des_Falla']) ?></td>
                <td><span class="badge badge-<?= strtolower($t['Prioridad']) ?>"><?= $t['Prioridad'] ?></span></td>
                <td><span class="badge badge-<?= strtolower(str_replace(' ','-',$t['Estado'])) ?>"><?= $t['Estado'] ?></span></td>
                <td><?= htmlspecialchars($t['Tecnico'] ?? 'Sin asignar') ?></td>
                <td><?= date('d/m/Y H:i', strtotime($t['Cre_Tic'])) ?></td>
                <td><a href="ticket_historial.php?id=<?= $t['Tck_ID'] ?>" class="btn btn-outline btn-sm">Ver</a></td>
            </tr>
            <?php endwhile; ?>
            <?php if ($tickets->num_rows === 0): ?>
            <tr><td colspan="8" class="text-center text-muted" style="padding:2rem">No tienes tickets registrados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
