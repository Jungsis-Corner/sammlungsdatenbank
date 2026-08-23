<?php
session_start();
if (!isset($_SESSION['loggedin'])) exit('no login');
require_once __DIR__ . '/config.php';
$conn = new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME);
if ($conn->connect_error) exit('DB error');
// Einfacher Endpunkt: sende JSON zurück
header('Content-Type: application/json');
echo json_encode(['ok'=>true,'post'=>$_POST]);
exit;
