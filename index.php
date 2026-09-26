<?php
/**
 * index.php — Punto de entrada del sistema
 * =========================================================
 * Maneja DOS funciones en un solo archivo:
 *  1. LOGIN: Verifica cédula + contraseña y crea la sesión.
 *  2. REGISTRO: Crea un nuevo Perfil + Usuario en la BD.
 *
 * Flujo de login:
 *   POST → buscar en Usuarios → validar hash → cargar Perfil
 *   → iniciar sesión → redirigir al dashboard según rol.
 *
 * Flujo de registro:
 *   POST → validar campos → insertar Perfiles → insertar Usuarios
 *   → si Tecnico, insertar en Tecnicos → redirigir con éxito.
 * =========================================================
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

// Migración automática para agregar columnas de preguntas de seguridad si no existen
$check_cols = $conn->query("SHOW COLUMNS FROM Usuarios LIKE 'Pregunta1_Rpta'");
if ($check_cols && $check_cols->num_rows === 0) {
    $conn->query("ALTER TABLE Usuarios 
        ADD COLUMN Pregunta1_Rpta VARCHAR(255) NOT NULL DEFAULT '',
        ADD COLUMN Pregunta2_Rpta VARCHAR(255) NOT NULL DEFAULT '',
        ADD COLUMN Pregunta3_Rpta VARCHAR(255) NOT NULL DEFAULT '',
        ADD COLUMN Pregunta4_Rpta VARCHAR(255) NOT NULL DEFAULT '',
        ADD COLUMN Pregunta5_Rpta VARCHAR(255) NOT NULL DEFAULT ''");
}

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Si ya hay sesión activa, redirigir al dashboard correspondiente
if (!empty($_SESSION['cedula'])) {
    require_once __DIR__ . '/includes/auth.php';
    header('Location: ' . dashboardUrl());
    exit;
}

$error   = '';
$success = '';
$action  = $_POST['action'] ?? '';

/* =========================================================
   PROCESAR LOGIN
   ========================================================= */
if ($action === 'login') {
    $cedula    = trim($_POST['cedula']    ?? '');
    $password  = trim($_POST['password'] ?? '');

    if (empty($cedula) || empty($password)) {
        $error = 'Por favor ingresa tu cédula y contraseña.';
    } else {
        // Buscar usuario con su perfil en un JOIN
        $hash  = hash('sha256', $password);
        $stmt  = $conn->prepare(
            "SELECT p.Cedula, p.Nombre, p.Rol, p.Activo
             FROM Usuarios u
             INNER JOIN Perfiles p ON u.Cedula = p.Cedula
             WHERE u.Cedula = ? AND u.Contrasena = ?
             LIMIT 1"
        );
        $stmt->bind_param('ss', $cedula, $hash);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error = 'Cédula o contraseña incorrectos.';
        } else {
            $user = $result->fetch_assoc();
            if (!$user['Activo']) {
                $error = 'Tu cuenta está desactivada. Contacta al administrador.';
            } else {
                // Crear sesión
                session_regenerate_id(true);
                $_SESSION['cedula'] = $user['Cedula'];
                $_SESSION['nombre'] = $user['Nombre'];
                $_SESSION['rol']    = $user['Rol'];

                // Si es técnico, guardar su CIT
                if ($user['Rol'] === 'Tecnico') {
                    $stmtT = $conn->prepare("SELECT CIT FROM Tecnicos WHERE Cedula = ? LIMIT 1");
                    $stmtT->bind_param('s', $user['Cedula']);
                    $stmtT->execute();
                    $resT = $stmtT->get_result()->fetch_assoc();
                    $_SESSION['cit'] = $resT['CIT'] ?? null;
                    $stmtT->close();
                }

                $stmt->close();
                require_once __DIR__ . '/includes/auth.php';
                header('Location: ' . dashboardUrl());
                exit;
            }
        }
        $stmt->close();
    }
}

/* =========================================================
   PROCESAR REGISTRO
   ========================================================= */
