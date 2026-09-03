<?php
/**
 * admin/usuarios.php
 * Lista de todos los usuarios del sistema.
 * El administrador puede crear nuevos usuarios, editar los existentes
 * (excepto otros administradores) y activar/desactivar cuentas.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Administrador', '..');

// Obtener todos los perfiles
$usuarios = $conn->query(
    "SELECT Cedula, Nombre, Rol, Activo, Cre_Per FROM Perfiles ORDER BY Cre_Per DESC"
);

$flash = '';
if (isset($_GET['ok'])) {
    $msgs = [
        'add'    => 'Usuario creado correctamente.',
        'edit'   => 'Usuario actualizado correctamente.',
        'delete' => 'Usuario eliminado correctamente.',
        'toggle' => 'Estado del usuario actualizado.',
    ];
    $flash = $msgs[$_GET['ok']] ?? '';
}

$pageTitle = 'Usuarios';
$basePath  = '..';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Gestión de usuarios</h1>
        <p>Administra las cuentas de acceso al sistema.</p>
    </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-success" data-autohide><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="bento-card">
    <div class="table-search">
        <input type="text" class="form-control" placeholder="Buscar por nombre, cédula, rol..."
               data-search-table="tablaUsuarios" style="max-width:380px">
    </div>
    <div class="table-wrapper">
        <table class="data-table" id="tablaUsuarios">
            <thead>
                <tr>
                    <th>Cédula</th><th>Nombre</th><th>Rol</th>
                    <th>Estado</th><th>Fecha registro</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($u = $usuarios->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($u['Cedula']) ?></td>
                <td><?= htmlspecialchars($u['Nombre']) ?></td>
                <td><span class="role-pill role-<?= strtolower($u['Rol']) ?>"><?= htmlspecialchars($u['Rol'] === 'Tecnico' ? 'Técnico' : $u['Rol']) ?></span></td>
                <td>
                    <?php if ($u['Activo']): ?>
                        <span class="badge badge-finalizado">Activo</span>
                    <?php else: ?>
                        <span class="badge badge-urgente">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td><?= date('d/m/Y', strtotime($u['Cre_Per'])) ?></td>
                <td>
                    <div class="table-actions">
                        <?php if ($u['Rol'] !== 'Administrador'): ?>
                            <a href="usuarios_form.php?cedula=<?= urlencode($u['Cedula']) ?>" class="btn btn-outline btn-sm">Editar</a>
                            <button class="btn btn-danger btn-sm"
                                    data-delete-url="usuarios_delete.php?cedula=<?= urlencode($u['Cedula']) ?>"
                                    data-delete-name="al usuario '<?= htmlspecialchars(addslashes($u['Nombre'])) ?>'">
                                Eliminar
                            </button>
                        <?php elseif ($u['Cedula'] !== $_SESSION['cedula']): ?>
                            <span class="text-muted" style="font-size:0.78rem">No editable</span>
                        <?php else: ?>
                            <span class="text-muted" style="font-size:0.78rem">Tu cuenta</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
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
