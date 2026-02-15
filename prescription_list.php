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
$hasCreatedAt    = column_exists($pdo, 'prescription', 'created_at');

/* ---------- “Needs Prescription” (next 7 days) ---------- */
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

/* ---------- Recent prescriptions ---------- */
if ($hasUpdatedAt && $hasCreatedAt) {
  $lastEditExpr = "COALESCE(rx.updated_at, rx.created_at)";
} elseif ($hasUpdatedAt) {
  $lastEditExpr = "rx.updated_at";
} elseif ($hasCreatedAt) {
  $lastEditExpr = "rx.created_at";
} else {
  $lastEditExpr = "s.start_dt";
}

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
    body { padding-top:0 !important; background:#f7f7fb; font-family:'Segoe UI', sans-serif; }
    .wrapper { max-width: 1100px; margin: 0 auto; padding: 16px 20px; }
    .section { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; margin-bottom:16px; }
    .section h3 { margin:0 0 8px; }
    .list { display:flex; flex-direction:column; gap:10px; }
    .item { display:flex; justify-content:space-between; align-items:center; padding:12px; background:#fff; border:1px solid #e5e7eb; border-radius:10px; }
    .left { display:flex; flex-direction:column; gap:6px; }
    .meta { color:#6b7280; font-size:14px; display:flex; gap:12px; flex-wrap:wrap; }
    .pill { background:#eef2ff; border:1px solid #e5e7eb; border-radius:999px; padding:2px 8px; font-size:12px; }
    .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    @media (max-width: 900px){ .grid-2{ grid-template-columns:1fr; } }

    /* Flash messages */
    .flash { background:#ecfdf5; border:1px solid #10b981; color:#065f46; padding:10px 14px; border-radius:10px; margin-bottom:12px; }
    .err { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:10px 12px; border-radius:10px; margin-bottom:12px; }

    /* Stable black buttons (Back + others) */
    .btn-stable {
      background:#111827 !important;
      color:#ffffff !important;
      border:0 !important;
      border-radius:10px !important;
      padding:8px 14px !important;
      font-weight:300!important;
      text-decoration:none !important;
      cursor:pointer !important;
      box-shadow:none !important;
      transition:none !important;
    }
    .btn-stable:hover,
    .btn-stable:focus,
    .btn-stable:active {
      background:#111827 !important;
      color:#ffffff !important;
      outline:none !important;
    }

    /* Gradient Edit button */
    .button .btn {
      background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);
      color:#fff;
      border:none;
      border-radius:10px;
      padding:8px 14px;
      text-align:center;
      font-weight:600;
      cursor:pointer;
      transition:all .2s ease-in-out;
      display:inline-block;
    }
    .button .btn:hover {
      background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 100%);
      transform:translateY(-2px);
    }
  </style>
</head>
<body>
  <?php include "nav.php"; ?>

  <div class="wrapper">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
      <h2 style="margin:0;">Prescriptions</h2>
      <a class="btn-stable" href="doctor.php">Back to Doctor Dashboard</a>
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
              <div class="button">
                <a class="btn" href="prescription_edit.php?appointment_id=<?= (int)$n['appointment_id'] ?>">Create</a>
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
              <div class="button">
                <a class="btn" href="prescription_edit.php?appointment_id=<?= (int)$r['appointment_id'] ?>">Edit</a>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>
  </div>
</body>
</html>