if ($action === 'register') {
    $cedula    = trim($_POST['cedula']    ?? '');
    $nombre    = trim($_POST['nombre']    ?? '');
    $password  = trim($_POST['password'] ?? '');
    $confirm   = trim($_POST['confirm']  ?? '');
    $rol       = trim($_POST['rol']      ?? '');
    $adminCode = trim($_POST['admin_code'] ?? '');
    
    // Capturar y limpiar respuestas de preguntas de seguridad
    $preg1 = strtolower(trim($_POST['preg1'] ?? ''));
    $preg2 = strtolower(trim($_POST['preg2'] ?? ''));
    $preg3 = strtolower(trim($_POST['preg3'] ?? ''));
    $preg4 = strtolower(trim($_POST['preg4'] ?? ''));
    $preg5 = strtolower(trim($_POST['preg5'] ?? ''));

    // Validaciones básicas
    if (empty($cedula) || empty($nombre) || empty($password) || empty($rol) ||
        empty($preg1) || empty($preg2) || empty($preg3) || empty($preg4) || empty($preg5)) {
        $error = 'Todos los campos, incluyendo las 5 preguntas de seguridad, son obligatorios.';
    } else {
        if (!preg_match('/^\d+$/', $cedula)) {
            $error = 'La cédula debe contener solo números.';
            $cedula = '';
        } elseif (!preg_match('/^\d{1,8}$/', $cedula)) {
            $error = 'La cédula debe tener máximo 8 dígitos.';
            $cedula = '';
        } elseif (mb_strlen($nombre, 'UTF-8') > 30) {
            $error = 'El nombre y apellido no debe superar los 30 caracteres.';
            $nombre = '';
        } elseif (!preg_match('/^[a-zA-ZáéíóúüñÁÉÍÓÚÜÑ\s]+$/u', $nombre)) {
            $error = 'El nombre y apellido solo debe contener letras (pueden ser acentuadas) y espacios.';
            $nombre = '';
        } elseif (strlen($password) < 6 || strlen($password) > 12) {
            $error = 'La contraseña debe tener entre 6 y 12 caracteres.';
            $password = '';
            $confirm = '';
        } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^a-zA-Z0-9]/', $password)) {
            $error = 'La contraseña debe incluir al menos una letra mayúscula, un número y un carácter especial.';
            $password = '';
            $confirm = '';
        } elseif ($password !== $confirm) {
            $error = 'Las contraseñas no coinciden.';
            $confirm = '';
        } elseif (!in_array($rol, ['Administrador', 'Operador', 'Tecnico'])) {
            $error = 'Rol no válido.';
            $rol = '';
        } elseif ($rol === 'Administrador' && $adminCode !== ADMIN_SECRET_CODE) {
            $error = 'Código de administración incorrecto.';
            $adminCode = '';
        } elseif (mb_strlen($preg1, 'UTF-8') > 20 || mb_strlen($preg2, 'UTF-8') > 20 || mb_strlen($preg3, 'UTF-8') > 20 || mb_strlen($preg4, 'UTF-8') > 20 || mb_strlen($preg5, 'UTF-8') > 20) {
            $error = 'Las respuestas de seguridad no deben superar los 20 caracteres.';
            if (mb_strlen($preg1, 'UTF-8') > 20) { $preg1 = ''; }
            if (mb_strlen($preg2, 'UTF-8') > 20) { $preg2 = ''; }
            if (mb_strlen($preg3, 'UTF-8') > 20) { $preg3 = ''; }
            if (mb_strlen($preg4, 'UTF-8') > 20) { $preg4 = ''; }
            if (mb_strlen($preg5, 'UTF-8') > 20) { $preg5 = ''; }
        } else {
            // Verificar que la cédula no exista ya
            $stmtCheck = $conn->prepare("SELECT Cedula FROM Perfiles WHERE Cedula = ? LIMIT 1");
            $stmtCheck->bind_param('s', $cedula);
            $stmtCheck->execute();
            if ($stmtCheck->get_result()->num_rows > 0) {
                $error = 'Ya existe un usuario registrado con esa cédula.';
                $cedula = '';
            } else {
                // Insertar en Perfiles
                $stmtP = $conn->prepare(
                    "INSERT INTO Perfiles (Cedula, Rol, Nombre) VALUES (?, ?, ?)"
                );
                $stmtP->bind_param('sss', $cedula, $rol, $nombre);
                $stmtP->execute();
                $stmtP->close();

                // Insertar en Usuarios (contraseña como hash SHA-256 + respuestas de seguridad)
                $hash   = hash('sha256', $password);
                $stmtU  = $conn->prepare("INSERT INTO Usuarios (Cedula, Contrasena, Pregunta1_Rpta, Pregunta2_Rpta, Pregunta3_Rpta, Pregunta4_Rpta, Pregunta5_Rpta) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtU->bind_param('sssssss', $cedula, $hash, $preg1, $preg2, $preg3, $preg4, $preg5);
                $stmtU->execute();
                $stmtU->close();

                // Si el rol es Tecnico, registrar en tabla Tecnicos y obtener su CIT
                $cit = null;
                if ($rol === 'Tecnico') {
                    $stmtT = $conn->prepare("INSERT INTO Tecnicos (Cedula) VALUES (?)");
                    $stmtT->bind_param('s', $cedula);
                    $stmtT->execute();
                    $cit = $stmtT->insert_id;
                    $stmtT->close();
                }

                // Notificar registro exitoso para que inicie sesión manualmente
                $success = '¡Registro exitoso! Por favor ingresa tus datos para iniciar sesión.';
                $action  = '';
                $cedula  = '';
                $nombre  = '';
                $rol     = '';
            }
            $stmtCheck->close();
        }
    }
}

