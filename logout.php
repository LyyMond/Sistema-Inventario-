<?php
/**
 * logout.php
 * Destruye la sesión del usuario y redirige al login.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
session_unset();
session_destroy();
header('Location: index.php?msg=logout');
exit;
