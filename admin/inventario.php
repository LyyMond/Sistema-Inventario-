<?php
/**
 * admin/inventario.php
 * Lista completa del inventario.
 * Permite filtrar por Ubicación y muestra las nuevas columnas.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Administrador', '..');

// Filtro de ubicación
$ubiFilter = isset($_GET['ubicacion']) ? (int)$_GET['ubicacion'] : 0;

// Cargar ubicaciones para el filtro
$ubicaciones = $conn->query("SELECT ID, Nombre FROM Ubicaciones ORDER BY Nombre ASC");
if (!$ubicaciones) {
    die("<div class='alert alert-danger'>Error SQL (Ubicaciones): " . htmlspecialchars($conn->error) . "</div>");
}

// Construir query de inventario
$sql = "SELECT i.ID, i.Cod_bien, i.Cantidad, i.Num_Bien, i.Descripcion, 
               i.Inv_Codigo, i.Inv_Concepto, i.Valor_Unitario, i.Valor_Total, 
               u.Nombre AS Ubicacion, u.ID AS UbiID
        FROM Inventario i
        LEFT JOIN Ubicaciones u ON i.Ubicacion_ID = u.ID ";

if ($ubiFilter > 0) {
    $sql .= " WHERE i.Ubicacion_ID = $ubiFilter ";
}
$sql .= " ORDER BY i.Fecha_Agregado DESC";

$bienes = $conn->query($sql);
if (!$bienes) {
    die("<div class='alert alert-danger'>Error SQL: " . htmlspecialchars($conn->error) . "</div>");
}

$flash = '';
if (isset($_GET['ok'])) {
    $msgs = [
        'add'    => 'Bien agregado correctamente.',
        'edit'   => 'Bien actualizado correctamente.',
        'delete' => 'Bien eliminado correctamente.',
    ];
    $flash = $msgs[$_GET['ok']] ?? '';
}

$pageTitle = 'Inventario';
$basePath  = '..';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Inventario de bienes</h1>
        <p>Gestiona los bienes institucionales registrados en el sistema.</p>
    </div>
    <div style="display:flex;gap:0.5rem">
        <a href="ubicaciones.php" class="btn btn-secondary">Gestionar Ubicaciones</a>
        <?php if ($ubiFilter > 0): ?>
            <a href="ubicacion_historial.php?id=<?= $ubiFilter ?>" class="btn btn-secondary">Ver Historial de Sala</a>
        <?php endif; ?>
        <a href="inventario_form.php" class="btn btn-primary">+ Agregar bien</a>
    </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-success" data-autohide><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="bento-card" style="margin-bottom: 1.5rem">
    <form method="GET" style="display:flex;gap:1rem;align-items:flex-end">
        <div class="form-group" style="margin:0;flex:1;max-width:300px">
            <label class="form-label" for="ubicacion">Filtrar por Sala / Ubicación</label>
            <select name="ubicacion" id="ubicacion" class="form-control" onchange="this.form.submit()">
                <option value="0">-- Todas las ubicaciones --</option>
                <?php while ($u = $ubicaciones->fetch_assoc()): ?>
                <option value="<?= $u['ID'] ?>" <?= $ubiFilter == $u['ID'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['Nombre']) ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="table-search" style="flex:1;max-width:300px;margin-bottom:0">
            <input type="text" class="form-control" placeholder="Buscar en tabla..."
                   data-search-table="tablaInventario">
        </div>
    </form>
</div>

<div class="bento-card">
    <div class="table-wrapper">
        <table class="data-table" id="tablaInventario" style="font-size:0.85rem">
            <thead>
                <tr>
                    <th>CANTIDAD</th>
                    <th>CÓDIGO</th>
                    <th>Nº DE BIEN</th>
                    <th>DESCRIPCIÓN</th>
                    <th>INV. CÓDIGO</th>
                    <th>INV. CONCEPTO</th>
                    <th>VALOR UNITARIO</th>
                    <th>VALOR TOTAL</th>
                    <th>SALA</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($b = $bienes->fetch_assoc()): ?>
            <tr>
                <td><?= $b['Cantidad'] ?></td>
                <td><strong><?= htmlspecialchars($b['Cod_bien']) ?></strong></td>
                <td><?= htmlspecialchars($b['Num_Bien']) ?></td>
                <td><?= htmlspecialchars($b['Descripcion']) ?></td>
                <td><?= htmlspecialchars($b['Inv_Codigo']) ?></td>
                <td><?= htmlspecialchars($b['Inv_Concepto']) ?></td>
                <td>$<?= number_format($b['Valor_Unitario'], 2) ?></td>
                <td><strong>$<?= number_format($b['Valor_Total'], 2) ?></strong></td>
                <td><?= htmlspecialchars($b['Ubicacion'] ?? '—') ?></td>
                <td>
                    <div class="table-actions">
                        <a href="inventario_form.php?id=<?= $b['ID'] ?>" class="btn btn-outline btn-sm">Editar</a>
                        <button class="btn btn-danger btn-sm"
                                data-delete-url="inventario_delete.php?id=<?= $b['ID'] ?>"
                                data-delete-name="el bien '<?= htmlspecialchars(addslashes($b['Descripcion'])) ?>'">
                            Eliminar
                        </button>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php if ($bienes->num_rows === 0): ?>
            <tr><td colspan="10" class="text-center text-muted" style="padding:2rem">No hay bienes para mostrar.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <h3>Confirmar eliminación</h3>
        <p id="deleteModalMsg"></p>
        <div class="modal-actions">
            <button id="deleteCancelBtn" class="btn btn-secondary">Cancelar</button>
            <a id="deleteConfirmBtn" href="#" class="btn btn-danger">Sí, eliminar</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
