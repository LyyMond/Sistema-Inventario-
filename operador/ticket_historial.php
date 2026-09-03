<?php
/**
 * operador/ticket_historial.php
 * Muestra el historial completo de cambios de estado de un ticket.
 * Solo permite ver tickets que pertenezcan al operador en sesión.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Operador', '..');

$tckId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cedula = $_SESSION['cedula'];

if ($tckId === 0) { header('Location: tickets.php'); exit; }

// Cargar ticket (verificando que pertenece al operador)
$stmt = $conn->prepare(
    "SELECT t.Tck_ID, t.Des_Falla, t.Prioridad, t.Estado, t.Nombre,
            t.Sugerencias, t.Cre_Tic, i.Descripcion AS Bien, i.Cod_bien,
            p2.Nombre AS Tecnico
     FROM Tickets t
     INNER JOIN Inventario i ON t.Cod_bien = i.Cod_bien
     LEFT JOIN Tecnicos tc ON t.CIT = tc.CIT
     LEFT JOIN Perfiles p2 ON tc.Cedula = p2.Cedula
     WHERE t.Tck_ID = ?
       AND t.Nombre = (SELECT Nombre FROM Perfiles WHERE Cedula = ?)
     LIMIT 1"
);
$stmt->bind_param('is', $tckId, $cedula);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ticket) { header('Location: tickets.php'); exit; }

// Historial de cambios
$stmtH = $conn->prepare(
    "SELECT Est_An, Est_Ac, Fecha FROM Tickets_Historial WHERE Tck_ID = ? ORDER BY ID ASC"
);
$stmtH->bind_param('i', $tckId);
$stmtH->execute();
$historial = $stmtH->get_result();
$stmtH->close();

$pageTitle = 'Historial ticket #' . $tckId;
$basePath  = '..';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Ticket #<?= $tckId ?></h1>
        <p><?= htmlspecialchars($ticket['Bien']) ?> &mdash; <?= htmlspecialchars($ticket['Des_Falla']) ?></p>
    </div>
    <a href="tickets.php" class="btn btn-secondary">← Mis tickets</a>
</div>

<div class="bento-grid bento-grid-2">
    <div class="bento-card">
        <div class="card-header"><span class="card-title">Detalles del ticket</span></div>
        <table style="width:100%;border-collapse:collapse;font-size:0.9rem">
            <tr><td style="padding:0.4rem 0;color:var(--text-muted);width:140px">Bien</td><td><?= htmlspecialchars($ticket['Bien']) ?> (Cód. <?= $ticket['Cod_bien'] ?>)</td></tr>
            <tr><td style="padding:0.4rem 0;color:var(--text-muted)">Descripción</td><td><?= htmlspecialchars($ticket['Des_Falla']) ?></td></tr>
            <tr><td style="padding:0.4rem 0;color:var(--text-muted)">Prioridad</td><td><span class="badge badge-<?= strtolower($ticket['Prioridad']) ?>"><?= $ticket['Prioridad'] ?></span></td></tr>
            <tr><td style="padding:0.4rem 0;color:var(--text-muted)">Estado actual</td><td><span class="badge badge-<?= strtolower(str_replace(' ','-',$ticket['Estado'])) ?>"><?= $ticket['Estado'] ?></span></td></tr>
            <tr><td style="padding:0.4rem 0;color:var(--text-muted)">Técnico</td><td><?= htmlspecialchars($ticket['Tecnico'] ?? 'Sin asignar') ?></td></tr>
            <tr><td style="padding:0.4rem 0;color:var(--text-muted)">Sugerencias</td><td><?= htmlspecialchars($ticket['Sugerencias'] ?? '—') ?></td></tr>
            <tr><td style="padding:0.4rem 0;color:var(--text-muted)">Creado el</td><td><?= date('d/m/Y H:i', strtotime($ticket['Cre_Tic'])) ?></td></tr>
        </table>
    </div>

    <div class="bento-card">
        <div class="card-header"><span class="card-title">Historial de cambios</span></div>
        <?php if ($historial->num_rows === 0): ?>
        <p class="text-muted">Sin cambios de estado registrados.</p>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:0.75rem">
        <?php while ($h = $historial->fetch_assoc()): ?>
            <div style="border-left:3px solid var(--verde-grama);padding-left:0.75rem">
                <div style="font-size:0.78rem;color:var(--text-muted)"><?= $h['Fecha'] ? date('d/m/Y H:i', strtotime($h['Fecha'])) : '' ?></div>
                <div style="display:flex;align-items:center;gap:0.5rem;margin-top:0.2rem">
                    <span class="badge badge-<?= strtolower(str_replace(' ','-',$h['Est_An'])) ?>"><?= $h['Est_An'] ?></span>
                    <span style="color:var(--text-muted)">→</span>
                    <span class="badge badge-<?= strtolower(str_replace(' ','-',$h['Est_Ac'])) ?>"><?= $h['Est_Ac'] ?></span>
                </div>
            </div>
        <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
