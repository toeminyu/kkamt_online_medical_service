<?php
require_once "session.php";
require_once "db.php";
require_once "helpers.php";
require_login();
if (($_SESSION['role'] ?? '') !== 'patient') redirect_role($_SESSION['role']);

$rx_id = (int)($_GET['id'] ?? 0);
if (!$rx_id) { header("Location: prescriptions.php"); exit; }

/* current patient */
$pStmt = $pdo->prepare("SELECT patient_ID, patient_name FROM patient WHERE user_id = ? LIMIT 1");
$pStmt->execute([$_SESSION['user_id']]);
$patient = $pStmt->fetch();
$patient_id = (int)($patient['patient_ID'] ?? 0);
if (!$patient_id) { header("Location: login.php"); exit; }

/* Load prescription (ensure it belongs to this patient) */
$rxStmt = $pdo->prepare("
  SELECT pr.*, d.doctor_name, d.doctor_degree, d.doctor_ph, d.doctor_email,
         p.patient_name, p.patient_dob, p.patient_gender,
         a.appointment_id, a.service_id, a.preferred_language,
         s.start_dt, s.end_dt,
         sv.service_name
  FROM prescription pr
  JOIN doctor d        ON d.doctor_ID = pr.doctor_ID
  JOIN patient p       ON p.patient_ID = pr.patient_ID
  LEFT JOIN appointment a ON a.appointment_id = pr.appointment_ID
  LEFT JOIN schedule_slot s ON s.slot_id = a.slot_id
  LEFT JOIN service sv   ON sv.service_ID = a.service_id
  WHERE pr.prescription_ID = ? AND pr.patient_ID = ?
  LIMIT 1
");
$rxStmt->execute([$rx_id, $patient_id]);
$rx = $rxStmt->fetch();
if (!$rx) { header("Location: prescriptions.php"); exit; }

/* Load items */
$itStmt = $pdo->prepare("
  SELECT item_id, medicine_name, dosage, instructions, duration_days
  FROM prescription_items
  WHERE prescription_ID = ?
  ORDER BY item_id
");
$itStmt->execute([$rx_id]);
$items = $itStmt->fetchAll();

/* Setup values */
$clinicName  = "Online Medical Service";
$clinicAddr  = "Bangkok, Thailand";
$clinicPhone = $rx['doctor_ph'] ?? '';
$clinicEmail = $rx['doctor_email'] ?? '';
$doctorLine  = $rx['doctor_name'] . (!empty($rx['doctor_degree']) ? ", " . $rx['doctor_degree'] : "");
$rxDate      = !empty($rx['prescription_date']) ? date('Y-m-d', strtotime($rx['prescription_date'])) : date('Y-m-d');
$apptTimeLine = $rx['start_dt'] ? (date('Y-m-d H:i', strtotime($rx['start_dt'])) . " – " . date('H:i', strtotime($rx['end_dt']))) : '-';
$rx_description = $rx['prescription_description'] ?? '';
$rx_diagnosis   = $rx['diagnosis'] ?? '';
?>
<!doctype html>
<html>
<head>
  <?php head_tag("Prescription #".$rx_id); ?>
  <style>
    html, body { margin:0; }
    body { padding-top:0 !important; background:#f7f7fb; color:#111827; font-family:'Segoe UI',sans-serif; }
    .wrapper { max-width: 900px; margin: 0 auto; padding: 20px; }

    .rx-card {
      background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:22px;
      box-shadow: 0 3px 10px rgba(0,0,0,.04);
    }

    .rx-header {
      display:flex; align-items:center; gap:16px; border-bottom:1px solid #e5e7eb; padding-bottom:14px; margin-bottom:14px;
    }
    .rx-logo {
      width:56px; height:56px; border-radius:12px; background:#eef2ff; display:flex; align-items:center; justify-content:center;
      font-weight:700;
    }
    .rx-headlines { display:flex; flex-direction:column; }
    .rx-title { font-size:20px; font-weight:800; line-height:1.2; }
    .rx-sub   { color:#6b7280; font-size:13px; }

    .rx-meta { display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-top:10px; }
    @media (max-width:700px){ .rx-meta{ grid-template-columns:1fr; } }
    .meta-block {
      background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:12px;
      font-size:14px;
    }
    .meta-block strong { display:inline-block; min-width:130px; }

    .section-title { margin:16px 0 8px; font-weight:700; }
    .muted { color:#6b7280; }

    table.rx-items { width:100%; border-collapse:collapse; margin-top:6px; }
    table.rx-items th, table.rx-items td { border:1px solid #e5e7eb; padding:8px; font-size:14px; vertical-align:top; }
    table.rx-items th { background:#f3f4f6; text-align:left; }

    .rx-footer {
      display:flex; justify-content:space-between; align-items:flex-end; gap:16px; margin-top:24px;
    }
    .sig { text-align:right; }
    .sig .line { width:220px; height:1px; background:#d1d5db; margin:34px 0 6px  auto; }
    .sig .name { font-weight:700; }

    /* ---------- Buttons ---------- */
    .btnbar { display:flex; gap:10px; justify-content:flex-end; margin-bottom:12px; }
    .btn { background:#111827; color:#fff; border-radius:10px; padding:10px 14px; text-decoration:none; display:inline-block; }
    
    /* stable black Back button */
    .btn-stable{
      background:#111827 !important;
      color:#ffffff !important;
      border:0 !important;
      border-radius:10px !important;
      padding:10px 14px !important;
      font-weight:400 !important;
      text-decoration:none !important;
      box-shadow:none !important;
      cursor:pointer !important;
      transition:none !important;
    }
    .btn-stable:hover,
    .btn-stable:focus,
    .btn-stable:active{
      background:#111827 !important;
      color:#ffffff !important;
      box-shadow:none !important;
      outline:none !important;
    }

    @media print {
      body { background:#fff; }
      .btnbar, nav { display:none !important; }
      .wrapper { max-width:100%; padding:0; }
      .rx-card { border:none; box-shadow:none; border-radius:0; }
    }
  </style>
</head>
<body>
  <?php include "nav.php"; ?>

  <div class="wrapper">
    <div class="btnbar">
      <!-- fixed Back button -->
      <a class="btn-stable" href="prescriptions.php">← Back</a>
    </div>

    <div class="rx-card">
      <div class="rx-header">
        <div class="rx-logo">Rx</div>
        <div class="rx-headlines">
          <div class="rx-title"><?= e($clinicName) ?></div>
          <div class="rx-sub">
            <?= e($clinicAddr) ?>
            <?php if ($clinicPhone): ?>&nbsp;•&nbsp;Tel: <?= e($clinicPhone) ?><?php endif; ?>
            <?php if ($clinicEmail): ?>&nbsp;•&nbsp;Email: <?= e($clinicEmail) ?><?php endif; ?>
          </div>
        </div>
      </div>

      <div class="rx-meta">
        <div class="meta-block">
          <div><strong>Patient:</strong> <?= e($rx['patient_name']) ?></div>
          <div><strong>DOB:</strong> <?= e($rx['patient_dob'] ?? '-') ?></div>
          <div><strong>Gender:</strong> <?= e($rx['patient_gender'] ?? '-') ?></div>
        </div>
        <div class="meta-block">
          <div><strong>Prescription No.:</strong> #<?= (int)$rx_id ?></div>
          <div><strong>Date:</strong> <?= e($rxDate) ?></div>
          <div><strong>Doctor:</strong> <?= e($doctorLine) ?></div>
        </div>
      </div>

      <div class="meta-block" style="margin-top:12px;">
        <div><strong>Service:</strong> <?= e($rx['service_name'] ?: 'Appointment') ?></div>
        <div><strong>Consultation Time:</strong> <?= e($apptTimeLine) ?></div>
        <div><strong>Preferred Language:</strong> <?= e($rx['preferred_language'] ?: '-') ?></div>
      </div>

      <?php if ($rx_diagnosis !== ''): ?>
        <div class="section-title">Diagnosis</div>
        <div class="meta-block"><?= nl2br(e($rx_diagnosis)) ?></div>
      <?php endif; ?>

      <?php if ($rx_description !== ''): ?>
        <div class="section-title">General Notes / Instructions</div>
        <div class="meta-block"><?= nl2br(e($rx_description)) ?></div>
      <?php endif; ?>

      <div class="section-title">Medications</div>
      <?php if (!$items): ?>
        <div class="muted">No medications recorded.</div>
      <?php else: ?>
        <table class="rx-items">
          <thead>
            <tr>
              <th style="width:28%;">Medicine</th>
              <th style="width:16%;">Dosage</th>
              <th>Instructions</th>
              <th style="width:12%;">Duration (days)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $it): ?>
              <tr>
                <td><strong><?= e($it['medicine_name']) ?></strong></td>
                <td><?= e($it['dosage']) ?></td>
                <td><?= nl2br(e($it['instructions'])) ?></td>
                <td><?= e($it['duration_days']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <div class="rx-footer">
        <div class="muted">
          * Take medicines exactly as directed. Seek care if symptoms worsen or allergic reactions occur.
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