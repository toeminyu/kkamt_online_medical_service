<?php
require_once "session.php";
require_once "db.php";
require_once "helpers.php";
require_login();

if (($_SESSION['role'] ?? '') !== 'doctor') {
  redirect_role($_SESSION['role'] ?? '');
}

/* ---------- Current doctor ---------- */
$dStmt = $pdo->prepare("SELECT doctor_ID, doctor_name FROM doctor WHERE user_id = ? LIMIT 1");
$dStmt->execute([$_SESSION['user_id']]);
$doc = $dStmt->fetch();
$doctor_id   = (int)($doc['doctor_ID'] ?? 0);
$displayName = $doc['doctor_name'] ?? ($_SESSION['email'] ?? 'Doctor');
if (!$doctor_id) { header("Location: login.php"); exit; }

/* ---------- Helpers ---------- */
function column_exists(PDO $pdo, $table, $col){
  $chk = $pdo->prepare("
    SELECT COUNT(*) c
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = ?
      AND COLUMN_NAME = ?
  ");
  $chk->execute([$table, $col]);
  return $chk->fetchColumn() > 0;
}

$hasDiagnosis    = column_exists($pdo, 'prescription', 'diagnosis');
$hasInstructions = column_exists($pdo, 'prescription', 'instructions');
$hasUpdatedAt    = column_exists($pdo, 'prescription', 'updated_at');

/* ---------- Routing: editor vs dashboard ---------- */
$appointment_id = (int)($_GET['appointment_id'] ?? $_POST['appointment_id'] ?? 0);

/* ---------------------- EDITOR MODE ---------------------- */
if ($appointment_id > 0) {
  /* Load appointment + ensure it belongs to this doctor */
  $aStmt = $pdo->prepare("
    SELECT a.appointment_id, a.status AS appt_status, a.patient_id, a.service_id, a.preferred_language,
           s.start_dt, s.end_dt, s.doctor_id,
           p.patient_name,
           sv.service_name
    FROM appointment a
    JOIN schedule_slot s ON s.slot_id = a.slot_id
    JOIN patient p       ON p.patient_ID = a.patient_id
    LEFT JOIN service sv ON sv.service_ID = a.service_id
    WHERE a.appointment_id = ?
    LIMIT 1
  ");
  $aStmt->execute([$appointment_id]);
  $appt = $aStmt->fetch();

  if (!$appt) {
    $_SESSION['flash_error'] = "Appointment not found.";
    header("Location: prescription_create.php"); exit;
  }
  if ((int)$appt['doctor_id'] !== $doctor_id) {
    $_SESSION['flash_error'] = "This appointment is not assigned to you.";
    header("Location: prescription_create.php"); exit;
  }

  /* Ensure a prescription row exists for this appointment */
  $rxStmt = $pdo->prepare("SELECT * FROM prescription WHERE appointment_id = ? LIMIT 1");
  $rxStmt->execute([$appointment_id]);
  $rx = $rxStmt->fetch();
  if (!$rx) {
    $insRx = $pdo->prepare("
      INSERT INTO prescription (appointment_id, patient_ID, doctor_ID)
      VALUES (?, ?, ?)
    ");
    $insRx->execute([$appointment_id, (int)$appt['patient_id'], $doctor_id]);
    $rx_id = (int)$pdo->lastInsertId();
    $rxStmt->execute([$appointment_id]);
    $rx = $rxStmt->fetch();
  } else {
    $rx_id = (int)$rx['prescription_ID'];
  }

  /* Load items */
  $itemStmt = $pdo->prepare("
    SELECT item_id, drug_name, dosage, frequency, duration, notes
    FROM prescription_items
    WHERE prescription_id = ?
    ORDER BY item_id
  ");
  $itemStmt->execute([$rx_id]);
  $items = $itemStmt->fetchAll();

  /* Save */
  $err = "";
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $diagnosis    = trim($_POST['diagnosis'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');

    $drug_name = $_POST['drug_name'] ?? [];
    $dosage    = $_POST['dosage'] ?? [];
    $frequency = $_POST['frequency'] ?? [];
    $duration  = $_POST['duration'] ?? [];
    $notes     = $_POST['item_notes'] ?? [];

    $newItems = [];
    $count = max(count($drug_name), count($dosage), count($frequency), count($duration), count($notes));
    for ($i=0; $i<$count; $i++) {
      $dn = trim($drug_name[$i] ?? '');
      $dg = trim($dosage[$i] ?? '');
      $fq = trim($frequency[$i] ?? '');
      $du = trim($duration[$i] ?? '');
      $nt = trim($notes[$i] ?? '');
      if ($dn === '' && $dg === '' && $fq === '' && $du === '' && $nt === '') continue;
      $newItems[] = [$dn, $dg, $fq, $du, $nt];
    }

    try {
      $pdo->beginTransaction();

      // Update optional columns if present
      $sets = [];
      $params = [];
      if ($hasDiagnosis)    { $sets[] = "diagnosis=?";    $params[] = $diagnosis; }
      if ($hasInstructions) { $sets[] = "instructions=?"; $params[] = $instructions; }
      if ($hasUpdatedAt)    { $sets[] = "updated_at=NOW()"; }

      if ($sets) {
        $sql = "UPDATE prescription SET ".implode(", ", $sets)." WHERE prescription_ID=? AND doctor_ID=?";
        $params[] = $rx_id;
        $params[] = $doctor_id;
        $u = $pdo->prepare($sql);
        $u->execute($params);
      }

      // Replace items
      $pdo->prepare("DELETE FROM prescription_items WHERE prescription_id=?")->execute([$rx_id]);
      if (!empty($newItems)) {
        $insItem = $pdo->prepare("
          INSERT INTO prescription_items (prescription_id, drug_name, dosage, frequency, duration, notes)
          VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($newItems as $it) {
          $insItem->execute([$rx_id, $it[0], $it[1], $it[2], $it[3], $it[4]]);
        }
      }

      $pdo->commit();
      $_SESSION['flash_success'] = "✅ Prescription saved.";
      header("Location: prescription_create.php?appointment_id=".$appointment_id);
      exit;
    } catch (Exception $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $err = $e->getMessage();
    }
  }
  ?>
  <!doctype html>
  <html>
  <head>
    <?php head_tag("Prescription Editor"); ?>
    <link rel="stylesheet" href="styles.css">
    <style>
      html, body { margin:0; }
      body { padding-top:0 !important; background:#f7f7fb; }
      .wrapper { max-width: 1100px; margin: 0 auto; padding: 16px 20px; }
      .panel { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; }
      .row { display:flex; gap:16px; align-items:center; flex-wrap:wrap; margin-bottom:10px; }
      .muted { color:#6b7280; }
      .input, textarea { width:100%; padding:10px 12px; border:1px solid #e5e7eb; border-radius:10px; background:#fff; }
      textarea { min-height:110px; }
      .btn { background:#111827; color:#fff; border-radius:10px; padding:10px 14px; border:0; cursor:pointer; text-decoration:none; display:inline-block; }
      .btn.secondary { background:#4b5563; }
      .btn.danger { background:#991b1b; }
      .grid-2 { display:grid; grid-template-columns: 1fr 1fr; gap:14px; }
      .items-table { width:100%; border-collapse: collapse; margin-top:10px; }
      .items-table th, .items-table td { border:1px solid #e5e7eb; padding:8px; font-size:14px; }
      .items-table th { background:#f9fafb; text-align:left; }
      .small { font-size:12px; color:#6b7280; }
      .actions { display:flex; gap:10px; margin-top:12px; }
      .flash { background:#ecfdf5; border:1px solid #10b981; color:#065f46; padding:10px 14px; border-radius:10px; margin-bottom:12px; }
      .err { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:10px 12px; border-radius:10px; margin-bottom:12px; }
      .add-row { margin-top:8px; }
    </style>
  </head>
  <body>
    <?php include "nav.php"; ?>

    <div class="wrapper">
      <div class="panel">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
          <h2 style="margin:0;">Write Prescription</h2>
          <a class="btn secondary" href="prescription_create.php">Back to dashboard</a>
        </div>

        <div class="row muted">
          <div><strong>Patient:</strong> <?= e($appt['patient_name']) ?> (ID #<?= (int)$appt['patient_id'] ?>)</div>
          <div>•</div>
          <div><strong>Service:</strong> <?= e($appt['service_name'] ?: 'Appointment') ?></div>
          <div>•</div>
          <div><strong>Preferred language:</strong> <?= e($appt['preferred_language'] ?: '-') ?></div>
          <div>•</div>
          <div><strong>Time:</strong> <?= e(date('Y-m-d H:i', strtotime($appt['start_dt']))) ?> – <?= e(date('H:i', strtotime($appt['end_dt']))) ?></div>
        </div>

        <?php if (!empty($_SESSION['flash_success'])): ?>
          <div class="flash"><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_error'])): ?>
          <div class="err"><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
        <?php endif; ?>
        <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>

        <form method="post" id="rxForm">
          <input type="hidden" name="appointment_id" value="<?= (int)$appointment_id ?>">

          <div class="grid-2">
            <div>
              <label><strong>Diagnosis</strong></label>
              <textarea name="diagnosis" class="input" placeholder="e.g., Acute tonsillitis"><?= e($rx['diagnosis'] ?? '') ?></textarea>
            </div>
            <div>
              <label><strong>General Instructions / Advice</strong></label>
              <textarea name="instructions" class="input" placeholder="Rest, fluids, follow-up advice"><?= e($rx['instructions'] ?? '') ?></textarea>
            </div>
          </div>

          <h3 style="margin-top:16px;">Medications</h3>
          <table class="items-table" id="itemsTable">
            <thead>
              <tr>
                <th style="width:25%;">Drug name</th>
                <th style="width:18%;">Dosage</th>
                <th style="width:18%;">Frequency</th>
                <th style="width:14%;">Duration</th>
                <th>Notes</th>
                <th style="width:60px;">&nbsp;</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$items): ?>
                <tr>
                  <td><input class="input" name="drug_name[]"    placeholder="e.g., Paracetamol"></td>
                  <td><input class="input" name="dosage[]"       placeholder="500mg"></td>
                  <td><input class="input" name="frequency[]"    placeholder="q6h PRN"></td>
                  <td><input class="input" name="duration[]"     placeholder="3 days"></td>
                  <td><input class="input" name="item_notes[]"   placeholder="for pain/fever"></td>
                  <td><button type="button" class="btn danger" onclick="removeRow(this)">✕</button></td>
                </tr>
              <?php else: foreach ($items as $it): ?>
                <tr>
                  <td><input class="input" name="drug_name[]"    value="<?= e($it['drug_name']) ?>"></td>
                  <td><input class="input" name="dosage[]"       value="<?= e($it['dosage']) ?>"></td>
                  <td><input class="input" name="frequency[]"    value="<?= e($it['frequency']) ?>"></td>
                  <td><input class="input" name="duration[]"     value="<?= e($it['duration']) ?>"></td>
                  <td><input class="input" name="item_notes[]"   value="<?= e($it['notes']) ?>"></td>
                  <td><button type="button" class="btn danger" onclick="removeRow(this)">✕</button></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>

          <div class="add-row">
            <button type="button" class="btn" onclick="addRow()">+ Add medication</button>
            <span class="small">Tip: leave a row entirely blank to ignore it.</span>
          </div>

          <div class="actions">
            <button type="submit" class="btn">💾 Save</button>
          </div>
        </form>
      </div>
    </div>

    <script>
      function removeRow(btn){ const tr = btn.closest('tr'); if (tr) tr.remove(); }
      function addRow(){
        const tbody = document.querySelector('#itemsTable tbody');
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><input class="input" name="drug_name[]"    placeholder="e.g., Amoxicillin"></td>
          <td><input class="input" name="dosage[]"       placeholder="500mg"></td>
          <td><input class="input" name="frequency[]"    placeholder="tid"></td>
          <td><input class="input" name="duration[]"     placeholder="5 days"></td>
          <td><input class="input" name="item_notes[]"   placeholder="after meals"></td>
          <td><button type="button" class="btn danger" onclick="removeRow(this)">✕</button></td>
        `;
        tbody.appendChild(tr);
      }
    </script>
  </body>
  </html>
  <?php
  exit;
}

/* ---------------------- DASHBOARD MODE ---------------------- */

/* Needs Prescription:
   - Confirmed appointments assigned to this doctor
   - Between today and +7 days
   - That DO NOT yet have a prescription row
*/
$needsStmt = $pdo->prepare("
  SELECT a.appointment_id, s.start_dt, s.end_dt, p.patient_name, sv.service_name, a.preferred_language
  FROM appointment a
  JOIN schedule_slot s ON s.slot_id = a.slot_id
  JOIN patient p       ON p.patient_ID = a.patient_id
  LEFT JOIN service sv ON sv.service_ID = a.service_id
  LEFT JOIN prescription rx ON rx.appointment_id = a.appointment_id
  WHERE s.doctor_id = ?
    AND a.status = 'confirmed'
    AND DATE(s.start_dt) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND rx.prescription_ID IS NULL
  ORDER BY s.start_dt ASC
");
$needsStmt->execute([$doctor_id]);
$needs = $needsStmt->fetchAll();

/* Recent prescriptions by this doctor (last 20)
   Safely select "last_edit" depending on whether updated_at exists.
*/
$lastEditExpr = $hasUpdatedAt ? "COALESCE(rx.updated_at, rx.created_at)" : "rx.created_at";
$recentSql = "
  SELECT rx.prescription_ID, rx.appointment_id, rx.patient_ID, p.patient_name,
         s.start_dt, s.end_dt,
         {$lastEditExpr} AS last_edit
  FROM prescription rx
  JOIN appointment a ON a.appointment_id = rx.appointment_id
  JOIN schedule_slot s ON s.slot_id = a.slot_id
  JOIN patient p ON p.patient_ID = rx.patient_ID
  WHERE rx.doctor_ID = ?
  ORDER BY last_edit DESC
  LIMIT 20
";
$recentStmt = $pdo->prepare($recentSql);
$recentStmt->execute([$doctor_id]);
$recent = $recentStmt->fetchAll();

?>
<!doctype html>
<html>
<head>
  <?php head_tag("Prescription Dashboard"); ?>
  <link rel="stylesheet" href="styles.css">
  <style>
    html, body { margin:0; }
    body { padding-top:0 !important; background:#f7f7fb; }
    .wrapper { max-width: 1100px; margin: 0 auto; padding: 16px 20px; }
    .section { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; margin-bottom:16px; }
    .section h3 { margin:0 0 8px; }
    .list { display:flex; flex-direction:column; gap:10px; }
    .item { display:flex; justify-content:space-between; align-items:center; padding:12px; background:#fff; border:1px solid #e5e7eb; border-radius:10px; }
    .left { display:flex; flex-direction:column; gap:6px; }
    .meta { color:#6b7280; font-size:14px; display:flex; gap:12px; flex-wrap:wrap; }
    .btn { background:#111827; color:#fff; border-radius:10px; padding:8px 12px; border:0; text-decoration:none; }
    .pill { background:#eef2ff; border:1px solid #e5e7eb; border-radius:999px; padding:2px 8px; font-size:12px; }
    .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    @media (max-width: 900px){ .grid-2{ grid-template-columns:1fr; } }
    .flash { background:#ecfdf5; border:1px solid #10b981; color:#065f46; padding:10px 14px; border-radius:10px; margin-bottom:12px; }
    .err { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:10px 12px; border-radius:10px; margin-bottom:12px; }
  </style>
</head>
<body>
  <?php include "nav.php"; ?>

  <div class="wrapper">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
      <h2 style="margin:0;">Prescriptions</h2>
      <a class="btn" href="doctor.php">Back to dashboard</a>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
      <div class="flash"><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="err"><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <div class="grid-2">
      <div class="section">
        <h3>Needs Prescription (next 7 days)</h3>
        <div class="list">
          <?php if (!$needs): ?>
            <div class="item"><div class="left"><strong>No upcoming confirmed appointments without prescription.</strong></div></div>
          <?php else: foreach ($needs as $n): ?>
            <div class="item">
              <div class="left">
                <div><strong><?= e($n['patient_name']) ?></strong> <span class="pill"><?= e($n['service_name'] ?: 'Appointment') ?></span></div>
                <div class="meta">
                  <span>🗓 <?= e(date('Y-m-d', strtotime($n['start_dt']))) ?></span>
                  <span>🕒 <?= e(date('H:i', strtotime($n['start_dt']))) ?>–<?= e(date('H:i', strtotime($n['end_dt']))) ?></span>
                  <span>🌐 <?= e($n['preferred_language'] ?: '-') ?></span>
                </div>
              </div>
              <div>
                <a class="btn" href="prescription_create.php?appointment_id=<?= (int)$n['appointment_id'] ?>">Create</a>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="section">
        <h3>Your Recent Prescriptions</h3>
        <div class="list">
          <?php if (!$recent): ?>
            <div class="item"><div class="left"><strong>No prescriptions yet.</strong></div></div>
          <?php else: foreach ($recent as $r): ?>
            <div class="item">
              <div class="left">
                <div><strong><?= e($r['patient_name']) ?></strong> <span class="pill">Rx #<?= (int)$r['prescription_ID'] ?></span></div>
                <div class="meta">
                  <span>🗓 <?= e(date('Y-m-d', strtotime($r['start_dt']))) ?></span>
                  <span>🕒 <?= e(date('H:i', strtotime($r['start_dt']))) ?>–<?= e(date('H:i', strtotime($r['end_dt']))) ?></span>
                  <span>🕘 Last edit: <?= e(substr((string)$r['last_edit'],0,16)) ?></span>
                </div>
              </div>
              <div>
                <a class="btn" href="prescription_create.php?appointment_id=<?= (int)$r['appointment_id'] ?>">Edit / View</a>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>
  </div>
</body>
</html>