<?php
/**
 * tecnico/ticket_historial.php
 * Muestra el historial completo de cambios de estado de un ticket específico.
 * Cualquier técnico puede ver el historial de cualquier ticket.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Tecnico', '..');

$tckId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($tckId === 0) { header('Location: tickets.php'); exit; }

// Cargar ticket
$stmt = $conn->prepare(
    "SELECT t.Tck_ID, t.Des_Falla, t.Prioridad, t.Estado, t.Nombre AS Solicitante,
            t.Sugerencias, t.Cre_Tic, i.Descripcion AS Bien, i.Cod_bien,
            p.Nombre AS Tecnico
     FROM Tickets t
     INNER JOIN Inventario i ON t.Cod_bien = i.Cod_bien
     LEFT JOIN Tecnicos tc ON t.CIT = tc.CIT
     LEFT JOIN Perfiles p  ON tc.Cedula = p.Cedula
     WHERE t.Tck_ID = ? LIMIT 1"
);
$stmt->bind_param('i', $tckId);
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
        <h1>Historial de ticket #<?= $tckId ?></h1>
        <p><?= htmlspecialchars($ticket['Bien']) ?> &mdash; <?= htmlspecialchars($ticket['Des_Falla']) ?></p>
    </div>
    <a href="tickets.php" class="btn btn-secondary">← Volver a tickets</a>
</div>

<div class="bento-grid bento-grid-2">
    <!-- Detalles -->
    <div class="bento-card">
        <div class="card-header"><span class="card-title">Detalles del ticket</span></div>
        <table style="width:100%;font-size:0.9rem;border-collapse:collapse">
            <tr><td style="padding:0.4rem 0;color:var(--text-muted);width:130px">Bien</td><td><?= htmlspecialchars($ticket['Bien']) ?> (Cód. <?= $ticket['Cod_bien'] ?>)</td></tr>
            <tr><td style="padding:0.4rem 0;color:var(--text-muted)">Descripción</td><td><?= htmlspecialchars($ticket['Des_Falla']) ?></td></tr>
            <tr><td style="padding:0.4rem 0;color:var(--text-muted)">Prioridad</td><td><span class="badge badge-<?= strtolower($ticket['Prioridad']) ?>"><?= $ticket['Prioridad'] ?></span></td></tr>
            <tr><td style="padding:0.4rem 0;color:var(--text-muted)">Estado actual</td><td><span class="badge badge-<?= strtolower(str_replace(' ','-',$ticket['Estado'])) ?>"><?= $ticket['Estado'] ?></span></td></tr>
            <tr><td style="padding:0.4rem 0;color:var(--text-muted)">Solicitante</td><td><?= htmlspecialchars($ticket['Solicitante']) ?></td></tr>
            <tr><td style="padding:0.4rem 0;color:var(--text-muted)">Técnico</td><td><?= htmlspecialchars($ticket['Tecnico'] ?? 'Sin asignar') ?></td></tr>
            <tr><td style="padding:0.4rem 0;color:var(--text-muted)">Sugerencias</td><td><?= htmlspecialchars($ticket['Sugerencias'] ?? '—') ?></td></tr>
            <tr><td style="padding:0.4rem 0;color:var(--text-muted)">Creado el</td><td><?= date('d/m/Y H:i', strtotime($ticket['Cre_Tic'])) ?></td></tr>
        </table>
        <div style="margin-top:1.5rem">
            <a href="ticket_estado.php?id=<?= $tckId ?>" class="btn btn-primary btn-block">Gestionar estado</a>
        </div>
    </div>

    <!-- Timeline de cambios -->
    <div class="bento-card">
        <div class="card-header"><span class="card-title">Historial de cambios</span></div>
        <?php if ($historial->num_rows === 0): ?>
        <p class="text-muted">No se registran cambios de estado.</p>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:0.75rem">
        <?php while ($h = $historial->fetch_assoc()): ?>
            <div style="border-left:3px solid var(--rojo-institucional);padding-left:0.75rem">
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
