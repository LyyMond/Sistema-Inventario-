<?php
/**
 * admin/dashboard.php
 * Panel principal del Administrador.
 * Muestra KPIs: total bienes, tickets, usuarios y accesos rápidos.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Administrador', '..');

// ── Estadísticas ──────────────────────────────────────────
$qB = $conn->query("SELECT COUNT(*) AS c FROM Inventario");
$totalBienes = $qB ? $qB->fetch_assoc()['c'] : 0;

$qT = $conn->query("SELECT COUNT(*) AS c FROM Tickets");
$totalTickets = $qT ? $qT->fetch_assoc()['c'] : 0;

$qU = $conn->query("SELECT COUNT(*) AS c FROM Perfiles");
$totalUsuarios = $qU ? $qU->fetch_assoc()['c'] : 0;

$qUrg = $conn->query("SELECT COUNT(*) AS c FROM Tickets WHERE Prioridad='Urgente'");
$urgentes = $qUrg ? $qUrg->fetch_assoc()['c'] : 0;


// Últimos 5 tickets
$ultTickets = $conn->query(
    "SELECT t.Tck_ID, t.Des_Falla, t.Estado, t.Prioridad, t.Cre_Tic, t.Nombre AS Solicitante, i.Descripcion AS Bien
     FROM Tickets t INNER JOIN Inventario i ON t.Cod_bien = i.Cod_bien
     ORDER BY t.Cre_Tic DESC LIMIT 5"
);
if (!$ultTickets) {
    die("<div class='alert alert-danger'>Error SQL: " . htmlspecialchars($conn->error) . "</div>");
}


$pageTitle = 'Inicio';
$basePath  = '..';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Panel de administración</h1>
        <p>Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?>. Gestiona el sistema desde aquí.</p>
    </div>
    <a href="ticket_nuevo.php" class="btn btn-primary">+ Nuevo Ticket</a>
</div>

<!-- KPI cards -->
<div class="bento-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.5rem">
    <div class="stat-card green">
        <div class="stat-label">Bienes en inventario</div>
        <div class="stat-number"><?= $totalBienes ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total tickets</div>
        <div class="stat-number"><?= $totalTickets ?></div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Usuarios registrados</div>
        <div class="stat-number"><?= $totalUsuarios ?></div>
    </div>
    <div class="stat-card red">
        <div class="stat-label">Tickets urgentes</div>
        <div class="stat-number"><?= $urgentes ?></div>
    </div>
</div>

    <!-- Tickets Recientes -->
    <div class="bento-card">
        <div class="card-header" style="border-bottom: none; margin-bottom: 0.5rem; padding-bottom: 0;">
            <span class="card-title" style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; color: var(--text-primary); font-size: 1.1rem;">
                <svg class="card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px; color: var(--text-primary); margin-right: 0.25rem;">
                    <line x1="8" y1="6" x2="21" y2="6"></line>
                    <line x1="8" y1="12" x2="21" y2="12"></line>
                    <line x1="8" y1="18" x2="21" y2="18"></line>
                    <line x1="3" y1="6" x2="3.01" y2="6"></line>
                    <line x1="3" y1="12" x2="3.01" y2="12"></line>
                    <line x1="3" y1="18" x2="3.01" y2="18"></line>
                </svg>
                Tickets Recientes
            </span>
        </div>
        <div class="table-wrapper" style="border: none; border-radius: 0; box-shadow: none; overflow-x: auto;">
            <table class="data-table" style="border-collapse: collapse; width: 100%;">
                <thead>
                    <tr>
                        <th style="background: transparent; color: var(--text-secondary); font-weight: 700; text-transform: none; border-bottom: 1.5px solid var(--border-color); padding: 0.85rem 1rem; font-size: 0.85rem; letter-spacing: 0;">ID Ticket</th>
                        <th style="background: transparent; color: var(--text-secondary); font-weight: 700; text-transform: none; border-bottom: 1.5px solid var(--border-color); padding: 0.85rem 1rem; font-size: 0.85rem; letter-spacing: 0;">Bien asociado</th>
                        <th style="background: transparent; color: var(--text-secondary); font-weight: 700; text-transform: none; border-bottom: 1.5px solid var(--border-color); padding: 0.85rem 1rem; font-size: 0.85rem; letter-spacing: 0;">Estado</th>
                        <th style="background: transparent; color: var(--text-secondary); font-weight: 700; text-transform: none; border-bottom: 1.5px solid var(--border-color); padding: 0.85rem 1rem; font-size: 0.85rem; letter-spacing: 0;">Fecha reporte</th>
                        <th style="background: transparent; color: var(--text-secondary); font-weight: 700; text-transform: none; border-bottom: 1.5px solid var(--border-color); padding: 0.85rem 1rem; font-size: 0.85rem; letter-spacing: 0;">Solicitante</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($t = $ultTickets->fetch_assoc()): 
                    $estadoStr = '';
                    $badgeStyle = '';
                    if ($t['Estado'] === 'Inicio') {
                        $estadoStr = 'Abierto';
                        $badgeStyle = 'background-color: #fdf2f2; color: #b91c1c; border: 1.5px solid #fca5a5; border-left: 5px solid #ef4444;';
                    } elseif ($t['Estado'] === 'En proceso') {
                        $estadoStr = 'En progreso';
                        $badgeStyle = 'background-color: #fffbeb; color: #b45309; border: 1.5px solid #fcd34d; border-left: 5px solid #f59e0b;';
                    } else {
                        $estadoStr = 'Resuelto';
                        $badgeStyle = 'background-color: #f0fdf4; color: #15803d; border: 1.5px solid #86efac; border-left: 5px solid #10b981;';
                    }
                ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 0.85rem 1rem; color: var(--text-secondary); font-size: 0.85rem; font-weight: 500;"><?= $t['Tck_ID'] ?></td>
                    <td style="padding: 0.85rem 1rem; color: var(--text-secondary); font-size: 0.85rem;"><?= htmlspecialchars($t['Bien']) ?></td>
                    <td style="padding: 0.85rem 1rem; font-size: 0.85rem;">
                        <span style="border-radius: 9999px; padding: 0.15rem 0.65rem; font-size: 0.72rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; text-transform: none; <?= $badgeStyle ?>"><?= $estadoStr ?></span>
                    </td>
                    <td style="padding: 0.85rem 1rem; color: var(--text-secondary); font-size: 0.85rem;"><?= date('Y-m-d', strtotime($t['Cre_Tic'])) ?></td>
                    <td style="padding: 0.85rem 1rem; color: var(--text-secondary); font-size: 0.85rem;"><?= htmlspecialchars($t['Solicitante'] ?? '—') ?></td>
                </tr>
                <?php endwhile; ?>
                <?php if ($ultTickets->num_rows === 0): ?>
                <tr><td colspan="5" class="text-center text-muted" style="padding:2.5rem; font-size: 0.85rem;">No hay tickets registrados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
