<?php
session_start();
$_SESSION['rol'] = 'Administrador';
$_SESSION['nombre'] = 'Admin';
$_SESSION['cedula'] = '00000003';
$_SESSION['rol'] = 'Operador';
require 'operador/dashboard.php';
