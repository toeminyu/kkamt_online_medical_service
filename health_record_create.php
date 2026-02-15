<?php
require_once "session.php";
require_once "db.php";
require_once "helpers.php";

require_login();
if (($_SESSION['role'] ?? '') !== 'doctor') redirect_role($_SESSION['role']);

/* ---------- Current doctor ---------- */
$dStmt = $pdo->prepare("SELECT doctor_ID, doctor_name FROM doctor WHERE user_id = ? LIMIT 1");
$dStmt->execute([$_SESSION['user_id']]);
$doc = $dStmt->fetch();
$doctor_id   = (int)($doc['doctor_ID'] ?? 0);
$displayName = $doc['doctor_name'] ?? ($_SESSION['email'] ?? 'Doctor');
if (!$doctor_id) { header("Location: login.php"); exit; }

/* ---------- Helpers ---------- */
function col_exists(PDO $pdo, string $table, string $col): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
  $st->execute([$col]);
  return (bool)$st->fetch();
}
$hasDoctorCol = col_exists($pdo, 'health_record', 'doctor_ID'); // optional
$hasPrescCol  = col_exists($pdo, 'health_record', 'prescription_ID'); // optional
$hasApptCol   = col_exists($pdo, 'health_record', 'appointment_ID'); // should exist based on your design

/* ---------- Inputs ---------- */
$patient_id = (int)($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);
$err = "";
$msg = "";

/* ---------- CSRF ---------- */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