$preguntas_seguridad = [
    1 => '¿Cuál era tu apodo de la infancia?',
    2 => '¿En qué ciudad nació tu madre?',
    3 => '¿Cuál es tu animal favorito?',
    4 => '¿Cuál es el nombre de tu primer colegio?',
    5 => '¿Cuál es el nombre de tu mejor amigo de la infancia?'
];

/* =========================================================
   PROCESAR RECUPERACIÓN DE CONTRASEÑA
   ========================================================= */
if (isset($_GET['action']) && $_GET['action'] === 'recovery_cancel') {
    unset($_SESSION['recovery_cedula'], $_SESSION['recovery_step'], $_SESSION['recovery_q1'], $_SESSION['recovery_q2']);
    header('Location: index.php');
    exit;
}

if ($action === 'recovery_step1') {
    $cedula = trim($_POST['cedula'] ?? '');
    if (empty($cedula)) {
        $error = 'Por favor ingresa tu cédula.';
    } else {
        // Verificar si la cédula existe en Usuarios
        $stmt = $conn->prepare("SELECT Cedula FROM Usuarios WHERE Cedula = ? LIMIT 1");
        $stmt->bind_param('s', $cedula);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = 'La cédula ingresada no está registrada en el sistema.';
        } else {
            // Cédula existe, elegir 2 preguntas al azar
            $keys = array_rand($preguntas_seguridad, 2);
            $_SESSION['recovery_cedula'] = $cedula;
            $_SESSION['recovery_q1'] = $keys[0];
            $_SESSION['recovery_q2'] = $keys[1];
            $_SESSION['recovery_step'] = 2;
        }
        $stmt->close();
    }
}

if ($action === 'recovery_step2') {
    $ans1 = strtolower(trim($_POST['ans1'] ?? ''));
    $ans2 = strtolower(trim($_POST['ans2'] ?? ''));
    $cedula = $_SESSION['recovery_cedula'] ?? '';
    $q1_idx = $_SESSION['recovery_q1'] ?? null;
    $q2_idx = $_SESSION['recovery_q2'] ?? null;

    if (empty($ans1) || empty($ans2) || !$cedula || !$q1_idx || !$q2_idx) {
        $error = 'Todas las respuestas son obligatorias.';
    } else {
        // Consultar respuestas guardadas
        $stmt = $conn->prepare("SELECT Pregunta1_Rpta, Pregunta2_Rpta, Pregunta3_Rpta, Pregunta4_Rpta, Pregunta5_Rpta FROM Usuarios WHERE Cedula = ? LIMIT 1");
        $stmt->bind_param('s', $cedula);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($res) {
            $stored_ans1 = $res["Pregunta{$q1_idx}_Rpta"];
            $stored_ans2 = $res["Pregunta{$q2_idx}_Rpta"];

            if (strtolower(trim($stored_ans1)) === $ans1 && strtolower(trim($stored_ans2)) === $ans2) {
                $_SESSION['recovery_step'] = 3;
            } else {
                $error = 'Una o ambas respuestas de seguridad son incorrectas. Inténtalo de nuevo.';
                if (strtolower(trim($stored_ans1)) !== $ans1) {
                    $ans1 = '';
                }
                if (strtolower(trim($stored_ans2)) !== $ans2) {
                    $ans2 = '';
                }
            }
        } else {
            $error = 'Error de sesión de recuperación. Inténtalo de nuevo.';
            unset($_SESSION['recovery_cedula'], $_SESSION['recovery_step'], $_SESSION['recovery_q1'], $_SESSION['recovery_q2']);
        }
    }
}

