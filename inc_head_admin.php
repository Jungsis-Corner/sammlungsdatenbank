<meta charset="utf-8">
<title><?= htmlspecialchars($page_title ?? 'Admin') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="/sammlung/assets/theme-toggle.js"></script>
<link rel="stylesheet" href="/sammlung/assets/admin.css?v=12">
<?php include __DIR__ . '/inc_pwa_head.php'; ?>
<?php if (function_exists('is_museum_mode') && is_museum_mode()): ?>
  <div class="museum-banner">
    <strong>Museum-Modus</strong> – Besucheransicht (Read-only)
    <a class="museum-exit" href="/sammlung/museum/exit.php">Beenden</a>
  </div>
<?php endif; ?>