/* ---------- Authorization: patient must be on this doctor's roster ---------- */
$allowed = false;
if ($patient_id) {
  $chk = $pdo->prepare("
    SELECT 1
    FROM appointment a
    JOIN schedule_slot s ON s.slot_id = a.slot_id
    WHERE s.doctor_id = ? AND a.patient_id = ? AND a.status IN ('confirmed','completed')
    LIMIT 1
  ");
  $chk->execute([$doctor_id, $patient_id]);
  $allowed = (bool)$chk->fetch();
}
if (!$patient_id || !$allowed) {
  $_SESSION['flash_error'] = "You can only create records for your own patients.";
  header("Location: doctor_patients.php");
  exit;
}

/* ---------- Patient header ---------- */
$pinfo = $pdo->prepare("SELECT patient_ID, patient_name FROM patient WHERE patient_ID=?");
$pinfo->execute([$patient_id]);
$patient = $pinfo->fetch();

/* ---------- Recent appts (to link record) ---------- */
$ap = $pdo->prepare("
  SELECT a.appointment_id, a.status, s.start_dt, s.end_dt, COALESCE(sv.service_name,'Appointment') AS service_name
  FROM appointment a
  JOIN schedule_slot s ON s.slot_id = a.slot_id
  LEFT JOIN service sv ON sv.service_ID = a.service_id
  WHERE a.patient_id = ? AND s.doctor_id = ?
  ORDER BY s.start_dt DESC
  LIMIT 20
");
$ap->execute([$patient_id, $doctor_id]);
$appts = $ap->fetchAll();

/* ---------- Lookups: severity + symptoms ---------- */
$severity = $pdo->query("SELECT severity_id, level FROM severity ORDER BY severity_id")->fetchAll(PDO::FETCH_ASSOC);
$symptoms = $pdo->query("SELECT symptom_id, symptom_name FROM symptom ORDER BY symptom_name")->fetchAll(PDO::FETCH_ASSOC);

/* ---------- POST handler ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  // CSRF
  if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
    $err = "Invalid form token. Please try again.";
  } else {
    $appointment_id  = (int)($_POST['appointment_id'] ?? 0);
    $prescription_id = (int)($_POST['prescription_id'] ?? 0);
    $desc            = trim($_POST['health_record_description'] ?? "");

    // details arrays
    $symptom_ids = $_POST['symptom_id'] ?? [];
    $severity_ids = $_POST['severity_id'] ?? [];
    $durations = $_POST['duration'] ?? [];
    $notesArr  = $_POST['notes'] ?? [];

    if ($desc === "" && empty(array_filter($symptom_ids))) {
      $err = "Please write record notes or add at least one symptom.";
    }

    // Validate appointment belongs to this doctor+patient (if given)
    if (!$err && $appointment_id) {
      $c2 = $pdo->prepare("
        SELECT 1
        FROM appointment a
        JOIN schedule_slot s ON s.slot_id = a.slot_id
        WHERE a.appointment_id = ? AND a.patient_id = ? AND s.doctor_id = ?
        LIMIT 1
      ");
      $c2->execute([$appointment_id, $patient_id, $doctor_id]);
      if (!$c2->fetch()) $err = "Selected appointment is invalid.";
    }

    // Optional: verify prescription belongs (if provided and table matches)
    if (!$err && $prescription_id) {
      try {
        $c3 = $pdo->prepare("
          SELECT 1 FROM prescription
          WHERE prescription_ID = ? AND patient_ID = ? AND doctor_ID = ?
          LIMIT 1
        ");
        $c3->execute([$prescription_id, $patient_id, $doctor_id]);
        if (!$c3->fetch()) $err = "Prescription ID doesn't match this patient/doctor.";
      } catch (Exception $e) {
        // ignore if table not present
      }
    }

    // Build normalized, non-empty detail rows
    $detailRows = [];
    $max = max(count($symptom_ids), count($severity_ids), count($durations), count($notesArr));
    for ($i=0; $i<$max; $i++) {
      $sid = (int)($symptom_ids[$i] ?? 0);
      $sevid = (int)($severity_ids[$i] ?? 0);
      $dur = trim((string)($durations[$i] ?? ""));
      $nt  = trim((string)($notesArr[$i] ?? ""));
      if ($sid<=0 && $sevid<=0 && $dur==="" && $nt==="") continue; // ignore empty row
      if ($sid<=0) { $err = "Each symptom row must have a Symptom selected."; break; }
      if ($sevid<=0) { $err = "Each symptom row must have a Severity selected."; break; }
      // duration is free text per your design; allow empty
      $detailRows[] = [$sid, $sevid, $dur, $nt];
    }

    if (!$err) {
      try {
        $pdo->beginTransaction();

        // insert header
        $cols = ['patient_ID', 'health_record_description', 'created_at'];
        $vals = [$patient_id, $desc, date('Y-m-d H:i:s')];

        if ($hasApptCol && $appointment_id) { $cols[] = 'appointment_ID';  $vals[] = $appointment_id; }
        if ($hasPrescCol && $prescription_id){ $cols[] = 'prescription_ID'; $vals[] = $prescription_id; }
        if ($hasDoctorCol) { $cols[] = 'doctor_ID'; $vals[] = $doctor_id; }

        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $colnames     = '`' . implode('`,`', $cols) . '`';

        $ins = $pdo->prepare("INSERT INTO `health_record` ($colnames) VALUES ($placeholders)");
        $ins->execute($vals);
        $recordId = (int)$pdo->lastInsertId();

        // insert details (if any)
        if (!empty($detailRows)) {
          $insDet = $pdo->prepare("
            INSERT INTO health_record_details (health_record_ID, symptom_id, severity_id, duration, notes)
            VALUES (?, ?, ?, ?, ?)
          ");
          foreach ($detailRows as $r) {
            $insDet->execute([$recordId, $r[0], $r[1], $r[2], $r[3]]);
          }
        }

        $pdo->commit();
        $_SESSION['flash_success'] = "✅ Health record #$recordId saved.";
        header("Location: doctor_patients.php?patient_id=".$patient_id);
        exit;

      } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = "Failed to save record. ".$e->getMessage();
      }
    }
  }
}
?>
<!doctype html>
<html>
<head>
  <?php head_tag("New Health Record"); ?>
  <link rel="stylesheet" href="styles.css">

  <style>
    :root{ --brand-1:#667eea; --brand-2:#764ba2; --hover-dark:#374151; }
    html, body { margin:0; }
    body { background:#f7f7fb; }

    .wrap{max-width:1100px;margin:0 auto;padding:16px 20px;}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;}
    .title{font-size:1.4rem;font-weight:700;margin:0 0 8px;}
    .sub{color:#6b7280;margin:0 0 12px;}

    label{display:block;font-weight:600;margin:10px 0 6px;}
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
    .grid-1{display:block;}
    .input, select, textarea{width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;}
    textarea{min-height:130px;resize:vertical;}

    .items-table { width:100%; border-collapse:collapse; margin-top:10px; }
    .items-table th, .items-table td { border:1px solid #e5e7eb; padding:8px; font-size:14px; }
    .items-table th { background:#f9fafb; text-align:left; }
    .small { font-size:12px; color:#6b7280; }

    .btn{
      background: linear-gradient(135deg, var(--brand-1), var(--brand-2));
      color:#fff; border-radius:10px; padding:10px 14px; border:0; cursor:pointer;
      text-decoration:none; display:inline-flex; align-items:center; justify-content:center;
      gap:6px; font-weight:600; transition:.2s ease; box-shadow:0 2px 8px rgba(0,0,0,.08);
    }
    .btn.secondary{ background:#667EEA; color:#fff; }
    .btn.secondary:hover{ background:black; color:white; transform:none; }
    .btn.compact{ padding:6px 10px; border-radius:8px; box-shadow:none; background:rgba(0,0,0,.6); color:#fff; }
    .btn.compact:hover{ background:black; color:white; transform:none; }
    .btn.cancel-row{ padding:8px 12px; border-radius:999px; }

    .actions { display:flex; gap:10px; margin-top:12px; flex-wrap:wrap; }
    .flash { background:#ecfdf5; border:1px solid #10b981; color:#065f46; padding:10px 14px; border-radius:10px; margin-bottom:12px; }
    .err   { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:10px 12px; border-radius:10px; margin-bottom:12px; }
  </style>
</head>
<body>
  <?php include "nav.php"; ?>

  <div class="wrap">
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
        <h2 style="margin:0;">New Health Record</h2>
        <div style="display:flex; gap:8px;">
          <a class="btn secondary" href="doctor_patients.php?patient_id=<?= (int)$patient_id ?>">Back</a>
          <a class="btn compact nohover" href="doctor_patients.php?patient_id=<?= (int)$patient_id ?>">Patient Overview</a>
        </div>
      </div>

      <div class="sub">
        Patient: <strong><?= e($patient['patient_name'] ?? ('#'.$patient_id)) ?></strong>
        <span class="small">ID #<?= (int)$patient_id ?></span>
      </div>

      <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="flash"><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
      <?php endif; ?>
      <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>

      <form method="post" id="hrForm">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="patient_id" value="<?= (int)$patient_id ?>">

        <div class="grid-2">
          <div>
            <label for="appointment_id">Link to Appointment (optional)</label>
            <select id="appointment_id" name="appointment_id" class="input">
              <option value="0">— None —</option>
              <?php foreach ($appts as $a):
                $labelDate = date('Y-m-d H:i', strtotime($a['start_dt']));
                $label = $labelDate . " · " . $a['service_name'] . " (" . ucfirst($a['status']) . ")";
              ?>
                <option value="<?= (int)$a['appointment_id'] ?>"><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="prescription_id">Attach Prescription ID (optional)</label>
            <input type="number" id="prescription_id" name="prescription_id" min="0" class="input" placeholder="e.g., 1023">
          </div>
        </div>

        <div class="grid-1" style="margin-top:10px;">
          <label for="desc"><strong>Record Notes (overall)</strong></label>
          <textarea id="desc" name="health_record_description" class="input" placeholder="Write the consultation summary, history, exam findings, assessment, plan..."></textarea>
        </div>

        <h3 style="margin-top:16px;">Symptoms</h3>
        <table class="items-table" id="symptomsTable">
          <thead>
            <tr>
              <th style="width:32%;">Symptom</th>
              <th style="width:20%;">Severity</th>
              <th style="width:20%;">Duration</th>
              <th style="width:22%;">Notes</th>
              <th style="width:60px;">&nbsp;</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>
                <select class="input" name="symptom_id[]">
                  <option value="">— Select —</option>
                  <?php foreach ($symptoms as $s): ?>
                    <option value="<?= (int)$s['symptom_id'] ?>"><?= e($s['symptom_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <select class="input" name="severity_id[]">
                  <option value="">— Select —</option>
                  <?php foreach ($severity as $v): ?>
                    <option value="<?= (int)$v['severity_id'] ?>"><?= e($v['level']) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input class="input" name="duration[]" placeholder="e.g., 2 days / since yesterday"></td>
              <td><input class="input" name="notes[]" placeholder="optional notes"></td>
              <td style="text-align:center; width:130px;">
                <button type="button" class="btn secondary cancel-row" onclick="removeRow(this)">Cancel</button>
              </td>
            </tr>
          </tbody>
        </table>

        <div class="add-row" style="display:flex; align-items:center; gap:10px; margin-top:10px;">
          <button type="button" class="btn compact nohover" onclick="addRow()">+ Add symptom</button>
          <button type="submit" class="btn compact nohover">Save</button>
          <span class="small">Tip: leave a row entirely blank to ignore it.</span>
        </div>

        <div class="actions">
          <a class="btn secondary" href="doctor_patients.php?patient_id=<?= (int)$patient_id ?>">Back to patient</a>
        </div>
      </form>
    </div>
  </div>

  <script>
    function removeRow(btn){
      const tr = btn.closest('tr');
      if (!tr) return;
      const tbody = tr.parentElement;
      const rows = tbody.querySelectorAll('tr');
      if (rows.length <= 1){
        tr.querySelectorAll('input, select').forEach(i => i.value = '');
      } else {
        tr.remove();
      }
    }

    function addRow(){
      const tbody = document.querySelector('#symptomsTable tbody');
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>
          <select class="input" name="symptom_id[]">
            <option value="">— Select —</option>
            <?php foreach ($symptoms as $s): ?>
              <option value="<?= (int)$s['symptom_id'] ?>"><?= e($s['symptom_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td>
          <select class="input" name="severity_id[]">
            <option value="">— Select —</option>
            <?php foreach ($severity as $v): ?>
              <option value="<?= (int)$v['severity_id'] ?>"><?= e($v['level']) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td><input class="input" name="duration[]" placeholder="e.g., 3 days / since Monday"></td>
        <td><input class="input" name="notes[]" placeholder="optional notes"></td>
        <td style="text-align:center; width:130px;">
          <button type="button" class="btn secondary cancel-row" onclick="removeRow(this)">Cancel</button>
        </td>
      `;
      tbody.appendChild(tr);
    }
  </script>
</body>
</html>
