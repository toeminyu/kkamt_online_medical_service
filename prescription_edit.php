<?php
require_once "session.php";
require_once "db.php";
require_once "helpers.php";
require_login();

if (($_SESSION['role'] ?? '') !== 'doctor') { redirect_role($_SESSION['role'] ?? ''); }

/* ---------- Current doctor ---------- */
$dStmt = $pdo->prepare("SELECT doctor_ID, doctor_name FROM doctor WHERE user_id = ? LIMIT 1");
$dStmt->execute([$_SESSION['user_id']]);
$doc = $dStmt->fetch();
$doctor_id   = (int)($doc['doctor_ID'] ?? 0);
$displayName = $doc['doctor_name'] ?? ($_SESSION['email'] ?? 'Doctor');
if (!$doctor_id) { header("Location: login.php"); exit; }

/* ---------- Appointment context ---------- */
$appointment_id = (int)($_GET['appointment_id'] ?? $_POST['appointment_id'] ?? 0);
if (!$appointment_id) { header("Location: doctor.php"); exit; }

/* Load appointment + ensure it's assigned to this doctor */
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
if (!$appt) { $_SESSION['flash_error'] = "Appointment not found."; header("Location: doctor.php"); exit; }
if ((int)$appt['doctor_id'] !== $doctor_id) {
  $_SESSION['flash_error'] = "This appointment is not assigned to you.";
  header("Location: doctor.php"); exit;
}

