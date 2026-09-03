<?php
/**
 * tecnico/ticket_estado.php
 * Permite al técnico:
 *  1. Cambiar el estado del ticket (Inicio → En proceso → Finalizado).
 *  2. Reasignar el ticket a otro técnico (o a sí mismo) desde un selector.
 * Cada cambio de estado queda registrado en Tickets_Historial con el técnico logueado como responsable.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Tecnico', '..');

$tckId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cit   = $_SESSION['cit'] ?? null;
$error = '';

if ($tckId === 0 || !$cit) { header('Location: tickets.php'); exit; }

// Cargar ticket
$stmt = $conn->prepare(
    "SELECT t.Tck_ID, t.Cod_bien, t.Des_Falla, t.Prioridad, t.Estado,
            t.Nombre AS Solicitante, t.Sugerencias, t.Cre_Tic, t.CIT,
            i.Descripcion AS Bien,
            p.Nombre AS TecnicoNombre
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

// Cargar lista de técnicos activos disponibles para asignación
$tecnicosQuery = $conn->query(
    "SELECT t.CIT, p.Nombre 
     FROM Perfiles p
     INNER JOIN Tecnicos t ON p.Cedula = t.Cedula
     WHERE p.Activo = 1
     ORDER BY p.Nombre ASC"
);
$tecnicosArr = [];
if ($tecnicosQuery) {
    while ($row = $tecnicosQuery->fetch_assoc()) {
        $tecnicosArr[] = $row;
    }
}

// Procesar cambio de estado y reasignación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevoEstado = trim($_POST['estado'] ?? '');
    $nuevoCit    = isset($_POST['asignar_tecnico']) && $_POST['asignar_tecnico'] !== '' ? (int)$_POST['asignar_tecnico'] : null;
    $estadosValidos = ['Inicio', 'En proceso', 'Finalizado'];

    if (!in_array($nuevoEstado, $estadosValidos)) {
        $error = 'Estado no válido.';
    } else {
        $estadoAnterior = $ticket['Estado'];

        // Validar que el nuevoCit existe
        if (!is_null($nuevoCit)) {
            $chkC = $conn->prepare("SELECT CIT FROM Tecnicos WHERE CIT = ? LIMIT 1");
            $chkC->bind_param('i', $nuevoCit);
            $chkC->execute();
            if ($chkC->get_result()->num_rows === 0) {
                $nuevoCit = null;
            }
            $chkC->close();
        }

        // Si el estado es "En proceso" y el CIT es nulo, autoseleccionar al técnico actual en sesión
        if ($nuevoEstado === 'En proceso' && is_null($nuevoCit)) {
            $nuevoCit = $cit;
        }

        // Si el estado es "Inicio", el técnico asignado debe resetearse a NULL
        if ($nuevoEstado === 'Inicio') {
            $nuevoCit = null;
        }

        $stmtUp = $conn->prepare(
            "UPDATE Tickets SET Estado=?, CIT=? WHERE Tck_ID=?"
        );
        $stmtUp->bind_param('sii', $nuevoEstado, $nuevoCit, $tckId);
        $stmtUp->execute();
        $stmtUp->close();

        // Registrar en historial con el técnico logueado responsable del cambio
        $stmtH = $conn->prepare(
            "INSERT INTO Tickets_Historial (Tck_ID, Est_Ac, Est_An, CIT) VALUES (?, ?, ?, ?)"
        );
        $stmtH->bind_param('issi', $tckId, $nuevoEstado, $estadoAnterior, $cit);
        $stmtH->execute();
        $stmtH->close();

        header('Location: tickets.php?ok=updated');
        exit;
    }
}

$pageTitle = 'Gestionar ticket #' . $tckId;
$basePath  = '..';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Gestionar ticket #<?= $tckId ?></h1>
        <p><?= htmlspecialchars($ticket['Bien']) ?> &mdash; <?= htmlspecialchars($ticket['Des_Falla']) ?></p>
    </div>
    <a href="tickets.php" class="btn btn-secondary">← Volver</a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="bento-grid bento-grid-2">
    <!-- Datos del ticket -->
    <div class="bento-card">
        <div class="card-header"><span class="card-title">Información del ticket</span></div>
        <table style="width:100%;font-size:0.9rem;border-collapse:collapse">
            <tr><td style="padding:0.4rem 0;color:var(--text-muted);width:130px">Bien</td><td><?= htmlspecialchars($ticket['Bien']) ?></td></tr>
            <tr><td style="padding:0.4rem 0;color:var(--text-muted)">Descripción</td><td><?= htmlspecialchars($ticket['Des_Falla']) ?></td></tr>
            <tr><td style="padding:0.4rem 0;color:var(--text-muted)">Prioridad</td><td><span class="badge badge-<?= strtolower($ticket['Prioridad']) ?>"><?= $ticket['Prioridad'] ?></span></td></tr>
            <tr><td style="padding:0.4rem 0;color:var(--text-muted)">Estado actual</td><td><span class="badge badge-<?= strtolower(str_replace(' ','-',$ticket['Estado'])) ?>"><?= $ticket['Estado'] ?></span></td></tr>
            <tr><td style="padding:0.4rem 0;color:var(--text-muted)">Solicitante</td><td><?= htmlspecialchars($ticket['Solicitante'] ?? '—') ?></td></tr>
            <tr><td style="padding:0.4rem 0;color:var(--text-muted)">Técnico</td><td><?= htmlspecialchars($ticket['TecnicoNombre'] ?? 'Sin asignar') ?></td></tr>
            <tr><td style="padding:0.4rem 0;color:var(--text-muted)">Sugerencias</td><td><?= htmlspecialchars($ticket['Sugerencias'] ?? '—') ?></td></tr>
            <tr><td style="padding:0.4rem 0;color:var(--text-muted)">Creado el</td><td><?= date('d/m/Y H:i', strtotime($ticket['Cre_Tic'])) ?></td></tr>
        </table>
    </div>

    <!-- Cambio de estado y asignación -->
    <div class="bento-card">
        <div class="card-header"><span class="card-title">Gestionar estado y asignación</span></div>

        <form method="POST">
            <div class="form-group">
                <label class="form-label" for="estado">Estado del ticket</label>
                <select id="estado" name="estado" class="form-control">
                    <option value="Inicio" <?= $ticket['Estado']==='Inicio'?'selected':'' ?>>Inicio</option>
                    <option value="En proceso" <?= $ticket['Estado']==='En proceso'?'selected':'' ?>>En proceso</option>
                    <option value="Finalizado" <?= $ticket['Estado']==='Finalizado'?'selected':'' ?>>Finalizado</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="asignar_tecnico">Técnico asignado</label>
                <select id="asignar_tecnico" name="asignar_tecnico" class="form-control">
                    <option value="">-- Sin asignar --</option>
                    <?php foreach ($tecnicosArr as $tec): ?>
                        <option value="<?= $tec['CIT'] ?>" <?= $ticket['CIT'] == $tec['CIT'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tec['Nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="form-hint">Puedes reasignar este ticket a cualquier otro técnico de la lista.</p>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top:1.5rem">Aplicar cambios</button>
        </form>

        <hr class="divider">
        <a href="ticket_historial.php?id=<?= $tckId ?>" class="btn btn-secondary btn-block">Ver historial completo</a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const estadoSelect = document.getElementById('estado');
    const tecnicoSelect = document.getElementById('asignar_tecnico');

    if (estadoSelect && tecnicoSelect) {
        estadoSelect.addEventListener('change', () => {
            if (estadoSelect.value === 'Inicio') {
                tecnicoSelect.value = '';
            } else if (estadoSelect.value === 'En proceso' && tecnicoSelect.value === '') {
                // Seleccionar al técnico actual logueado por comodidad
                tecnicoSelect.value = '<?= $cit ?>';
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