if ($action === 'recovery_step3') {
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_new = trim($_POST['confirm_new_password'] ?? '');
    $cedula = $_SESSION['recovery_cedula'] ?? '';
    $step = $_SESSION['recovery_step'] ?? 1;

    if (empty($new_password) || empty($confirm_new) || !$cedula || $step != 3) {
        $error = 'Por favor completa todos los campos.';
    } elseif (strlen($new_password) < 6 || strlen($new_password) > 12) {
        $error = 'La contraseña debe tener entre 6 y 12 caracteres.';
    } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[0-9]/', $new_password) || !preg_match('/[^a-zA-Z0-9]/', $new_password)) {
        $error = 'La contraseña debe incluir al menos una letra mayúscula, un número y un carácter especial.';
    } elseif ($new_password !== $confirm_new) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $hash = hash('sha256', $new_password);
        $stmt = $conn->prepare("UPDATE Usuarios SET Contrasena = ? WHERE Cedula = ?");
        $stmt->bind_param('ss', $hash, $cedula);
        
        if ($stmt->execute()) {
            $success = '¡Contraseña restablecida con éxito! Ya puedes iniciar sesión.';
            unset($_SESSION['recovery_cedula'], $_SESSION['recovery_step'], $_SESSION['recovery_q1'], $_SESSION['recovery_q2']);
        } else {
            $error = 'Ocurrió un error al actualizar la contraseña.';
        }
        $stmt->close();
    }
}

// Mensaje de redirección (sesión expirada, acceso denegado)
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'session')   $error = 'Tu sesión ha expirado. Por favor inicia sesión nuevamente.';
    if ($_GET['msg'] === 'forbidden') $error = 'No tienes permiso para acceder a esa sección.';
    if ($_GET['msg'] === 'logout')    $success = 'Sesión cerrada correctamente.';
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema institucional de gestión de tickets e inventario — Iniciar sesión">
    <title>Acceso — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <style>
        /* Estilos adicionales para el modo oscuro en la página de login */
        body.login-page-body { background: none; }
        .login-page { min-height: 100vh; }
    </style>
</head>
<body class="login-page-body">

<!-- Botón de tema flotante para la página de login -->
<button id="themeToggle" class="theme-btn" title="Cambiar tema"
        style="position:fixed;top:1rem;right:1rem;z-index:99;background:rgba(0,0,0,0.3);border-color:rgba(255,255,255,0.3)">
    <svg id="iconSun" class="theme-icon" viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2"/>
        <path d="M12 2v2M12 20v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M2 12h2M20 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </svg>
    <svg id="iconMoon" class="theme-icon hidden" viewBox="0 0 24 24" fill="none">
        <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
</button>

