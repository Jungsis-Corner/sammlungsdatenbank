<?php
// manifest.php – dynamisches PWA-Manifest, Werte kommen zentral aus config.php
require_once __DIR__ . '/config.php';
header('Content-Type: application/manifest+json');

echo json_encode([
  "name" => APP_PWA_NAME,
  "short_name" => APP_PWA_SHORT_NAME,
  "start_url" => "/sammlung/index.php",
  "scope" => "/sammlung/",
  "display" => "standalone",
  "background_color" => "#ffffff",
  "theme_color" => "#111111",
  "description" => "Sammlungsverwaltung und Anzeige deiner Retro-Hardware und Spiele.",
  "icons" => [
    [
      "src" => "/sammlung/icons/icon-192.png",
      "sizes" => "192x192",
      "type" => "image/png"
    ],
    [
      "src" => "/sammlung/icons/icon-512.png",
      "sizes" => "512x512",
      "type" => "image/png"
    ]
  ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
