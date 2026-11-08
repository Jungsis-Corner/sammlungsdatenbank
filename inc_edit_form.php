<?php /* Formular von edit.php (reine Ausgabe). Wird von edit.php eingebunden; erwartet dessen Variablen
   ($data, $id, $inputs, $params, die Navigations-Hrefs, $flashMsg …) und die Funktionen aus inc_edit.php. */ ?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="format-detection" content="telephone=no">
<title><?= $id === 0 ? 'Neuer Eintrag' : 'Bearbeiten: „' . htmlspecialchars(trim((string)($data['Bezeichnung'] ?? '')), ENT_QUOTES, 'UTF-8') . '“' ?> – Sammlung</title>
<script src="/sammlung/assets/theme-toggle.js?v=6"></script>
<script src="/sammlung/assets/typeahead.js?v=4" defer></script>
<script src="/sammlung/assets/edit.js?v=3" defer></script>
<link rel="stylesheet" href="/sammlung/assets/nav.css?v=3">
<link rel="stylesheet" href="/sammlung/assets/edit.css?v=4">
<link rel="stylesheet" href="/sammlung/assets/base.css?v=4">
</head>



<body class="edit-page">
<a class="skip-link" href="#inhalt">Zum Inhalt springen</a>

<header class="appbar">
  <p class="appbar-titel"><a href="index.php"><?= htmlspecialchars(APP_TITLE, ENT_QUOTES, 'UTF-8') ?></a></p>
  <?= nav_appnav(['is_guest' => false, 'is_admin' => true, 'museum' => false, 'export' => true, 'params' => $params]) ?>
  <button type="button" id="themeToggle" class="btn theme-toggle appbar-icon" title="Hell/Dunkel umschalten"></button>
</header>