<?php
$showRecovery = isset($_SESSION['recovery_step']) || (isset($_GET['action']) && $_GET['action'] === 'recovery');
$showRegister = !$showRecovery && ($action === 'register' || ($error && $action === 'register'));
$showLogin = !$showRecovery && !$showRegister;
?>
<div class="login-page">

    <!-- Alertas Globales -->
    <?php if ($error):   ?><div class="alert alert-danger"  style="width: 100%; max-width: 480px; border-radius: 8px; margin-bottom: 0; box-shadow: var(--shadow-sm);"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success" style="width: 100%; max-width: 480px; border-radius: 8px; margin-bottom: 0; box-shadow: var(--shadow-sm);"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <!-- ===== CAJA DE LOGIN ===== -->
    <div class="login-box" id="loginBox" style="display: <?= $showLogin ? 'block' : 'none' ?>">
        <!-- Encabezado con logo -->
        <div class="login-logo">
            <svg class="logo-svg" viewBox="0 0 24 24" fill="none">
                <path d="M9 12h6M9 16h6M9 8h6M5 4h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1z" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                <circle cx="12" cy="3" r="1.5" fill="#fff"/>
            </svg>
            <h1><?= APP_NAME ?></h1>
            <p>Sistema institucional de soporte técnico</p>
        </div>

        <!-- Formulario de Login -->
        <div class="login-form-body" id="loginForm">
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="login">

                <div class="form-group">
                    <label class="form-label" for="loginCedula">Cédula de identidad</label>
                    <input type="text" id="loginCedula" name="cedula" class="form-control"
                           placeholder="Ej: 12345678" maxlength="8"
                           value="<?= htmlspecialchars($_POST['cedula'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <label class="form-label" for="loginPassword" style="margin:0">Contraseña</label>
                        <a href="#" id="showRecovery" style="font-size:0.75rem; color:var(--text-muted); font-weight:600">¿Olvidaste tu contraseña?</a>
                    </div>
                    <div class="password-wrapper" style="margin-top:0.4rem">
                        <input type="password" id="loginPassword" name="password" class="form-control"
                               placeholder="••••••••" required>
                        <button type="button" class="password-toggle-btn" data-target="loginPassword" aria-label="Mostrar contraseña" title="Mostrar contraseña">
                            <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                </div>

                <div class="mt-2">
                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        Iniciar sesión
                    </button>
                </div>
            </form>

            <hr class="divider" style="margin-top:1.5rem">
            <p class="text-center text-muted" style="font-size:0.85rem">
                ¿No tienes cuenta?
                <a href="#" id="showRegister" style="color:var(--verde-grama);font-weight:600">Regístrate aquí</a>
            </p>
        </div>
    </div>

    <!-- ===== CAJA DE REGISTRO ===== -->
    <div class="login-box" id="registerBox" style="display:none">
        <div class="login-logo">
            <svg class="logo-svg" viewBox="0 0 24 24" fill="none">
                <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z" fill="#fff"/>
            </svg>
            <div>
                <h1 style="margin:0;font-size:1.4rem">Crear cuenta</h1>
                <p style="margin:0;font-size:0.82rem;opacity:0.8">Registro de nuevo usuario del sistema</p>
            </div>
        </div>

        <!-- Pestañas: Usuario Normal | Administrador -->
        <div class="tab-bar">
            <button class="tab-btn active" data-tab="tabUsuario">Usuario / Técnico</button>
            <button class="tab-btn"        data-tab="tabAdmin">Administrador</button>
        </div>

        <!-- Pestaña: Operador / Técnico -->
        <div id="tabUsuario" class="tab-content active">
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="register">

                <div class="register-grid">
                    <!-- Columna Izquierda: Datos Básicos -->
                    <div class="register-grid-col">
                        <div class="form-group">
                            <label class="form-label" for="regCedula">Cédula de identidad</label>
                            <input type="text" id="regCedula" name="cedula" class="form-control"
                                   placeholder="Ej: 12345678" maxlength="8" pattern="\d+"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                   value="<?= htmlspecialchars($cedula ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="regNombre">Nombre y Apellido</label>
                            <input type="text" id="regNombre" name="nombre" class="form-control"
                                   placeholder="Nombre y Apellido" maxlength="30" pattern="[a-zA-ZáéíóúüñÁÉÍÓÚÜÑ\s]+"
                                   value="<?= htmlspecialchars($nombre ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="regRol">Rol</label>
                            <select id="regRol" name="rol" class="form-control" required>
                                <option value="">-- Seleccionar rol --</option>
                                <option value="Operador" <?= (isset($rol) && $rol === 'Operador') ? 'selected' : '' ?>>Operador</option>
                                <option value="Tecnico" <?= (isset($rol) && $rol === 'Tecnico') ? 'selected' : '' ?>>Técnico</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="regPass">Contraseña</label>
                            <div class="password-wrapper">
                                <input type="password" id="regPass" name="password" class="form-control"
                                       placeholder="6-12 caracteres (1 Mayúscula, 1 Número, 1 Especial)" minlength="6" maxlength="12" required>
                                <button type="button" class="password-toggle-btn" data-target="regPass" aria-label="Mostrar contraseña" title="Mostrar contraseña">
                                    <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="regConfirm">Confirmar contraseña</label>
                            <div class="password-wrapper">
                                <input type="password" id="regConfirm" name="confirm" class="form-control"
                                       placeholder="Repite la contraseña" minlength="6" maxlength="12" required>
                                <button type="button" class="password-toggle-btn" data-target="regConfirm" aria-label="Mostrar contraseña" title="Mostrar contraseña">
                                    <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Preguntas de Seguridad -->
                    <div class="register-grid-col">
                        <div class="form-group" style="margin-bottom:0.6rem">
                            <label class="form-label" style="font-weight:700;color:var(--verde-grama);border-bottom:1px solid var(--border-color);padding-bottom:0.25rem">
                                Preguntas de seguridad (Obligatorias)
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="regPreg1">1. ¿Cuál era tu apodo de la infancia?</label>
                            <input type="text" id="regPreg1" name="preg1" class="form-control" placeholder="Tu respuesta" maxlength="20" value="<?= htmlspecialchars($preg1 ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="regPreg2">2. ¿En qué ciudad nació tu madre?</label>
                            <input type="text" id="regPreg2" name="preg2" class="form-control" placeholder="Tu respuesta" maxlength="20" value="<?= htmlspecialchars($preg2 ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="regPreg3">3. ¿Cuál es tu animal favorito?</label>
                            <input type="text" id="regPreg3" name="preg3" class="form-control" placeholder="Tu respuesta" maxlength="20" value="<?= htmlspecialchars($preg3 ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="regPreg4">4. ¿Cuál es el nombre de tu primer colegio?</label>
                            <input type="text" id="regPreg4" name="preg4" class="form-control" placeholder="Tu respuesta" maxlength="20" value="<?= htmlspecialchars($preg4 ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="regPreg5">5. ¿Cuál es el nombre de tu mejor amigo de la infancia?</label>
                            <input type="text" id="regPreg5" name="preg5" class="form-control" placeholder="Tu respuesta" maxlength="20" value="<?= htmlspecialchars($preg5 ?? '') ?>" required>
                        </div>
                    </div>

                    <!-- Botón de Envío -->
                    <div class="register-footer-actions">
                        <button type="submit" class="btn btn-primary btn-block btn-lg" style="max-width:320px">
                            Crear cuenta
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Pestaña: Administrador -->
        <div id="tabAdmin" class="tab-content">
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="rol"    value="Administrador">

                <div class="register-grid">
                    <!-- Columna Izquierda: Datos Básicos -->
                    <div class="register-grid-col">
                        <div class="form-group">
                            <label class="form-label" for="admCedula">Cédula de identidad</label>
                            <input type="text" id="admCedula" name="cedula" class="form-control"
                                   placeholder="Ej: 12345678" maxlength="8" pattern="\d+"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                   value="<?= htmlspecialchars($cedula ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="admNombre">Nombre y Apellido</label>
                            <input type="text" id="admNombre" name="nombre" class="form-control"
                                   placeholder="Nombre y Apellido" maxlength="30" pattern="[a-zA-ZáéíóúüñÁÉÍÓÚÜÑ\s]+"
                                   value="<?= htmlspecialchars($nombre ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="admCode">Código de administración</label>
                            <input type="password" id="admCode" name="admin_code" class="form-control"
                                   placeholder="Código secreto institucional"
                                   value="<?= htmlspecialchars($adminCode ?? '') ?>" required>
                            <p class="form-hint">Solicita este código al administrador.</p>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="admPass">Contraseña</label>
                            <div class="password-wrapper">
                                <input type="password" id="admPass" name="password" class="form-control"
                                       placeholder="6-12 caracteres (1 Mayúscula, 1 Número, 1 Especial)" minlength="6" maxlength="12" required>
                                <button type="button" class="password-toggle-btn" data-target="admPass" aria-label="Mostrar contraseña" title="Mostrar contraseña">
                                    <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="admConfirm">Confirmar contraseña</label>
                            <div class="password-wrapper">
                                <input type="password" id="admConfirm" name="confirm" class="form-control"
                                       placeholder="Repite la contraseña" minlength="6" maxlength="12" required>
                                <button type="button" class="password-toggle-btn" data-target="admConfirm" aria-label="Mostrar contraseña" title="Mostrar contraseña">
                                    <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Preguntas de Seguridad -->
                    <div class="register-grid-col">
                        <div class="form-group" style="margin-bottom:0.6rem">
                            <label class="form-label" style="font-weight:700;color:var(--verde-grama);border-bottom:1px solid var(--border-color);padding-bottom:0.25rem">
                                Preguntas de seguridad (Obligatorias)
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="admPreg1">1. ¿Cuál era tu apodo de la infancia?</label>
                            <input type="text" id="admPreg1" name="preg1" class="form-control" placeholder="Tu respuesta" maxlength="20" value="<?= htmlspecialchars($preg1 ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="admPreg2">2. ¿En qué ciudad nació tu madre?</label>
                            <input type="text" id="admPreg2" name="preg2" class="form-control" placeholder="Tu respuesta" maxlength="20" value="<?= htmlspecialchars($preg2 ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="admPreg3">3. ¿Cuál es tu animal favorito?</label>
                            <input type="text" id="admPreg3" name="preg3" class="form-control" placeholder="Tu respuesta" maxlength="20" value="<?= htmlspecialchars($preg3 ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="admPreg4">4. ¿Cuál es el nombre de tu primer colegio?</label>
                            <input type="text" id="admPreg4" name="preg4" class="form-control" placeholder="Tu respuesta" maxlength="20" value="<?= htmlspecialchars($preg4 ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="admPreg5">5. ¿Cuál es el nombre de tu mejor amigo de la infancia?</label>
                            <input type="text" id="admPreg5" name="preg5" class="form-control" placeholder="Tu respuesta" maxlength="20" value="<?= htmlspecialchars($preg5 ?? '') ?>" required>
                        </div>
                    </div>

                    <!-- Botón de Envío -->
                    <div class="register-footer-actions">
                        <button type="submit" class="btn btn-primary btn-block btn-lg" style="max-width:320px">
                            Crear cuenta admin
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div style="padding:0.75rem 1.75rem 1.25rem;text-align:center">
            <a href="#" id="showLogin" style="color:var(--verde-grama);font-size:0.85rem;font-weight:600">
                ← Volver al inicio de sesión
            </a>
        </div>
    </div>

    <!-- ===== CAJA DE RECUPERACIÓN DE CONTRASEÑA ===== -->
    <div class="login-box" id="recoveryBox" style="display: <?= $showRecovery ? 'block' : 'none' ?>">
        <div class="login-logo">
            <svg class="logo-svg" viewBox="0 0 24 24" fill="none" style="background: rgba(255,255,255,0.18);">
                <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <div>
                <h1 style="margin:0;font-size:1.4rem">Recuperar contraseña</h1>
                <p style="margin:0;font-size:0.82rem;opacity:0.8">Restablece tu acceso con preguntas de seguridad</p>
            </div>
        </div>

        <div class="login-form-body">
            <?php
            $recStep = $_SESSION['recovery_step'] ?? 1;

            if ($recStep == 1):
            ?>
                <!-- PASO 1: Pedir Cédula -->
                <form method="POST" action="index.php">
                    <input type="hidden" name="action" value="recovery_step1">

                    <div class="form-group">
                        <label class="form-label" for="recCed">Cédula de identidad</label>
                        <input type="text" id="recCed" name="cedula" class="form-control"
                               placeholder="Ej: 12345678" maxlength="8" required>
                        <p class="form-hint">Buscaremos tu usuario para seleccionar tus preguntas de seguridad.</p>
                    </div>

                    <div class="mt-2">
                        <button type="submit" class="btn btn-danger btn-block btn-lg" style="background: var(--rojo);">
                            Continuar
                        </button>
                    </div>
                </form>
            <?php
            elseif ($recStep == 2):
                $q1_idx = $_SESSION['recovery_q1'];
                $q2_idx = $_SESSION['recovery_q2'];
                $q1_text = $preguntas_seguridad[$q1_idx];
                $q2_text = $preguntas_seguridad[$q2_idx];
            ?>
                <!-- PASO 2: Responder 2 preguntas al azar -->
                <form method="POST" action="index.php">
                    <input type="hidden" name="action" value="recovery_step2">

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label" style="font-weight: 700; color: var(--rojo);">
                            Responde las siguientes preguntas de seguridad:
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="ans1"><?= htmlspecialchars($q1_text) ?></label>
                        <input type="text" id="ans1" name="ans1" class="form-control" placeholder="Tu respuesta" maxlength="20" value="<?= htmlspecialchars($ans1 ?? '') ?>" required autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="ans2"><?= htmlspecialchars($q2_text) ?></label>
                        <input type="text" id="ans2" name="ans2" class="form-control" placeholder="Tu respuesta" maxlength="20" value="<?= htmlspecialchars($ans2 ?? '') ?>" required autocomplete="off">
                    </div>

                    <div class="mt-2">
                        <button type="submit" class="btn btn-danger btn-block btn-lg" style="background: var(--rojo);">
                            Verificar respuestas
                        </button>
                    </div>
                </form>
            <?php
            elseif ($recStep == 3):
            ?>
                <!-- PASO 3: Nueva contraseña -->
                <form method="POST" action="index.php">
                    <input type="hidden" name="action" value="recovery_step3">

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label" style="font-weight: 700; color: var(--verde-grama);">
                            ¡Identidad verificada! Ingresa tu nueva contraseña:
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="newPass">Nueva contraseña</label>
                        <div class="password-wrapper">
                            <input type="password" id="newPass" name="new_password" class="form-control"
                                   placeholder="6-12 caracteres (1 Mayúscula, 1 Número, 1 Especial)" minlength="6" maxlength="12" required>
                            <button type="button" class="password-toggle-btn" data-target="newPass" aria-label="Mostrar contraseña" title="Mostrar contraseña">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirmNewPass">Confirmar nueva contraseña</label>
                        <div class="password-wrapper">
                            <input type="password" id="confirmNewPass" name="confirm_new_password" class="form-control"
                                   placeholder="Repite la contraseña" minlength="6" maxlength="12" required>
                            <button type="button" class="password-toggle-btn" data-target="confirmNewPass" aria-label="Mostrar contraseña" title="Mostrar contraseña">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="mt-2">
                        <button type="submit" class="btn btn-primary btn-block btn-lg">
                            Restablecer contraseña
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <hr class="divider" style="margin-top:1.5rem">
            <p class="text-center" style="font-size:0.85rem">
                <a href="index.php?action=recovery_cancel" style="color:var(--text-muted);font-weight:600">
                    ← Cancelar y volver al login
                </a>
            </p>
        </div>
    </div>

