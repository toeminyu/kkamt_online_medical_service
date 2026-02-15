<?php
require_once "session.php";
require_once "db.php";
require_once "helpers.php";

require_login();
if (($_SESSION['role'] ?? '') !== 'doctor') redirect_role($_SESSION['role']);

$record_id = (int)($_GET['id'] ?? 0);
if (!$record_id) { header("Location: doctor.php"); exit; }

/* ---------- Current doctor ---------- */
$dStmt = $pdo->prepare("SELECT doctor_ID, doctor_name, doctor_degree, doctor_ph, doctor_email FROM doctor WHERE user_id = ? LIMIT 1");
$dStmt->execute([$_SESSION['user_id']]);
$doctor = $dStmt->fetch();
$doctor_id   = (int)($doctor['doctor_ID'] ?? 0);
$displayName = $doctor['doctor_name'] ?? ($_SESSION['email'] ?? 'Doctor');
if (!$doctor_id) { header("Location: login.php"); exit; }

/* ---------- Detect if health_record.doctor_ID column exists ---------- */
$hasDoctorCol = false;
try {
  $hasDoctorCol = (bool)$pdo->query("SHOW COLUMNS FROM health_record LIKE 'doctor_ID'")->fetch();
} catch (Exception $e) {}

/* ---------- Load record header + joins for time/service context ---------- */
$hrStmt = $pdo->prepare("
  SELECT hr.*, 
         p.patient_name, p.patient_dob, p.patient_gender, p.patient_ID,
         a.appointment_id, a.service_id, a.preferred_language,
         s.start_dt, s.end_dt, s.doctor_id AS appt_doctor_id,
         sv.service_name
  FROM health_record hr
  JOIN patient p            ON p.patient_ID = hr.patient_ID
  LEFT JOIN appointment a   ON a.appointment_id = hr.appointment_ID
  LEFT JOIN schedule_slot s ON s.slot_id = a.slot_id
  LEFT JOIN service sv      ON sv.service_ID = a.service_id
  WHERE hr.health_record_ID = ?
  LIMIT 1
");
$hrStmt->execute([$record_id]);
$rec = $hrStmt->fetch();
if (!$rec) {
  $_SESSION['flash_error'] = "Health record not found.";
  header("Location: doctor_patients.php"); exit;
}

/* ---------- Authorization: ensure this is the doctor's patient ---------- */
$allowed = false;
if ($hasDoctorCol) {
  // If record stores doctor_ID, require it matches
  $allowed = ((int)$rec['doctor_ID'] === $doctor_id);
} elseif (!empty($rec['appt_doctor_id'])) {
  // If it has an appointment, require the appointment's doctor matches
  $allowed = ((int)$rec['appt_doctor_id'] === $doctor_id);
} else {
  // Fallback: verify this patient has been treated by this doctor in the past
  $chk = $pdo->prepare("
    SELECT 1
    FROM appointment a
    JOIN schedule_slot s ON s.slot_id = a.slot_id
    WHERE s.doctor_id = ? AND a.patient_id = ? AND a.status IN ('confirmed','completed')
    LIMIT 1
  ");
  $chk->execute([$doctor_id, (int)$rec['patient_ID']]);
  $allowed = (bool)$chk->fetch();
}
if (!$allowed) {
  $_SESSION['flash_error'] = "This health record is not assigned to you.";
  header("Location: doctor_patients.php"); exit;
}

/* ---------- Load details (robust to different PK column names) ---------- */
function hrd_has_col(PDO $pdo, string $col): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM `health_record_details` LIKE ?");
  $st->execute([$col]);
  return (bool)$st->fetch();
}

$detailIdCol = null;
foreach (['id','hrs_id','detail_id','health_record_details_id'] as $cand) {
  if (hrd_has_col($pdo, $cand)) { $detailIdCol = $cand; break; }
}

$selectId = $detailIdCol ? "d.`$detailIdCol` AS detail_id," : "";
$orderBy  = $detailIdCol ? "d.`$detailIdCol`" : "d.`created_at`, d.`symptom_id`";

$sql = "
  SELECT
    $selectId
    d.symptom_id, d.severity_id, d.duration, d.notes, d.created_at,
    sym.symptom_name,
    sev.level AS severity_level
  FROM health_record_details d
  LEFT JOIN symptom  sym ON sym.symptom_id  = d.symptom_id
  LEFT JOIN severity sev ON sev.severity_id = d.severity_id
  WHERE d.health_record_ID = ?
  ORDER BY $orderBy
";
$detStmt = $pdo->prepare($sql);
$detStmt->execute([$record_id]);
$details = $detStmt->fetchAll();

/* ---------- Niceties ---------- */
$clinicName   = "Online Medical Service";
$clinicAddr   = "Bangkok, Thailand";
$clinicPhone  = $doctor['doctor_ph']    ?? '';
$clinicEmail  = $doctor['doctor_email'] ?? '';
$doctorLine   = $doctor['doctor_name'] . (!empty($doctor['doctor_degree']) ? ", " . $doctor['doctor_degree'] : "");
$recDate      = !empty($rec['created_at']) ? date('Y-m-d', strtotime($rec['created_at'])) : date('Y-m-d');
$apptTimeLine = $rec['start_dt'] ? (date('Y-m-d H:i', strtotime($rec['start_dt'])) . " – " . date('H:i', strtotime($rec['end_dt']))) : '-';
?>
<!doctype html>
<html>
<head>
  <?php head_tag("Health Record #".$record_id); ?>
  <link rel="stylesheet" href="styles.css">
  <style>
    /* Page & print styles */
    html, body { margin:0; }
    body { padding-top:0 !important; background:#f7f7fb; color:#111827; }
    .wrapper { max-width: 900px; margin: 0 auto; padding: 20px; }

    .card {
      background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:22px;
      box-shadow: 0 3px 10px rgba(0,0,0,.04);
    }

    .hdr {
      display:flex; align-items:center; gap:16px; border-bottom:1px solid #e5e7eb; padding-bottom:14px; margin-bottom:14px;
    }
    .logo {
      width:56px; height:56px; border-radius:12px; background:#eef2ff;
      display:flex; align-items:center; justify-content:center; font-weight:700;
    }
    .headlines { display:flex; flex-direction:column; }
    .title { font-size:20px; font-weight:800; line-height:1.2; }
    .sub   { color:#6b7280; font-size:13px; }

    .meta-grid { display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-top:10px; }
    @media (max-width:700px){ .meta-grid{ grid-template-columns:1fr; } }
    .meta-block {
      background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:12px; font-size:14px;
    }
    .meta-block strong { display:inline-block; min-width:130px; }

    .section-title { margin:16px 0 8px; font-weight:700; }
    .muted { color:#6b7280; }

    table.items { width:100%; border-collapse:collapse; margin-top:6px; }
    table.items th, table.items td { border:1px solid #e5e7eb; padding:8px; font-size:14px; vertical-align:top; }
    table.items th { background:#f3f4f6; text-align:left; }

    .footer {
      display:flex; justify-content:space-between; align-items:flex-end; gap:16px; margin-top:24px;
    }
    .sig { text-align:right; }
    .sig .line { width:220px; height:1px; background:#d1d5db; margin:34px 0 6px auto; }
    .sig .name { font-weight:700; }

    .btnbar { display:flex; gap:10px; justify-content:flex-end; margin-bottom:12px; }
    .btn {
      background:#111827; color:#fff; border-radius:10px; padding:10px 14px;
      text-decoration:none; display:inline-block; border:0; cursor:pointer; transition:.15s ease;
    }
    .btn.light { background:#4b5563; color:#fff; }
    .btn:hover, .btn.light:hover { filter:brightness(0.92); color:#fff; } /* improved hover keeps white text */

    @media print {
      body { background:#fff; }
      .btnbar, nav, .topbar-spacer { display:none !important; }
      .wrapper { max-width:100%; padding:0; }
      .card { border:none; box-shadow:none; border-radius:0; }
    }
  </style>
</head>
<body>
  <?php include "nav.php"; ?>

  <div class="wrapper">
    <div class="btnbar">
      <a class="btn light" href="doctor_patients.php?patient_id=<?= (int)$rec['patient_ID'] ?>">Back to Patient</a>
      <a class="btn" href="#" onclick="window.print();return false;">Print</a>
    </div>

    <div class="card">
      <div class="hdr">
        <div class="logo">HR</div>
        <div class="headlines">
          <div class="title"><?= e($clinicName) ?></div>
          <div class="sub">
            <?= e($clinicAddr) ?>
            <?php if ($clinicPhone): ?>&nbsp;•&nbsp;Tel: <?= e($clinicPhone) ?><?php endif; ?>
            <?php if ($clinicEmail): ?>&nbsp;•&nbsp;Email: <?= e($clinicEmail) ?><?php endif; ?>
          </div>
        </div>
      </div>

      <div class="meta-grid">
        <div class="meta-block">
          <div><strong>Patient:</strong> <?= e($rec['patient_name']) ?></div>
          <div><strong>DOB:</strong> <?= e($rec['patient_dob'] ?? '-') ?></div>
          <div><strong>Gender:</strong> <?= e($rec['patient_gender'] ?? '-') ?></div>
        </div>
        <div class="meta-block">
          <div><strong>Record No.:</strong> #<?= (int)$record_id ?></div>
          <div><strong>Date:</strong> <?= e($recDate) ?></div>
          <div><strong>Doctor:</strong> <?= e($doctorLine) ?></div>
        </div>
      </div>

      <div class="meta-block" style="margin-top:12px;">
        <div><strong>Service:</strong> <?= e($rec['service_name'] ?: 'Appointment') ?></div>
        <div><strong>Consultation Time:</strong> <?= e($apptTimeLine) ?></div>
        <div><strong>Preferred Language:</strong> <?= e($rec['preferred_language'] ?: '-') ?></div>
      </div>

      <?php if (!empty($rec['health_record_description'])): ?>
        <div class="section-title">Consultation Summary</div>
        <div class="meta-block"><?= nl2br(e($rec['health_record_description'])) ?></div>
      <?php endif; ?>

      <div class="section-title">Symptoms</div>
      <?php if (!$details): ?>
        <div class="muted">No symptoms recorded for this visit.</div>
      <?php else: ?>
        <table class="items">
          <thead>
            <tr>
              <th style="width:32%;">Symptom</th>
              <th style="width:20%;">Severity</th>
              <th style="width:20%;">Duration</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($details as $d): ?>
              <tr>
                <td><strong><?= e($d['symptom_name'] ?: ('#'.$d['symptom_id'])) ?></strong></td>
                <td><?= e($d['severity_level'] ?: ('#'.$d['severity_id'])) ?></td>
                <td><?= e($d['duration'] ?: '-') ?></td>
                <td><?= nl2br(e($d['notes'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <div class="footer">
        <div class="muted">
          * This record summarizes the consultation. For emergencies or severe symptoms, seek urgent medical care.
        </div>
        <div class="sig">
          <div class="line"></div>
          <div class="name"><?= e($doctorLine) ?></div>
          <div class="muted">Signature</div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
