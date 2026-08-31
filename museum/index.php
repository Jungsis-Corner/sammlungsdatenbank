<?php
session_start();

// Option B (nur wenn für dich ok):
// Gast-Session setzen, falls noch niemand eingeloggt ist
if (empty($_SESSION['user'])) {
  $_SESSION['user'] = 'gast';
  $_SESSION['role'] = 'guest';

  // Gast-Login protokollieren (nur bei echtem Neu-Login, nicht bei jedem Aufruf)
  require_once __DIR__ . '/../config.php';
  try {
      $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
      if ($conn && !$conn->connect_errno) {
          if ($stmt = $conn->prepare("INSERT INTO Login_Log (Quelle) VALUES ('museum_link')")) {
              $stmt->execute();
              $stmt->close();
          }
          $conn->close();
      }
  } catch (\Throwable $e) {
      // bewusst ignorieren - darf den Museumszugang nie blockieren
  }
}

header('Location: /sammlung/index.php');
exit;