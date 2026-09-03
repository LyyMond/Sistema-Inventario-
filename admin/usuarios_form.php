<?php
/**
 * admin/usuarios_form.php
 * Formulario de EDICIÓN de usuario existente (?cedula=X).
 * El administrador NO puede editar perfiles de otros Administradores.
 * El administrador solo puede cambiar el rol y el estado (activo/inactivo).
 * La cédula y el nombre son de solo lectura y no pueden ser modificados.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Administrador', '..');

$cedula = trim($_GET['cedula'] ?? '');
if ($cedula === '') {
    header('Location: usuarios.php');
    exit;
}

$error  = '';
$usuario = ['Cedula'=>'','Nombre'=>'','Rol'=>'Operador','Activo'=>1];

// Cargar datos del usuario
$stmt = $conn->prepare(
    "SELECT p.Cedula, p.Nombre, p.Rol, p.Activo
     FROM Perfiles p WHERE p.Cedula = ? LIMIT 1"
);
$stmt->bind_param('s', $cedula);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    header('Location: usuarios.php');
    exit;
}
$usuario = $res->fetch_assoc();
$stmt->close();

// Bloquear edición de otros admins
if ($usuario['Rol'] === 'Administrador' && $usuario['Cedula'] !== $_SESSION['cedula']) {
    header('Location: usuarios.php');
    exit;
}

// Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rol        = trim($_POST['rol']      ?? '');
    $activo     = isset($_POST['activo']) ? 1 : 0;

    if (!in_array($rol, ['Administrador','Operador','Tecnico'])) {
        $error = 'El rol seleccionado no es válido.';
    } else {
        // Actualizar Perfil (solo Rol y Activo)
        $stmt = $conn->prepare(
            "UPDATE Perfiles SET Rol=?, Activo=? WHERE Cedula=?"
        );
        $stmt->bind_param('sis', $rol, $activo, $cedula);
        $stmt->execute();
        $stmt->close();

        // Si cambió a Técnico, asegurar que existe en tabla Tecnicos
        if ($rol === 'Tecnico') {
            $chkT = $conn->prepare("SELECT CIT FROM Tecnicos WHERE Cedula=? LIMIT 1");
            $chkT->bind_param('s', $cedula);
            $chkT->execute();
            if ($chkT->get_result()->num_rows === 0) {
                $ins = $conn->prepare("INSERT INTO Tecnicos (Cedula) VALUES (?)");
                $ins->bind_param('s', $cedula);
                $ins->execute();
                $ins->close();
            }
            $chkT->close();
        }

        if (empty($error)) {
            header('Location: usuarios.php?ok=edit');
            exit;
        }
    }
}

$pageTitle = 'Editar usuario';
$basePath  = '..';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Editar usuario</h1>
    </div>
    <a href="usuarios.php" class="btn btn-secondary">← Volver</a>
</div>

<div style="max-width:540px">
    <div class="bento-card">
        <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label" for="cedula">Cédula</label>
                <input type="text" id="cedula" name="cedula" class="form-control"
                       value="<?= htmlspecialchars($usuario['Cedula']) ?>"
                       readonly style="opacity:0.7" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="nombre">Nombre y Apellido</label>
                <input type="text" id="nombre" name="nombre" class="form-control"
                       value="<?= htmlspecialchars($usuario['Nombre']) ?>"
                       readonly style="opacity:0.7" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="rol">Rol *</label>
                <select id="rol" name="rol" class="form-control" required>
                    <option value="Operador"      <?= $usuario['Rol']==='Operador'      ?'selected':'' ?>>Operador</option>
                    <option value="Tecnico"       <?= $usuario['Rol']==='Tecnico'       ?'selected':'' ?>>Técnico</option>
                    <option value="Administrador" <?= $usuario['Rol']==='Administrador' ?'selected':'' ?>>Administrador</option>
                </select>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer">
                    <input type="checkbox" name="activo" value="1" <?= $usuario['Activo'] ? 'checked' : '' ?>>
                    <span class="form-label" style="margin:0">Cuenta activa</span>
                </label>
            </div>
            <div style="display:flex;gap:0.75rem;margin-top:1.5rem">
                <button type="submit" class="btn btn-primary">
                    Guardar cambios
                </button>
                <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
