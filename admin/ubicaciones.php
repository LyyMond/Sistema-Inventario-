<?php
/**
 * admin/ubicaciones.php
 * Lista de ubicaciones/salas registradas en el sistema.
 * Solo accesible para Administrador.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Administrador', '..');

// Obtener todas las ubicaciones
$ubicaciones = $conn->query("SELECT ID, Nombre, Cre_Ubi FROM Ubicaciones ORDER BY Nombre ASC");

$flash = '';
if (isset($_GET['ok'])) {
    $msgs = [
        'add'    => 'Ubicación agregada correctamente.',
        'edit'   => 'Ubicación actualizada correctamente.',
        'delete' => 'Ubicación eliminada correctamente.',
        'err_del'=> 'No se puede eliminar la ubicación porque tiene bienes asociados.'
    ];
    $flash = $msgs[$_GET['ok']] ?? '';
}

$pageTitle = 'Ubicaciones';
$basePath  = '..';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Gestión de Ubicaciones</h1>
        <p>Administra las salas y espacios donde se encuentran los bienes.</p>
    </div>
    <a href="ubicaciones_form.php" class="btn btn-primary">+ Nueva Ubicación</a>
</div>

<?php if ($flash): ?>
<div class="alert <?= $_GET['ok'] === 'err_del' ? 'alert-danger' : 'alert-success' ?>" data-autohide>
    <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>

<div class="bento-card">
    <div class="table-search">
        <input type="text" class="form-control" placeholder="Buscar ubicación..."
               data-search-table="tablaUbicaciones" style="max-width:380px">
    </div>
    <div class="table-wrapper">
        <table class="data-table" id="tablaUbicaciones">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre de Ubicación / Sala</th>
                    <th>Fecha de Registro</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($u = $ubicaciones->fetch_assoc()): ?>
            <tr>
                <td><?= $u['ID'] ?></td>
                <td><strong><?= htmlspecialchars($u['Nombre']) ?></strong></td>
                <td><?= date('d/m/Y', strtotime($u['Cre_Ubi'])) ?></td>
                <td>
                    <div class="table-actions">
                        <a href="ubicaciones_form.php?id=<?= $u['ID'] ?>" class="btn btn-outline btn-sm">Editar</a>
                        <button class="btn btn-danger btn-sm"
                                data-delete-url="ubicaciones_delete.php?id=<?= $u['ID'] ?>"
                                data-delete-name="la ubicación '<?= htmlspecialchars(addslashes($u['Nombre'])) ?>'">
                            Eliminar
                        </button>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php if ($ubicaciones->num_rows === 0): ?>
            <tr><td colspan="4" class="text-center text-muted" style="padding:2rem">No hay ubicaciones registradas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal de confirmación de eliminación -->
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