/* ---------- Small helper to check optional columns ---------- */
function column_exists(PDO $pdo, $table, $col) {
  $q = $pdo->prepare("
    SELECT COUNT(*) c
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = ?
      AND COLUMN_NAME = ?
  ");
  $q->execute([$table, $col]);
  return $q->fetchColumn() > 0;
}

/* Prescription optional column */
$hasRxDescription = column_exists($pdo, 'prescription', 'prescription_description');

/* ---------- Ensure a prescription row exists for this appointment ---------- */
$rxStmt = $pdo->prepare("SELECT * FROM prescription WHERE appointment_ID = ? AND doctor_ID = ? LIMIT 1");
$rxStmt->execute([$appointment_id, $doctor_id]);
$rx = $rxStmt->fetch();

if (!$rx) {
  $insRx = $pdo->prepare("
    INSERT INTO prescription (appointment_ID, patient_ID, doctor_ID, prescription_date)
    VALUES (?, ?, ?, NOW())
  ");
  $insRx->execute([$appointment_id, (int)$appt['patient_id'], $doctor_id]);
  $rxStmt->execute([$appointment_id, $doctor_id]);
  $rx = $rxStmt->fetch();
}
$rx_id = (int)$rx['prescription_ID'];

/* ---------- Load existing items ---------- */
$itemStmt = $pdo->prepare("
  SELECT item_id, prescription_ID,
         medicine_name, dosage, instructions, duration_days
  FROM prescription_items
  WHERE prescription_ID = ?
  ORDER BY item_id
");
$itemStmt->execute([$rx_id]);
$items = $itemStmt->fetchAll();

/* ---------- POST: Save ---------- */
$err = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $rx_description = trim($_POST['prescription_description'] ?? '');

  $medicine_name = $_POST['medicine_name'] ?? [];
  $dosage        = $_POST['dosage'] ?? [];
  $instructions  = $_POST['item_instructions'] ?? [];
  $duration_days = $_POST['duration_days'] ?? [];

  $newItems = [];
  $count = max(count($medicine_name), count($dosage), count($instructions), count($duration_days));
  for ($i=0; $i<$count; $i++) {
    $nm = trim($medicine_name[$i] ?? '');
    $dg = trim($dosage[$i] ?? '');
    $in = trim($instructions[$i] ?? '');
    $du = trim($duration_days[$i] ?? '');
    if ($nm==='' && $dg==='' && $in==='' && $du==='') continue;
    $newItems[] = [$nm, $dg, $in, (string)(is_numeric($du) ? (int)$du : 0)];
  }

  try {
    $pdo->beginTransaction();

    if ($hasRxDescription) {
      $u = $pdo->prepare("UPDATE prescription SET prescription_description=? WHERE prescription_ID=? AND doctor_ID=?");
      $u->execute([$rx_description, $rx_id, $doctor_id]);
    }

    $pdo->prepare("DELETE FROM prescription_items WHERE prescription_ID=?")->execute([$rx_id]);

    if (!empty($newItems)) {
      $insItem = $pdo->prepare("
        INSERT INTO prescription_items (prescription_ID, medicine_name, dosage, instructions, duration_days)
        VALUES (?, ?, ?, ?, ?)
      ");
      foreach ($newItems as $it) {
        $insItem->execute([$rx_id, $it[0], $it[1], $it[2], $it[3]]);
      }
    }

    $pdo->commit();
    $_SESSION['flash_success'] = "✅ Prescription saved.";
    header("Location: prescription_edit.php?appointment_id=".$appointment_id);
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
  <?php head_tag("Write Prescription"); ?>
  <link rel="stylesheet" href="styles.css">

  <style>
    :root{ --brand-1:#667eea; --brand-2:#764ba2; --hover-dark:#374151; }

    html, body { margin:0; }
    body { background:#f7f7fb; }

    .wrapper { max-width:1100px; margin:0 auto; padding:16px 20px; }
    .panel   { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; }
    .row     { display:flex; gap:16px; align-items:center; flex-wrap:wrap; margin-bottom:10px; }
    .muted   { color:#6b7280; }
    .input, textarea { width:100%; padding:10px 12px; border:1px solid #e5e7eb; border-radius:10px; background:#fff; }
    textarea { min-height:110px; }

    .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

    .items-table { width:100%; border-collapse:collapse; margin-top:10px; }
    .items-table th, .items-table td { border:1px solid #e5e7eb; padding:8px; font-size:14px; }
    .items-table th { background:#f9fafb; text-align:left; }
    .small { font-size:12px; color:#6b7280; }

    /* Buttons change secondary for color */
    .btn{
      background: linear-gradient(135deg, var(--brand-1), var(--brand-2));
      color:#fff; border-radius:10px; padding:10px 14px; border:0; cursor:pointer;
      text-decoration:none; display:inline-flex; align-items:center; justify-content:center;
      gap:6px; font-weight:600; transition:.2s ease; box-shadow:0 2px 8px rgba(0,0,0,.08);
    }
    .btn.secondary{ background:#667EEA; color:#fff; }
        .btn.secondary:hover{ background:black; color:white; transform:none; }


    /* Compact + no-hover (for All Prescriptions, Add medication, Save) */
    .btn.compact{ padding:6px 10px; border-radius:8px; box-shadow:none; background:rgba(0,0,0,.6); color:#fff;}
    .btn.compact:hover{ background:black; color:white; transform:none; }

    .btn.cancel-row{ padding:8px 12px; border-radius:999px; }
    .actions { display:flex; gap:10px; margin-top:12px; flex-wrap:wrap; }

    .flash { background:#ecfdf5; border:1px solid #10b981; color:#065f46; padding:10px 14px; border-radius:10px; margin-bottom:12px; }
    .err   { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:10px 12px; border-radius:10px; margin-bottom:12px; }
  </style>
</head>
<body>
  <?php include "nav.php"; ?>

  <div class="wrapper">
    <div class="panel">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
        <h2 style="margin:0;">Write Prescription</h2>
        <div style="display:flex; gap:8px;">
          <a class="btn secondary" href="doctor.php">Back</a>
          <!-- compact + no-hover required -->
          <a class="btn compact nohover" href="prescription_list.php">All Prescriptions</a>
        </div>
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
      <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>

      <form method="post" id="rxForm">
        <input type="hidden" name="appointment_id" value="<?= (int)$appointment_id ?>">

        <div class="grid-2">
          <div>
            <label><strong>Prescription Notes (overall)</strong></label>
            <textarea name="prescription_description" class="input" placeholder="General advice or overall instructions"><?= e($hasRxDescription ? ($rx['prescription_description'] ?? '') : '') ?></textarea>
            <?php if (!$hasRxDescription): ?>
              <div class="small muted">Note: your <code>prescription</code> table has no <code>prescription_description</code> column; this field will be ignored on save.</div>
            <?php endif; ?>
          </div>
          <div>
            <label><strong>Visit context</strong></label>
            <div class="small muted">
              Service: <?= e($appt['service_name'] ?: 'Appointment') ?> —
              Language: <?= e($appt['preferred_language'] ?: '-') ?> —
              Starts: <?= e(date('Y-m-d H:i', strtotime($appt['start_dt']))) ?>
            </div>
          </div>
        </div>

        <h3 style="margin-top:16px;">Medications</h3>
        <table class="items-table" id="itemsTable">
          <thead>
            <tr>
              <th style="width:28%;">Medicine name</th>
              <th style="width:18%;">Dosage</th>
              <th style="width:26%;">Instructions</th>
              <th style="width:12%;">Duration (days)</th>
              <th style="width:60px;">&nbsp;</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$items): ?>
              <tr>
                <td><input class="input" name="medicine_name[]"    placeholder="e.g., Paracetamol"></td>
                <td><input class="input" name="dosage[]"           placeholder="500mg"></td>
                <td><input class="input" name="item_instructions[]" placeholder="q6h PRN, after meals"></td>
                <td><input class="input" name="duration_days[]"    placeholder="3"></td>
                <td style="text-align:center; width:130px;">
                  <button type="button" class="btn secondary cancel-row" onclick="removeMedRow(this)">Cancel</button>
                </td>
              </tr>
            <?php else: foreach ($items as $it): ?>
              <tr>
                <td><input class="input" name="medicine_name[]"     value="<?= e($it['medicine_name']) ?>"></td>
                <td><input class="input" name="dosage[]"            value="<?= e($it['dosage']) ?>"></td>
                <td><input class="input" name="item_instructions[]" value="<?= e($it['instructions']) ?>"></td>
                <td><input class="input" name="duration_days[]"     value="<?= e($it['duration_days']) ?>"></td>
                <td style="text-align:center; width:130px;">
                  <button type="button" class="btn secondary cancel-row" onclick="removeMedRow(this)">Cancel</button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>

        <!-- Add + Save directly under the table; both compact & no-hover -->
        <div class="add-row" style="display:flex; align-items:center; gap:10px; margin-top:10px;">
          <button type="button" class="btn compact nohover" onclick="addRow()">+ Add medication</button>
          <button type="submit" class="btn compact nohover">Save</button>
          <span class="small">Tip: leave a row entirely blank to ignore it.</span>
        </div>

        <div class="actions">
          <a class="btn secondary" href="prescription_view.php?id=<?= (int)$rx_id ?>">Preview</a>
          <a class="btn secondary" href="doctor_patients.php?patient_id=<?= (int)$appt['patient_id'] ?>">Back to patient</a>
        </div>
      </form>
    </div>
  </div>

  <script>
    function removeMedRow(btn){
      const tr = btn.closest('tr');
      if (!tr) return;
      const tbody = tr.parentElement;
      const rows = tbody.querySelectorAll('tr');
      if (rows.length <= 1){
        tr.querySelectorAll('input').forEach(i => i.value = '');
      } else {
        tr.remove();
      }
    }

    function addRow(){
      const tbody = document.querySelector('#itemsTable tbody');
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><input class="input" name="medicine_name[]"    placeholder="e.g., Amoxicillin"></td>
        <td><input class="input" name="dosage[]"           placeholder="500mg"></td>
        <td><input class="input" name="item_instructions[]" placeholder="tid / after meals"></td>
        <td><input class="input" name="duration_days[]"    placeholder="5"></td>
        <td style="text-align:center; width:130px;">
          <button type="button" class="btn secondary cancel-row" onclick="removeMedRow(this)">Cancel</button>
        </td>
      `;
      tbody.appendChild(tr);
    }
  </script>
</body>
</html>