<main id="inhalt" tabindex="-1">
  <h1><?= $id===0 ? 'Neuer Eintrag' : 'Eintrag bearbeiten' ?></h1>

  <?php if ($flashMsg !== ''): ?>
    <div class="msg-box info" role="status">
      <?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?>
      <?php if ($flashDiag !== ''): ?>
        <div class="msg-diag">Diagnose: <?= htmlspecialchars($flashDiag, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php
    // Navigationslinks: ohne Ziel deaktiviert (kein onclick nötig, edit.js fängt aria-disabled ab)
    $navLink = function (bool $aktiv, string $href, string $titel, string $inhalt): string {
        return '<a class="btn nav' . ($aktiv ? '' : ' disabled') . '" href="' . htmlspecialchars($href) . '"'
             . ($aktiv ? '' : ' aria-disabled="true" tabindex="-1"') . ' title="' . htmlspecialchars($titel) . '">' . $inhalt . '</a>';
    };
  ?>

  <!-- Speichern / Abbrechen (auf dem iPhone fest unten). KEIN data-nocheck: warnt bei ungespeicherten Änderungen -->
  <div class="edit-leiste edit-speichern">
    <a class="btn back" href="<?= htmlspecialchars($backUrl ?: $listUrl) ?>" title="Zurück zur Liste"><?= icon('back') ?> <span class="nur-gross">Zurück</span><span class="nur-klein">Abbrechen</span></a>
    <button type="submit" class="btn save" form="f" name="post_action" value="stay" title="Speichern"><?= icon('save') ?> Speichern</button>
    <button type="submit" class="btn save" form="f" name="post_action" value="next" <?= $next_id ? '' : 'disabled' ?>
            title="Speichern und zum nächsten Datensatz springen"><?= icon('save') ?> <span class="nur-gross">Speichern &amp; Nächster</span><span class="nur-klein">&amp; Weiter</span></button>
  </div>

  <!-- Navigation zwischen den Datensätzen -->
  <div class="edit-leiste edit-nav">
    <?php if ($id > 0): ?>
      <a class="btn view" data-nocheck href="<?= htmlspecialchars($viewUrl) ?>" title="Eintrag ansehen"><?= icon('eye') ?> Anzeigen</a>
    <?php endif; ?>
    <?= $navLink($id !== $first_id, $firstHref, 'Erster Datensatz', icon('back') . ' Erster') ?>
    <?= $navLink((bool)$prev_id, $prevHref, 'Vorheriger Datensatz', icon('back') . ' Vorheriger') ?>
    <?= $navLink((bool)$next_id, $nextHref, 'Nächster Datensatz', 'Nächster ' . icon('forward')) ?>
    <?= $navLink($id !== $last_id, $lastHref, 'Letzter Datensatz', 'Letzter ' . icon('forward')) ?>
  </div>

  <!-- Weitere Aktionen (auf dem iPhone eingeklappt) -->
  <details class="weitere-aktionen" open data-klein-zu>
    <summary>Weitere Aktionen</summary>
    <div>
      <button type="submit" form="f" name="action" value="dup" class="btn dup"
              title="als neuen Eintrag speichern"><?= icon('copy') ?> Als NEU speichern</button>

      <button type="submit" form="f" name="action" value="dup_keepcost" class="btn dupplus"
              title="als neuen Eintrag speichern (mit Datum/Preis/Wert)"><?= icon('copy') ?> NEU (mit Datum/Preis/Wert)</button>

      <a class="btn new" data-nocheck title="Neuer Eintrag ohne Übernahme"
         href="edit.php?<?= htmlspecialchars(http_build_query(array_merge($params, ['id'=>0,'from'=>'new']))) ?>"><?= icon('plus') ?> Neuer Eintrag (leer)</a>

      <?php $igdbQuery = $_GET; unset($igdbQuery['msg']); ?>
      <?php if ($id > 0): ?>
        <form method="post" action="edit.php?<?= htmlspecialchars(http_build_query($igdbQuery)) ?>" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="igdb">
          <button type="submit" class="btn igdb" data-nocheck
                  title="Beschreibung aus IGDB importieren (summary)"><?= icon('search') ?> IGDB holen</button>
        </form>

        <button type="button" class="btn delete" data-loeschen data-nocheck
                title="Diesen Datensatz löschen"><?= icon('trash') ?> Löschen</button>
      <?php endif; ?>
    </div>
  </details>

  <form id="f" method="post">
    <?php
      foreach ($params as $k => $v) {
        if ($v !== '' && $v !== null) {
          echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars((string)$v).'">';
        }
      }
    ?>
    <?= csrf_field() ?>
    <input type="hidden" name="next_id" value="<?= htmlspecialchars((string)($next_id ?? '')) ?>">

    <?php
      // Gruppierung der Felder für die neue Karten-Ansicht
      $fieldGroups = [
        'Bezeichnung' => 'Grunddaten', 'Jahr' => 'Grunddaten', 'Hersteller' => 'Grunddaten',
        'Publisher' => 'Grunddaten', 'Seriennummer' => 'Grunddaten', 'Anzahl' => 'Grunddaten',
        'Kategorie' => 'Grunddaten', 'Original/Homebrew' => 'Grunddaten',

        'Zustand' => 'Zustand & Vollständigkeit', 'Verpackung Status' => 'Zustand & Vollständigkeit',
        'Verpackung' => 'Zustand & Vollständigkeit', 'Datenträger Status' => 'Zustand & Vollständigkeit',
        'Datentraeger' => 'Zustand & Vollständigkeit', 'Anleitung Status' => 'Zustand & Vollständigkeit',
        'Sonstiges' => 'Zustand & Vollständigkeit', 'Sonstiges Beschreibung' => 'Zustand & Vollständigkeit',
        'Material' => 'Zustand & Vollständigkeit',

        'Standort' => 'Standort & Kennung', 'Zum Verkauf' => 'Standort & Kennung', 'VerbautIn' => 'Standort & Kennung', 'ISBN' => 'Standort & Kennung',
        'Barcode' => 'Standort & Kennung', 'Box' => 'Standort & Kennung',

        'Einkaufsdatum' => 'Kauf & Test', 'Getestet am' => 'Kauf & Test', 'Getestet Status' => 'Kauf & Test',
        'Verkäufer' => 'Kauf & Test', 'Einkaufspreis' => 'Kauf & Test', 'Wert' => 'Kauf & Test',

        'Link zum Blog' => 'Links & Notizen', 'Link zu YouTube' => 'Links & Notizen',
        'Beschreibung' => 'Links & Notizen', 'SammlungBild1' => 'Links & Notizen',
      ];
      // Felder, die die volle Kartenbreite bekommen (statt 2-Spalten-Grid)
      $wideFields = ['Bezeichnung','Zustand','Barcode','Sonstiges Beschreibung','Link zum Blog','Link zu YouTube','Beschreibung','SammlungBild1'];
      $currentGroup = null;
    ?>

    <div class="container">
      <!-- Reihenfolge behalten: Felder links, Bild rechts -->
      <div class="fields">
        <div class="form-groups">
          <?php foreach ($inputs as $col => $type):
              $htmlName = str_replace(' ', '_', $col);
              $value    = $data[$col] ?? '';
              $group    = $fieldGroups[$col] ?? 'Weitere Angaben';
              $isWide   = in_array($col, $wideFields, true);
              $fid      = ($col === 'Barcode') ? 'barcodeInput' : 'f_' . $htmlName;   // id für <label for>

              if ($group !== $currentGroup) {
                  if ($currentGroup !== null) echo '</div></details>'; // .form-grid + Gruppe schließen
                  echo '<details class="form-group" open><summary>'.htmlspecialchars($group).'</summary><div class="form-grid">';
                  $currentGroup = $group;
              }
          ?>
            <div class="field<?= $isWide ? ' wide' : '' ?><?= $type === 'checkbox' ? ' checkbox-field' : '' ?>" data-col="<?= htmlspecialchars($htmlName) ?>">
  <label class="label" for="<?= $fid ?>"><?= htmlspecialchars($col === 'VerbautIn' ? 'Gehört zu' : $col) ?></label>

  <?php if ($type === 'textarea'): ?>

    <textarea id="<?= $fid ?>" name="<?= $htmlName ?>" rows="4"><?= htmlspecialchars($value) ?></textarea>

  <?php elseif ($type === 'select'): ?>

    <?php if ($col === 'Datentraeger'): ?>
      <select id="<?= $fid ?>" name="<?= $htmlName ?>">
        <?= get_options('Datentraeger','Datentrager',$value) ?>
      </select>

    <?php elseif ($col === 'Box'): ?>
      <select id="<?= $fid ?>" name="<?= $htmlName ?>">
        <?= get_options('Box','Box',$value) ?>
      </select>

    <?php elseif (in_array($col, ['Kategorie','Hersteller','Publisher','Verkäufer'], true)): ?>
      <?php render_lookup_search($htmlName, $col, $col, $value, $fid); ?>

    <?php elseif ($col === 'Original/Homebrew'): ?>
      <?php $sel = $value ?: 'Original'; ?>
      <select id="<?= $fid ?>" name="<?= $htmlName ?>">
        <option value="Original" <?= ($sel === 'Original' ? 'selected' : '') ?>>Original</option>
        <option value="Homebrew" <?= ($sel === 'Homebrew' ? 'selected' : '') ?>>Homebrew</option>
      </select>

    <?php elseif ($col === 'Getestet Status'): ?>
      <select id="<?= $fid ?>" name="<?= $htmlName ?>">
        <option value=""       <?= ($value === ''       ? 'selected' : '') ?>>— ungetestet —</option>
        <option value="OK"     <?= ($value === 'OK'      ? 'selected' : '') ?>>OK</option>
        <option value="Defekt" <?= ($value === 'Defekt'  ? 'selected' : '') ?>>Defekt</option>
      </select>

    <?php elseif (in_array($col, ['Verpackung Status','Anleitung Status','Datenträger Status'], true)): ?>
      <select id="<?= $fid ?>" name="<?= $htmlName ?>">
        <option value=""          <?= ($value === ''          ? 'selected' : '') ?>>— nicht vorhanden —</option>
        <option value="Original"  <?= ($value === 'Original'   ? 'selected' : '') ?>>Original</option>
        <option value="Nachdruck" <?= ($value === 'Nachdruck'  ? 'selected' : '') ?>>Nachdruck / Repro</option>
      </select>

    <?php elseif ($col === 'VerbautIn'): ?>
      <?php
        $vbLabel = function(string $bez, string $sn, int $vbId): string {
            $l = $bez . ' (#' . $vbId . ')';
            if (trim($sn) !== '') $l .= ' – SN: ' . trim($sn);
            return $l;
        };
        $vbCurrentId    = (int)$value;
        $vbCurrentLabel = '';
        if ($vbCurrentId > 0) {
            $vbCurRow = db_select($conn, "SELECT Bezeichnung, Seriennummer FROM Sammlung WHERE ID = ?", 'i', [$vbCurrentId])->fetch_assoc();
            if ($vbCurRow) $vbCurrentLabel = $vbLabel($vbCurRow['Bezeichnung'], (string)($vbCurRow['Seriennummer'] ?? ''), $vbCurrentId);
        }
      ?>
      <input type="text"
             id="<?= $fid ?>"
             class="ta-input"
             autocomplete="off"
             placeholder="Tippen zum Suchen…"
             value="<?= htmlspecialchars($vbCurrentLabel) ?>"
             data-ta="sammlung" data-ta-exclude="<?= (int)$id ?>" data-ta-hidden="<?= $htmlName ?>">
      <input type="hidden" name="<?= $htmlName ?>" id="<?= $htmlName ?>" value="<?= $vbCurrentId ?>">

    <?php else: ?>
      <select id="<?= $fid ?>" name="<?= $htmlName ?>">
        <?= get_options($col, $col, $value) ?>
      </select>
    <?php endif; ?>

  <?php elseif ($type === 'checkbox'): ?>

    <input type="checkbox" id="<?= $fid ?>" name="<?= $htmlName ?>" value="1" <?= $value ? 'checked' : '' ?>>

  <?php elseif ($type === 'number'): ?>

    <input type="number" id="<?= $fid ?>" name="<?= $htmlName ?>" value="<?= htmlspecialchars($value) ?>" inputmode="numeric">

  <?php elseif ($col === 'Barcode'): ?>

    <div class="barcode-field">
      <input
        type="text"
        name="<?= $htmlName ?>"
        id="barcodeInput"
        value="<?= htmlspecialchars($value) ?>"
        inputmode="numeric"
        autocomplete="off"
        placeholder="Barcode / EAN scannen oder eingeben"
      >

      <div class="barcode-actions">
        <button type="button" class="btn secondary" id="startScanBtn"><?= icon('camera') ?> Scannen</button>
        <button type="button" class="btn secondary" id="stopScanBtn" hidden><?= icon('close') ?> Scan beenden</button>
        <button type="button" class="btn secondary" id="torchBtn" hidden><?= icon('flash') ?><span id="torchLabel">Licht an</span></button>
      </div>

      <div id="barcodeScanWrap" class="barcode-scan-wrap" hidden>
        <video id="barcodeVideo" autoplay playsinline muted></video>
        <div class="barcode-hint">Barcode mittig vor die Kamera halten.</div>
        <button type="button" class="btn secondary" id="captureBtn"><?= icon('camera') ?> Foto aufnehmen &amp; auswerten</button>
      </div>
      <canvas id="barcodeCanvas" hidden></canvas>

      <div id="barcodeStatus" class="barcode-status" aria-live="polite"></div>
    </div>

  <?php elseif ($col === 'Einkaufsdatum'): ?>

    <?php
      $ekdRaw = trim((string)$value);
      $ekdDisplay = '';
      if ($ekdRaw !== '' && $ekdRaw !== '0000-00-00') {
          $ekdTs = strtotime($ekdRaw);
          $ekdDisplay = $ekdTs ? date('d.m.Y', $ekdTs) : $ekdRaw;
      }
    ?>
    <input type="text"
           id="<?= $fid ?>"
           name="<?= $htmlName ?>"
           value="<?= htmlspecialchars($ekdDisplay) ?>"
           inputmode="numeric"
           autocomplete="off"
           placeholder="TT.MM.JJJJ"
           pattern="\d{1,2}\.\d{1,2}\.\d{4}"
           title="Datum als TT.MM.JJJJ">

  <?php elseif ($col === 'SammlungBild1'): ?>

    <?php $bildUpload = image_upload_available(); $bildAuswahl = image_local_mode(); ?>
    <div class="bild-feld" id="bildFeld"<?php if ($bildUpload): ?>
         data-upload="bild_upload.php" data-csrf="<?= htmlspecialchars((string)($_SESSION['csrf'] ?? '')) ?>"
         data-max-mb="<?= (int)floor(image_upload_limit() / 1048576) ?>" data-max-edge="<?= (int)IMAGE_UPLOAD_MAX_EDGE ?>"<?php endif; ?>
         <?php if ($bildAuswahl): ?> data-medien="api_medien.php"<?php endif; ?>>
      <input type="text" id="<?= $fid ?>" name="<?= $htmlName ?>" value="<?= htmlspecialchars((string)$value) ?>" autocomplete="off"
             placeholder="<?= $bildAuswahl ? 'Dateiname in medien/, hochladen oder aus vorhandenen Bildern wählen' : 'Dateiname oder Pfad unter wp-content/uploads, z. B. 2014/11/bild.jpg (volle URL geht auch)' ?>">
      <?php if ($bildUpload || $bildAuswahl): ?>
        <div class="bild-knoepfe">
          <?php if ($bildUpload): ?>
            <label class="btn secondary bild-upload-label" for="bildDatei"><?= icon('camera') ?> Bild hochladen</label>
            <input type="file" id="bildDatei" class="sr-only" accept="image/jpeg,image/png,image/webp,image/gif">
          <?php endif; ?>
          <?php if ($bildAuswahl): ?>
            <button type="button" class="btn secondary" id="bildWaehlen"><?= icon('search') ?> Aus vorhandenen wählen</button>
          <?php endif; ?>
          <button type="button" class="btn secondary" id="bildLeeren" hidden>Bild entfernen</button>
        </div>
        <p class="bild-status" id="bildStatus" role="status" aria-live="polite"></p>
        <img class="bild-vorschau" id="bildVorschau" alt="Vorschau des gewählten Bildes" hidden>
      <?php endif; ?>
    </div>

  <?php elseif ($col === 'Einkaufspreis' || $col === 'Wert'): ?>

    <?php $geldVal = parse_decimal($value); $geldVal = ($geldVal !== null) ? fmt_dec($geldVal) : (string)$value; ?>
    <input type="text" id="<?= $fid ?>" name="<?= $htmlName ?>" value="<?= htmlspecialchars($geldVal) ?>" inputmode="decimal" autocomplete="off" placeholder="z. B. 12,50">

  <?php else: ?>

    <input type="<?= $type ?>" id="<?= $fid ?>" name="<?= $htmlName ?>" value="<?= htmlspecialchars($value) ?>">

  <?php endif; ?>
</div>
<?php endforeach; ?>
          </div></details><!-- .form-grid + letzte reguläre Gruppe schließen -->

          <?php
            $erstellt  = !empty($data['Erstellt_am'])  ? date('d.m.Y H:i:s', strtotime($data['Erstellt_am']))   : '';
            $geaendert = !empty($data['Geaendert_am']) ? date('d.m.Y H:i:s', strtotime($data['Geaendert_am'])) : '';
          ?>
          <details class="form-group" open>
            <summary>Verwaltung</summary>
            <div class="form-grid">
              <div class="field readonly">
                <label class="label" for="f_erstellt">Erstellt am</label>
                <input type="text" id="f_erstellt" value="<?= htmlspecialchars($erstellt) ?>" readonly tabindex="-1">
              </div>
              <div class="field readonly">
                <label class="label" for="f_geaendert">Geändert am</label>
                <input type="text" id="f_geaendert" value="<?= htmlspecialchars($geaendert) ?>" readonly tabindex="-1">
              </div>
            </div>
          </details>
        </div>
      </div>

      <?php if (image_parse($data['SammlungBild1'] ?? '')): ?>
        <div class="image"><?= image_img($data['SammlungBild1'] ?? '', (string)($data['Bezeichnung'] ?? ''), ['widths' => [320, 640], 'sizes' => '(max-width: 767px) 100vw, 480px']) ?></div>
      <?php endif; ?>
    </div><!-- .container -->
  </form>

<?php if ($id > 0): ?>
<?php
  // Filterkontext für den Rücksprung nach index.php mitgeben
  $deleteParams = $params;
  foreach (['filter'=>$filter, 'kat'=>($kat > 0 ? (string)$kat : ''), 'inkl'=>($inkl ? '1' : ''), 'oh'=>$oh, 'material'=>$material, 'q'=>$q] as $k=>$v) {
      if ($v !== '') $deleteParams[$k] = $v;
  }
  $deleteParams['id'] = $id;
?>
<?php if (image_local_mode()): ?>
<!-- Auswahl vorhandener Bilder aus medien/ (Liste aus api_medien.php) -->
<dialog id="medienDialog" class="medien-dialog" aria-labelledby="medienTitel">
  <h2 id="medienTitel">Bild wählen</h2>
  <label class="label sr-only" for="medienSuche">Suche im Dateinamen</label>
  <input type="search" id="medienSuche" placeholder="Dateiname suchen" autocomplete="off">
  <ul class="medien-raster" id="medienRaster"></ul>
  <p class="bild-status" id="medienStatus" role="status" aria-live="polite"></p>
  <div class="loesch-knoepfe">
    <button type="button" class="btn secondary" id="medienMehr" hidden>Mehr laden</button>
    <button type="button" class="btn" id="medienZu">Schließen</button>
  </div>
</dialog>
<?php endif; ?>
<!-- Löschen bestätigen (POST mit CSRF an delete.php) -->
<dialog id="loeschDialog" class="loesch-dialog" aria-labelledby="loeschTitel">
  <form method="post" action="delete.php">
    <?= csrf_field() ?>
    <?php foreach ($deleteParams as $dk => $dv): ?>
      <input type="hidden" name="<?= htmlspecialchars((string)$dk) ?>" value="<?= htmlspecialchars((string)$dv) ?>">
    <?php endforeach; ?>
    <h2 id="loeschTitel">Eintrag löschen?</h2>
    <p>„<?= htmlspecialchars((string)($data['Bezeichnung'] ?? ''), ENT_QUOTES, 'UTF-8') ?>“ wird unwiderruflich gelöscht.</p>
    <div class="loesch-knoepfe">
      <button type="button" class="btn" data-loeschen-zu>Abbrechen</button>
      <button type="submit" class="btn delete" data-nocheck><?= icon('trash') ?> Löschen</button>
    </div>
  </form>
</dialog>
<?php endif; ?>
</main>
</body>
</html>