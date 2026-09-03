<?php
/**
 * perfil.php — Página de gestión del perfil del usuario
 * =========================================================
 * Todos los usuarios pueden:
 *  1. Ver su información de cuenta (Cédula, Nombre, Rol).
 *  2. Modificar su Nombre Completo y Cédula de Identidad de forma segura.
 *  3. Ver y modificar sus 5 respuestas de preguntas de seguridad.
 * =========================================================
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

// Validar que el usuario esté autenticado
requireRole();

$cedula = $_SESSION['cedula'];
$error = '';
$success = '';

// Obtener datos iniciales del usuario en sesión
$stmt = $conn->prepare("
    SELECT p.Cedula, p.Nombre, p.Rol, p.Cre_Per, u.Pregunta1_Rpta, u.Pregunta2_Rpta, u.Pregunta3_Rpta, u.Pregunta4_Rpta, u.Pregunta5_Rpta
    FROM Perfiles p
    INNER JOIN Usuarios u ON p.Cedula = u.Cedula
    WHERE p.Cedula = ? 
    LIMIT 1
");
$stmt->bind_param('s', $cedula);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    // Si la sesión activa hace referencia a un usuario que ya no existe en la base de datos,
    // destruimos la sesión para redirigir de forma limpia al inicio.
    header('Location: logout.php');
    exit;
}

// Lista de preguntas de seguridad
$preguntas_seguridad = [
    1 => '¿Cuál era tu apodo de la infancia?',
    2 => '¿En qué ciudad nació tu madre?',
    3 => '¿Cuál es tu animal favorito?',
    4 => '¿Cuál es el nombre de tu primer colegio?',
    5 => '¿Cuál es el nombre de tu mejor amigo de la infancia?'
];

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nueva_cedula = trim($_POST['cedula'] ?? '');
    $nombre       = trim($_POST['nombre'] ?? '');
    
    // Respuestas de seguridad
    $preg1 = strtolower(trim($_POST['preg1'] ?? ''));
    $preg2 = strtolower(trim($_POST['preg2'] ?? ''));
    $preg3 = strtolower(trim($_POST['preg3'] ?? ''));
    $preg4 = strtolower(trim($_POST['preg4'] ?? ''));
    $preg5 = strtolower(trim($_POST['preg5'] ?? ''));

    // Validaciones
    if (empty($nueva_cedula) || empty($nombre) || empty($preg1) || empty($preg2) || empty($preg3) || empty($preg4) || empty($preg5)) {
        $error = 'Todos los campos son obligatorios.';
    } elseif (!preg_match('/^\d{1,8}$/', $nueva_cedula)) {
        $error = 'La cédula debe contener solo números (máximo 8 dígitos).';
    } elseif (strlen($nombre) > 20) {
        $error = 'El nombre completo no debe superar los 20 caracteres.';
    } else {
        // Verificar si la nueva cédula está duplicada (si cambió)
        $duplicada = false;
        if ($nueva_cedula !== $cedula) {
            $stmtC = $conn->prepare("SELECT Cedula FROM Perfiles WHERE Cedula = ? LIMIT 1");
            $stmtCC = $conn->prepare("SELECT Cedula FROM Perfiles WHERE Cedula = ? LIMIT 1");
            $stmtC->bind_param('s', $nueva_cedula);
            $stmtC->execute();
            if ($stmtC->get_result()->num_rows > 0) {
                $duplicada = true;
                $error = 'La cédula ingresada ya está registrada por otro usuario.';
            }
            $stmtC->close();
        }

        if (!$duplicada) {
            // Transacción para actualizar todas las referencias de forma consistente
            $conn->begin_transaction();
            try {
                // Desactivar temporalmente restricciones de clave foránea
                $conn->query("SET FOREIGN_KEY_CHECKS = 0");

                // 1. Actualizar tabla Perfiles (Cédula y Nombre)
                $stmtP = $conn->prepare("UPDATE Perfiles SET Cedula = ?, Nombre = ? WHERE Cedula = ?");
                $stmtP->bind_param('sss', $nueva_cedula, $nombre, $cedula);
                $stmtP->execute();
                $stmtP->close();

                // 2. Actualizar tabla Usuarios (Cédula y Respuestas de seguridad)
                $stmtU = $conn->prepare("UPDATE Usuarios SET Cedula = ?, Pregunta1_Rpta = ?, Pregunta2_Rpta = ?, Pregunta3_Rpta = ?, Pregunta4_Rpta = ?, Pregunta5_Rpta = ? WHERE Cedula = ?");
                $stmtU->bind_param('sssssss', $nueva_cedula, $preg1, $preg2, $preg3, $preg4, $preg5, $cedula);
                $stmtU->execute();
                $stmtU->close();

                // 3. Si el rol es Técnico, actualizar la tabla de Técnicos
                if ($user['Rol'] === 'Tecnico') {
                    $stmtT = $conn->prepare("UPDATE Tecnicos SET Cedula = ? WHERE Cedula = ?");
                    $stmtT->bind_param('ss', $nueva_cedula, $cedula);
                    $stmtT->execute();
                    $stmtT->close();
                }

                // Volver a activar las llaves foráneas
                $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                $conn->commit();

                // Actualizar las variables de sesión del usuario activo
                $_SESSION['cedula'] = $nueva_cedula;
                $_SESSION['nombre'] = $nombre;

                // Actualizar variables locales para renderizar
                $cedula = $nueva_cedula;
                $user['Cedula'] = $nueva_cedula;
                $user['Nombre'] = $nombre;
                $user['Pregunta1_Rpta'] = $preg1;
                $user['Pregunta2_Rpta'] = $preg2;
                $user['Pregunta3_Rpta'] = $preg3;
                $user['Pregunta4_Rpta'] = $preg4;
                $user['Pregunta5_Rpta'] = $preg5;

                $success = '¡Perfil actualizado correctamente!';
            } catch (Exception $e) {
                $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                $conn->rollback();
                $error = 'Error de base de datos al guardar cambios: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Mi Perfil';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header" style="margin-bottom: 1rem;">
    <div>
        <h1 style="font-size:1.35rem; margin-bottom: 0.1rem;">Mi Perfil</h1>
        <p style="font-size:0.8rem; margin:0;">Visualiza tu información y personaliza tus respuestas de seguridad</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" data-autohide style="margin-bottom: 0.75rem; padding: 0.65rem 1rem; font-size: 0.82rem;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success" data-autohide style="margin-bottom: 0.75rem; padding: 0.65rem 1rem; font-size: 0.82rem;"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<style>
.profile-bento-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 1.25rem;
    align-items: start;
}
@media (max-width: 768px) {
    .profile-bento-layout {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- ===== DISEÑO BENTO PARA PERFIL DE USUARIO ===== -->
<div class="profile-bento-layout">

    <!-- Tarjeta Izquierda: Información de Identidad (Visual) -->
    <div class="bento-card" style="display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding: 1.5rem 1.25rem; max-height: 380px;">
        <!-- Avatar Representativo Premium -->
        <div style="width: 76px; height: 76px; border-radius: 50%; background: linear-gradient(135deg, var(--verde-grama), var(--verde-hover)); display: flex; align-items: center; justify-content: center; color: white; font-size: 2.2rem; font-weight: 700; margin-bottom: 0.85rem; box-shadow: var(--shadow-md);">
            <?= strtoupper(substr($user['Nombre'], 0, 1)) ?>
        </div>

        <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-primary); margin-bottom: 0.15rem; word-break: break-word;"><?= htmlspecialchars($user['Nombre']) ?></h2>
        <span class="role-pill role-<?= strtolower($user['Rol']) ?>" style="font-size: 0.7rem; padding: 0.25rem 0.65rem; margin-bottom: 1.25rem;"><?= htmlspecialchars($user['Rol'] === 'Tecnico' ? 'Técnico' : $user['Rol']) ?></span>

        <div style="width: 100%; border-top: 1px solid var(--border-color); padding-top: 0.85rem; text-align: left; font-size: 0.78rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; white-space: nowrap;">
                <span class="text-muted" style="white-space: nowrap;">Miembro desde:</span>
                <span style="font-weight: 600; color: var(--text-primary); white-space: nowrap;"><?= date('d/m/Y h:i A', strtotime($user['Cre_Per'])) ?></span>
            </div>
        </div>
    </div>

    <!-- Tarjeta Derecha/Centro: Formulario y Vista de Modificación -->
    <div class="bento-card" style="padding: 1rem 1.75rem;">
        
        <!-- CABECERA DE LA TARJETA -->
        <div class="card-header" style="margin-bottom: 0.85rem; padding-bottom: 0.4rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                <svg class="card-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:18px; height:18px; color:var(--verde-grama);">
                    <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                Datos del Perfil
            </h3>
            
            <!-- BOTÓN EDITAR (SOLO VISIBLE EN MODO VISTA) -->
            <button type="button" id="btnToggleEdit" class="btn btn-primary btn-sm" style="margin: 0; padding: 0.4rem 1rem; display: flex; align-items: center; gap: 0.35rem; font-size: 0.78rem;">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:14px; height:14px; stroke:currentColor; stroke-width:2.5; stroke-linecap:round; stroke-linejoin:round;">
                    <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Editar Perfil
            </button>
        </div>

        <!-- ==================== MODO VISTA ==================== -->
        <div id="profileViewBlock">
            <div class="register-grid" style="gap: 1.25rem;">
                <!-- Columna Left: Datos -->
                <div class="register-grid-col">
                    <h4 style="font-size:0.75rem; font-weight:700; color:var(--verde-grama); margin-bottom: 0.5rem; border-bottom: 1px dashed var(--border-color); padding-bottom: 0.15rem; text-transform: uppercase; letter-spacing:0.04em;">
                        Información de Cuenta
                    </h4>
                    
                    <div class="form-group" style="margin-bottom: 0.5rem;">
                        <span class="form-label" style="color:var(--text-secondary); margin-bottom: 0.05rem; font-size: 0.75rem;">Cédula de identidad</span>
                        <div style="font-size: 0.88rem; font-weight: 600; color: var(--text-primary); padding: 0.35rem 0; border-bottom: 1px solid var(--border-color);"><?= htmlspecialchars($user['Cedula']) ?></div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0.5rem;">
                        <span class="form-label" style="color:var(--text-secondary); margin-bottom: 0.05rem; font-size: 0.75rem;">Nombre completo</span>
                        <div style="font-size: 0.88rem; font-weight: 600; color: var(--text-primary); padding: 0.35rem 0; border-bottom: 1px solid var(--border-color);"><?= htmlspecialchars($user['Nombre']) ?></div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0.5rem;">
                        <span class="form-label" style="color:var(--text-secondary); margin-bottom: 0.05rem; font-size: 0.75rem;">Rol de usuario</span>
                        <div style="font-size: 0.88rem; font-weight: 600; color: var(--text-primary); padding: 0.35rem 0; border-bottom: 1px solid var(--border-color);"><?= htmlspecialchars($user['Rol'] === 'Tecnico' ? 'Técnico' : $user['Rol']) ?></div>
                    </div>
                </div>

                <!-- Columna Right: Preguntas -->
                <div class="register-grid-col">
                    <h4 style="font-size:0.75rem; font-weight:700; color:var(--verde-grama); margin-bottom: 0.5rem; border-bottom: 1px dashed var(--border-color); padding-bottom: 0.15rem; text-transform: uppercase; letter-spacing:0.04em;">
                        Respuestas de Seguridad
                    </h4>
                    
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <div class="form-group" style="margin-bottom: 0.45rem;">
                            <span class="form-label" style="color:var(--text-secondary); margin-bottom: 0.02rem; font-size: 0.72rem;"><?= $i ?>. <?= htmlspecialchars($preguntas_seguridad[$i]) ?></span>
                            <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-primary); padding: 0.25rem 0; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                                <span class="mask-answer" data-answer="<?= htmlspecialchars($user['Pregunta'.$i.'_Rpta']) ?>" style="letter-spacing: 0.05em;">••••••••</span>
                                <button type="button" class="btn-toggle-mask" style="background: none; border: none; color: var(--verde-grama); cursor: pointer; font-size: 0.7rem; font-weight: 700; padding: 0;" onclick="toggleAnswerMask(this)">Mostrar</button>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
                
                <!-- Footer Vista -->
                <div class="register-footer-actions" style="margin-top: 0.2rem; justify-content: flex-end; flex-direction: row; gap: 1rem; width: 100%;">
                    <a href="<?= dashboardUrl() ?>" class="btn btn-secondary" style="margin: 0; padding: 0.45rem 1.25rem; font-size: 0.8rem;">Ir al Inicio</a>
                </div>
            </div>
        </div>

        <!-- ==================== MODO EDICIÓN ==================== -->
        <div id="profileEditBlock" style="display: none;">
            <form method="POST" action="perfil.php">
                <div class="register-grid" style="gap: 1.25rem;">
                    
                    <!-- Columna Left Form: Datos -->
                    <div class="register-grid-col">
                        <h4 style="font-size:0.75rem; font-weight:700; color:var(--verde-grama); margin-bottom: 0.5rem; border-bottom: 1px dashed var(--border-color); padding-bottom: 0.15rem; text-transform: uppercase; letter-spacing:0.04em;">
                            Modificar Datos
                        </h4>
                        
                        <div class="form-group" style="margin-bottom: 0.5rem;">
                            <label class="form-label" for="profCedula">Cédula de identidad</label>
                            <input type="text" id="profCedula" name="cedula" class="form-control"
                                   value="<?= htmlspecialchars($user['Cedula']) ?>" placeholder="Ej: 12345678" maxlength="8" required style="padding: 0.45rem 0.75rem; font-size: 0.85rem;">
                        </div>

                        <div class="form-group" style="margin-bottom: 0.5rem;">
                            <label class="form-label" for="profNombre">Nombre completo (máximo 20 caracteres)</label>
                            <input type="text" id="profNombre" name="nombre" class="form-control"
                                   value="<?= htmlspecialchars($user['Nombre']) ?>" placeholder="Nombre y Apellido" maxlength="20" required style="padding: 0.45rem 0.75rem; font-size: 0.85rem;">
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 0.5rem;">
                            <label class="form-label">Rol en el sistema (No modificable)</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['Rol'] === 'Tecnico' ? 'Técnico' : $user['Rol']) ?>" disabled style="background: var(--bg-card-alt); cursor: not-allowed; opacity: 0.8; padding: 0.45rem 0.75rem; font-size: 0.85rem;">
                        </div>
                    </div>

                    <!-- Columna Right Form: Preguntas -->
                    <div class="register-grid-col">
                        <h4 style="font-size:0.75rem; font-weight:700; color:var(--verde-grama); margin-bottom: 0.5rem; border-bottom: 1px dashed var(--border-color); padding-bottom: 0.15rem; text-transform: uppercase; letter-spacing:0.04em;">
                            Modificar Preguntas
                        </h4>

                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <div class="form-group" style="margin-bottom: 0.45rem;">
                                <label class="form-label" for="profPreg<?= $i ?>" style="font-size:0.72rem; margin-bottom: 0.05rem;"><?= $i ?>. <?= htmlspecialchars($preguntas_seguridad[$i]) ?></label>
                                <input type="text" id="profPreg<?= $i ?>" name="preg<?= $i ?>" class="form-control"
                                       value="<?= htmlspecialchars($user['Pregunta'.$i.'_Rpta']) ?>" placeholder="Tu respuesta" required autocomplete="off" style="padding: 0.4rem 0.75rem; font-size: 0.82rem;">
                            </div>
                        <?php endfor; ?>
                    </div>

                    <!-- Footer Edición -->
                    <div class="register-footer-actions" style="margin-top: 0.2rem; justify-content: flex-end; flex-direction: row; gap: 0.75rem; width: 100%;">
                        <button type="button" id="btnCancelEdit" class="btn btn-secondary" style="margin: 0; padding: 0.45rem 1.25rem; font-size: 0.8rem;">Cancelar</button>
                        <button type="submit" class="btn btn-primary" style="margin: 0; padding: 0.45rem 1.75rem; font-size: 0.8rem;">Guardar Cambios</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnToggleEdit = document.getElementById('btnToggleEdit');
    const btnCancelEdit = document.getElementById('btnCancelEdit');
    const profileViewBlock = document.getElementById('profileViewBlock');
    const profileEditBlock = document.getElementById('profileEditBlock');

    if (btnToggleEdit && btnCancelEdit && profileViewBlock && profileEditBlock) {
        btnToggleEdit.addEventListener('click', function() {
            profileViewBlock.style.display = 'none';
            profileEditBlock.style.display = 'block';
            btnToggleEdit.style.display = 'none'; // Ocultar botón de edición en modo edición
        });

        btnCancelEdit.addEventListener('click', function() {
            profileEditBlock.style.display = 'none';
            profileViewBlock.style.display = 'block';
            btnToggleEdit.style.display = 'flex'; // Mostrar botón de edición en modo vista
        });
    }

    // Si hubo un error en el guardado (POST), volver a mostrar directamente el modo edición
    <?php if ($error): ?>
    if (profileViewBlock && profileEditBlock && btnToggleEdit) {
        profileViewBlock.style.display = 'none';
        profileEditBlock.style.display = 'block';
        btnToggleEdit.style.display = 'none';
    }
    <?php endif; ?>
});

function toggleAnswerMask(btn) {
    const span = btn.previousElementSibling;
    if (span.textContent === '••••••••') {
        span.textContent = span.getAttribute('data-answer');
        btn.textContent = 'Ocultar';
    } else {
        span.textContent = '••••••••';
        btn.textContent = 'Mostrar';
    }
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
