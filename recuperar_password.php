<?php
/**
 * recuperar_password.php — Módulo de Recuperación de Contraseña
 * =========================================================
 * Permite a un usuario restablecer su contraseña en 3 pasos:
 *   Paso 1: Solicita la Cédula y valida que exista.
 *   Paso 2: Presenta 2 preguntas de seguridad al azar de las 5 configuradas.
 *   Paso 3: Si las respuestas coinciden, permite registrar una nueva contraseña.
 * =========================================================
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

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

// Inicializar el paso de recuperación si no está definido
if (!isset($_SESSION['recovery_step'])) {
    $_SESSION['recovery_step'] = 1;
}

$step   = $_SESSION['recovery_step'];
$cedula = $_SESSION['recovery_cedula'] ?? '';

// Mapeo de preguntas de seguridad y sus columnas correspondientes
$questions_map = [
    'apodo_infancia' => [
        'label' => '¿Cuál es tu apodo de infancia?',
        'col' => 'ApodoInfancia'
    ],
    'ciudad_natal' => [
        'label' => '¿Cuál es tu ciudad natal?',
        'col' => 'CiudadNatal'
    ],
    'escuela_primaria' => [
        'label' => '¿Cuál es el nombre de tu escuela primaria?',
        'col' => 'EscuelaPrimaria'
    ],
    'madre_nombre' => [
        'label' => '¿Cuál es el segundo nombre de tu madre?',
        'col' => 'MadreNombre'
    ],
    'comida_favorita' => [
        'label' => '¿Cuál es tu comida favorita?',
        'col' => 'ComidaFavorita'
    ]
];

$action = $_POST['action'] ?? '';

// Reiniciar flujo de recuperación
if (isset($_GET['restart'])) {
    unset($_SESSION['recovery_step']);
    unset($_SESSION['recovery_cedula']);
    unset($_SESSION['recovery_questions']);
    header('Location: recuperar_password.php');
    exit;
}

/* =========================================================
   PROCESAR FORMULARIOS DE CADA PASO
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($action === 'step1') {
        // PASO 1: Ingreso e Identificación de Cédula
        $input_cedula = trim($_POST['cedula'] ?? '');

        if (empty($input_cedula)) {
            $error = 'Por favor ingresa tu cédula de identidad.';
        } elseif (!preg_match('/^\d{1,8}$/', $input_cedula)) {
            $error = 'La cédula debe contener solo números (máximo 8 dígitos).';
        } else {
            // Verificar si el usuario existe y tiene preguntas registradas
            $stmt = $conn->prepare(
                "SELECT ps.Cedula 
                 FROM PreguntasSeguridad ps
                 INNER JOIN Usuarios u ON ps.Cedula = u.Cedula
                 WHERE ps.Cedula = ? LIMIT 1"
            );
            $stmt->bind_param('s', $input_cedula);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows === 0) {
                $error = 'La cédula ingresada no está registrada o no posee preguntas de seguridad configuradas.';
            } else {
                $_SESSION['recovery_cedula'] = $input_cedula;
                $_SESSION['recovery_step']   = 2;

                // Seleccionar 2 preguntas al azar
                $keys = array_keys($questions_map);
                shuffle($keys);
                $_SESSION['recovery_questions'] = array_slice($keys, 0, 2);

                header('Location: recuperar_password.php');
                exit;
            }
            $stmt->close();
        }
    }

    elseif ($action === 'step2') {
        // PASO 2: Validar Respuestas de Seguridad
        if ($step !== 2 || empty($cedula) || empty($_SESSION['recovery_questions'])) {
            $error = 'Sesión de recuperación inválida. Reiniciando proceso.';
            $_SESSION['recovery_step'] = 1;
            header('Location: recuperar_password.php');
            exit;
        }

        $q_keys = $_SESSION['recovery_questions'];
        $resp1  = trim($_POST['respuesta_' . $q_keys[0]] ?? '');
        $resp2  = trim($_POST['respuesta_' . $q_keys[1]] ?? '');

        if (empty($resp1) || empty($resp2)) {
            $error = 'Por favor responde ambas preguntas de seguridad.';
        } else {
            // Consultar las respuestas almacenadas
            $stmt = $conn->prepare("SELECT ApodoInfancia, CiudadNatal, EscuelaPrimaria, MadreNombre, ComidaFavorita FROM PreguntasSeguridad WHERE Cedula = ? LIMIT 1");
            $stmt->bind_param('s', $cedula);
            $stmt->execute();
            $db_answers = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$db_answers) {
                $error = 'Error de consulta en el sistema. Intenta de nuevo.';
                $_SESSION['recovery_step'] = 1;
            } else {
                $col1 = $questions_map[$q_keys[0]]['col'];
                $col2 = $questions_map[$q_keys[1]]['col'];

                $db_ans1 = $db_answers[$col1];
                $db_ans2 = $db_answers[$col2];

                $user_ans1 = mb_strtolower($resp1, 'UTF-8');
                $user_ans2 = mb_strtolower($resp2, 'UTF-8');

                if ($user_ans1 === $db_ans1 && $user_ans2 === $db_ans2) {
                    $_SESSION['recovery_step'] = 3;
                    header('Location: recuperar_password.php');
                    exit;
                } else {
                    $error = 'Una o ambas respuestas de seguridad son incorrectas. Inténtalo de nuevo.';
                    // Volver a sortear preguntas por seguridad para evitar ataques de adivinación
                    $keys = array_keys($questions_map);
                    shuffle($keys);
                    $_SESSION['recovery_questions'] = array_slice($keys, 0, 2);
                }
            }
        }
    }

    elseif ($action === 'step3') {
        // PASO 3: Restablecer Contraseña
        if ($step !== 3 || empty($cedula)) {
            $error = 'Sesión de recuperación inválida. Reiniciando proceso.';
            $_SESSION['recovery_step'] = 1;
            header('Location: recuperar_password.php');
            exit;
        }

        $password = trim($_POST['password'] ?? '');
        $confirm  = trim($_POST['confirm'] ?? '');

        if (empty($password) || empty($confirm)) {
            $error = 'Por favor completa todos los campos.';
        } elseif (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif ($password !== $confirm) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            // Actualizar en base de datos
            $hash = hash('sha256', $password);
            $stmt = $conn->prepare("UPDATE Usuarios SET Contrasena = ? WHERE Cedula = ?");
            $stmt->bind_param('ss', $hash, $cedula);

            if ($stmt->execute()) {
                // Éxito: limpiar variables de sesión y redirigir
                unset($_SESSION['recovery_step']);
                unset($_SESSION['recovery_cedula']);
                unset($_SESSION['recovery_questions']);
                header('Location: index.php?msg=recovery_success');
                exit;
            } else {
                $error = 'Ocurrió un error al actualizar la contraseña en el servidor. Inténtalo de nuevo.';
            }
            $stmt->close();
        }
    }
}

// Sincronizar el paso final
$step = $_SESSION['recovery_step'];
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de gestión de tickets — Recuperar contraseña institucional">
    <title>Recuperar Contraseña — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .recovery-page {
            min-height: 100vh;
            background: var(--bg-page);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            flex-direction: column;
            gap: 2rem;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            background: var(--bg-card-alt);
            padding: 0.75rem 1.5rem;
            border-bottom: 2px solid var(--border-color);
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-secondary);
        }
        .step-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--border-color);
            display: inline-block;
            margin-left: 5px;
            transition: background var(--transition);
        }
        .step-dot.active {
            background: var(--verde-grama);
        }
    </style>
</head>
<body class="login-page-body">

<!-- Botón de tema flotante -->
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

<div class="recovery-page">

    <div class="login-box">
        <!-- Encabezado con Logo -->
        <div class="login-logo">
            <svg class="logo-svg" viewBox="0 0 24 24" fill="none">
                <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <h1>Recuperar Contraseña</h1>
            <p>Verificación de identidad e ingreso seguro</p>
        </div>

        <!-- Indicador de Pasos -->
        <div class="step-indicator">
            <span>Paso <?= $step ?> de 3</span>
            <div>
                <span class="step-dot active"></span>
                <span class="step-dot <?= $step >= 2 ? 'active' : '' ?>"></span>
                <span class="step-dot <?= $step >= 3 ? 'active' : '' ?>"></span>
            </div>
        </div>

        <!-- Alertas de Error/Éxito -->
        <?php if ($error): ?>
            <div class="alert alert-danger" style="margin: 1rem 1.75rem 0; border-radius: 8px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="login-form-body">
            
            <!-- ==========================================
                 PASO 1: INGRESAR CÉDULA
                 ========================================== -->
            <?php if ($step === 1): ?>
                <form method="POST" action="recuperar_password.php">
                    <input type="hidden" name="action" value="step1">

                    <p class="text-muted mb-2" style="font-size: 0.85rem; line-height: 1.4;">
                        Ingresa tu número de cédula para iniciar el proceso de verificación de identidad.
                    </p>

                    <div class="form-group">
                        <label class="form-label" for="recCedula">Cédula de identidad</label>
                        <input type="text" id="recCedula" name="cedula" class="form-control"
                               placeholder="Ej: 12345678" maxlength="8" required autofocus>
                    </div>

                    <div class="mt-2">
                        <button type="submit" class="btn btn-primary btn-block btn-lg">
                            Continuar
                        </button>
                    </div>
                </form>

            <!-- ==========================================
                 PASO 2: PREGUNTAS DE SEGURIDAD
                 ========================================== -->
            <?php elseif ($step === 2): ?>
                <form method="POST" action="recuperar_password.php">
                    <input type="hidden" name="action" value="step2">

                    <p class="text-muted mb-2" style="font-size: 0.85rem; line-height: 1.4;">
                        Para comprobar tu identidad, responde las siguientes preguntas de seguridad de forma exacta a como las configuraste al registrarte.
                    </p>

                    <div style="background: var(--bg-card-alt); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.5rem 1rem; margin-bottom: 1rem; font-size: 0.82rem; font-weight: 600; color: var(--text-secondary);">
                        Cédula identificada: <?= htmlspecialchars($cedula) ?>
                    </div>

                    <?php 
                    $q_keys = $_SESSION['recovery_questions'] ?? [];
                    foreach ($q_keys as $index => $q_key): 
                        $question = $questions_map[$q_key];
                    ?>
                        <div class="form-group">
                            <label class="form-label" for="resp_<?= $q_key ?>">
                                <strong>Pregunta <?= $index + 1 ?>:</strong> <?= $question['label'] ?>
                            </label>
                            <input type="text" id="resp_<?= $q_key ?>" name="respuesta_<?= $q_key ?>" class="form-control"
                                   placeholder="Ingresa tu respuesta..." required <?= $index === 0 ? 'autofocus' : '' ?>>
                        </div>
                    <?php endforeach; ?>

                    <div class="mt-2" style="display: flex; gap: 0.5rem;">
                        <a href="recuperar_password.php?restart=1" class="btn btn-secondary" style="flex: 1; text-align: center; justify-content: center;">
                            Atrás
                        </a>
                        <button type="submit" class="btn btn-primary" style="flex: 2; justify-content: center;">
                            Verificar respuestas
                        </button>
                    </div>
                </form>

            <!-- ==========================================
                 PASO 3: RESTABLECER CONTRASEÑA
                 ========================================== -->
            <?php elseif ($step === 3): ?>
                <form method="POST" action="recuperar_password.php">
                    <input type="hidden" name="action" value="step3">

                    <p class="text-muted mb-2" style="font-size: 0.85rem; line-height: 1.4;">
                        ¡Identidad confirmada con éxito! Ingresa una nueva contraseña segura para acceder a tu cuenta.
                    </p>

                    <div class="form-group">
                        <label class="form-label" for="newPass">Nueva contraseña</label>
                        <input type="password" id="newPass" name="password" class="form-control"
                               placeholder="Mínimo 6 caracteres" minlength="6" required autofocus>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="newConfirm">Confirmar nueva contraseña</label>
                        <input type="password" id="newConfirm" name="confirm" class="form-control"
                               placeholder="Repite la contraseña" required>
                    </div>

                    <div class="mt-2">
                        <button type="submit" class="btn btn-primary btn-block btn-lg">
                            Restablecer contraseña
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <hr class="divider" style="margin-top: 1.5rem;">
            <p class="text-center" style="font-size: 0.85rem;">
                <a href="index.php" style="color: var(--verde-grama); font-weight: 600;">
                    ← Cancelar y volver al inicio
                </a>
            </p>
        </div>
    </div>

</div><!-- /recovery-page -->

<script src="assets/js/app.js"></script>
</body>
</html>
