<?php
/**
 * admin/ubicaciones_form.php
 * Formulario para crear o editar ubicaciones.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Administrador', '..');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$error = '';
$ubicacion = ['Nombre' => ''];

if ($isEdit) {
    $stmt = $conn->prepare("SELECT Nombre FROM Ubicaciones WHERE ID = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        header('Location: ubicaciones.php');
        exit;
    }
    $ubicacion = $res->fetch_assoc();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');

    if (empty($nombre)) {
        $error = 'El nombre de la ubicación es obligatorio.';
    } else {
        // Verificar si ya existe el nombre
        $chk = $conn->prepare("SELECT ID FROM Ubicaciones WHERE Nombre = ? AND ID != ? LIMIT 1");
        $chk->bind_param('si', $nombre, $id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = 'Ya existe una ubicación con ese nombre.';
        }
        $chk->close();

        if (empty($error)) {
            if ($isEdit) {
                $stmt = $conn->prepare("UPDATE Ubicaciones SET Nombre=? WHERE ID=?");
                $stmt->bind_param('si', $nombre, $id);
            } else {
                $stmt = $conn->prepare("INSERT INTO Ubicaciones (Nombre) VALUES (?)");
                $stmt->bind_param('s', $nombre);
            }
            $stmt->execute();
            $stmt->close();
            header('Location: ubicaciones.php?ok=' . ($isEdit ? 'edit' : 'add'));
            exit;
        }
    }
    $ubicacion['Nombre'] = $nombre;
}

$pageTitle = $isEdit ? 'Editar Ubicación' : 'Nueva Ubicación';
$basePath  = '..';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= $isEdit ? 'Editar Ubicación' : 'Agregar nueva ubicación' ?></h1>
    </div>
    <a href="ubicaciones.php" class="btn btn-secondary">← Volver</a>
</div>

<div style="max-width:500px">
    <div class="bento-card">
        <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label" for="nombre">Nombre de la Ubicación / Sala *</label>
                <input type="text" id="nombre" name="nombre" class="form-control"
                       value="<?= htmlspecialchars($ubicacion['Nombre']) ?>" maxlength="100" required
                       placeholder="Ej: Oficina 1A, Sala de Reuniones...">
            </div>
            <div style="display:flex;gap:0.75rem;margin-top:1.5rem">
                <button type="submit" class="btn btn-primary">
                    <?= $isEdit ? 'Guardar cambios' : 'Agregar ubicación' ?>
                </button>
                <a href="ubicaciones.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