</div><!-- /login-page -->

<script src="assets/js/app.js?v=<?= time() ?>"></script>
<script>
// Alternar entre login, registro y recuperación
document.getElementById('showRegister').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('loginBox').style.display = 'none';
    document.getElementById('recoveryBox').style.display = 'none';
    const regBox = document.getElementById('registerBox');
    regBox.classList.add('register-box-wide');
    regBox.style.display = 'block';
});

document.getElementById('showLogin').addEventListener('click', function(e) {
    e.preventDefault();
    const regBox = document.getElementById('registerBox');
    regBox.style.display = 'none';
    regBox.classList.remove('register-box-wide');
    document.getElementById('recoveryBox').style.display = 'none';
    document.getElementById('loginBox').style.display = 'block';
});

document.getElementById('showRecovery').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('loginBox').style.display = 'none';
    document.getElementById('registerBox').style.display = 'none';
    const recBox = document.getElementById('recoveryBox');
    recBox.style.display = 'block';
});

// Mantener estados de error y cajas correspondientes si ocurren fallas
<?php if ($error && $action === 'register'): ?>
document.getElementById('loginBox').style.display = 'none';
document.getElementById('recoveryBox').style.display = 'none';
const regBox = document.getElementById('registerBox');
regBox.classList.add('register-box-wide');
regBox.style.display = 'block';
<?php endif; ?>
</script>
</body>
</html>
