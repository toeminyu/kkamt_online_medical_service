<?php
require_once "session.php";
require_once "db.php";
require_once "helpers.php";
require_login();
if (($_SESSION['role'] ?? '') !== 'doctor') redirect_role($_SESSION['role']);

/* ---- Current doctor ---- */
$dStmt = $pdo->prepare("SELECT doctor_ID, doctor_name FROM doctor WHERE user_id = ? LIMIT 1");
$dStmt->execute([$_SESSION['user_id']]);
$doctor = $dStmt->fetch();
$doctor_id   = (int)($doctor['doctor_ID'] ?? 0);
$displayName = $doctor['doctor_name'] ?? ($_SESSION['email'] ?? 'Doctor');
if (!$doctor_id) { header("Location: login.php"); exit; }

/* ---- Flash messages ---- */
$flash_ok  = $_SESSION['flash_success'] ?? "";
$flash_err = $_SESSION['flash_error']   ?? "";
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

/* ---- Helpers ---- */
function safe_col(array $row, string $col, $fallback = '') {
  return array_key_exists($col, $row) && $row[$col] !== null ? $row[$col] : $fallback;
}

/* ---- Patient search/filter ---- */
$q = trim($_GET['q'] ?? '');
$patient_id = (int)($_GET['patient_id'] ?? 0);

/* ---- Patient roster for THIS doctor ---- */
$params = [$doctor_id];
$sqlPatients = "
  SELECT DISTINCT p.patient_ID, p.patient_name
  FROM appointment a
  JOIN schedule_slot s ON s.slot_id = a.slot_id
  JOIN patient p       ON p.patient_ID = a.patient_id
  WHERE s.doctor_id = ?
    AND a.status IN ('confirmed','completed')
";
if ($q !== '') {
  $sqlPatients .= " AND (p.patient_name LIKE ? OR CAST(p.patient_ID AS CHAR) LIKE ?) ";
  $like = "%{$q}%"; $params[] = $like; $params[] = $like;
}
$sqlPatients .= " ORDER BY p.patient_name ASC";
$listStmt = $pdo->prepare($sqlPatients);
$listStmt->execute($params);
$patients = $listStmt->fetchAll();

/* Default to first patient if none selected */
if (!$patient_id && $patients) $patient_id = (int)$patients[0]['patient_ID'];

/* ---- Right pane data ---- */
$patientInfo = null; $apptHistory = []; $records = []; $rxs = [];

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

  if ($allowed) {
    $pinfo = $pdo->prepare("SELECT * FROM patient WHERE patient_ID=? LIMIT 1");
    $pinfo->execute([$patient_id]);
    $patientInfo = $pinfo->fetch();

    $ah = $pdo->prepare("
      SELECT a.appointment_id, a.status, s.start_dt, s.end_dt, sv.service_name
      FROM appointment a
      JOIN schedule_slot s ON s.slot_id = a.slot_id
      LEFT JOIN service sv ON sv.service_ID = a.service_id
      WHERE a.patient_id = ?
        AND s.doctor_id = ?
        AND a.status IN ('confirmed','completed')
      ORDER BY s.start_dt DESC
      LIMIT 50
    ");
    $ah->execute([$patient_id, $doctor_id]);
    $apptHistory = $ah->fetchAll();

    // Health records (prefer column doctor_ID if exists)
    $hasDocIdCol = false;
    try { $hasDocIdCol = (bool)$pdo->query("SHOW COLUMNS FROM health_record LIKE 'doctor_ID'")->fetch(); } catch (Exception $e) {}

    if ($hasDocIdCol) {
      $hr = $pdo->prepare("
        SELECT health_record_ID, health_record_description, created_at
        FROM health_record
        WHERE patient_ID = ? AND doctor_ID = ?
        ORDER BY created_at DESC
        LIMIT 50
      ");
      $hr->execute([$patient_id, $doctor_id]);
    } else {
      $hr = $pdo->prepare("
        SELECT health_record_ID, health_record_description, created_at
        FROM health_record
        WHERE patient_ID = ?
        ORDER BY created_at DESC
        LIMIT 50
      ");
      $hr->execute([$patient_id]);
    }
    $records = $hr->fetchAll();

    $rx = $pdo->prepare("
      SELECT prescription_ID
      FROM prescription
      WHERE patient_ID = ? AND doctor_ID = ?
      ORDER BY prescription_ID DESC
      LIMIT 50
    ");
    $rx->execute([$patient_id, $doctor_id]);
    $rxs = $rx->fetchAll();
  } else {
    $patient_id = 0;
  }
}
?>
<!doctype html>
<html>
<head>
  <?php head_tag("Patient Records — Doctor"); ?>
  <link rel="stylesheet" href="styles.css">
  <style>
    html, body { margin:0; }
    body { padding-top:0 !important; background:#f7f7fb; }

    .wrapper { max-width: 1200px; margin: 0 auto; padding: 16px 20px; }
    .header .hi { font-size:1.4rem; font-weight:700; margin-top:0; }
    .header .sub { color:#6b7280; margin-bottom:12px; }

    .layout { display:grid; grid-template-columns: 320px 1fr; gap:16px; }
    @media (max-width: 1000px){ .layout{ grid-template-columns:1fr; } }

    .panel { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:14px; }
    .panel h3 { margin: 0 0 10px; }

    .search { display:flex; gap:8px; margin-bottom:10px; }
    .input { width:100%; padding:10px 12px; border:1px solid #e5e7eb; border-radius:10px; background:#fff; }

    /* Generic buttons elsewhere on the page (unchanged) */
    .btn { background:#111827; color:#fff; border-radius:10px; padding:10px 14px; text-decoration:none; display:inline-block; border:0; cursor:pointer; transition:.15s ease; }
    .btn:hover{ filter:brightness(0.95); color:#fff; }

    /* Stable black Search button (already used above) */
    .btn-search{
      background:#111827 !important; color:#fff !important; border:0 !important;
      border-radius:10px !important; padding:10px 14px !important; font-weight:700 !important;
      text-decoration:none !important; box-shadow:none !important;
    }
    .btn-search:hover, .btn-search:focus, .btn-search:active{
      background:#111827 !important; color:#fff !important; box-shadow:none !important; outline:none !important;
    }

    /* >>> Stable black “+ New Record” button (no hover color change) */
    .btn-newrecord{
      background:#111827 !important; color:#ffffff !important; border:0 !important;
      border-radius:8px !important; padding:10px 14px !important; font-weight:400 !important;
      text-decoration:none !important; box-shadow:none !important;
    }
    .btn-newrecord:hover, .btn-newrecord:focus, .btn-newrecord:active{
      background:#111827 !important; color:#ffffff !important; box-shadow:none !important; outline:none !important;
    }

    .list { display:flex; flex-direction:column; gap:8px; max-height: 60vh; overflow:auto; }
    .patient-item { display:flex; justify-content:space-between; align-items:center; padding:10px; border:1px solid #e5e7eb; border-radius:10px; background:#fafafa; text-decoration:none; color:#111827; }
    .patient-item.active { background:#eef2ff; border-color:#c7d2fe; }
    .idpill { font-size:12px; color:#6b7280; }

    .patient-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
    .patient-title { font-weight:700; font-size:18px; }
    .patient-meta { color:#6b7280; font-size:14px; }

    .tabs { display:flex; gap:8px; border-bottom:1px solid #e5e7eb; margin-top:8px; }
    .tab-btn { padding:10px 12px; border:0; background:transparent; cursor:pointer; border-bottom:2px solid transparent; }
    .tab-btn.active { border-bottom-color:#111827; font-weight:700; }

    .tab-panel { display:none; padding:12px 0; }
    .tab-panel.active { display:block; }

    .row { display:flex; justify-content:space-between; align-items:center; padding:10px; border:1px solid #e5e7eb; border-radius:10px; background:#fff; margin-bottom:8px; transition:.15s ease; }
    .meta { color:#6b7280; font-size:14px; display:flex; gap:10px; }
    .pill { background:#eef2ff; color:#3730a3; padding:2px 8px; border-radius:999px; font-size:12px; font-weight:700; }

    .rx-link, .hr-link { text-decoration:none; color:inherit; }
    .rx-link .row:hover, .hr-link .row:hover { background:#f9fafb; }

    .empty { color:#6b7280; padding:8px 0; }

    .actions-bar{ display:flex; justify-content:space-between; align-items:center; margin:6px 0 12px; }
    .actions-bar h3{ margin:0; }
    .alert{border-radius:10px;padding:10px 12px;margin:10px 0;}
    .alert.ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;}
    .alert.err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;}
  </style>
</head>
<body>
  <?php include "nav.php"; ?>

  <div class="wrapper">
    <div class="header">
      <div class="hi">Patient Records</div>
      <div class="sub">View your patients, appointments, health records, and prescriptions.</div>
    </div>

    <?php if ($flash_ok): ?><div class="alert ok"><?= e($flash_ok) ?></div><?php endif; ?>
    <?php if ($flash_err): ?><div class="alert err"><?= e($flash_err) ?></div><?php endif; ?>

    <div class="layout">
      <!-- LEFT -->
      <div class="panel">
        <h3>My Patients</h3>
        <form class="search" method="get" action="doctor_patients.php">
          <input type="text" class="input" name="q" value="<?= e($q) ?>" placeholder="Search by name or patient ID…">
          <?php if ($patient_id): ?><input type="hidden" name="patient_id" value="<?= (int)$patient_id ?>"><?php endif; ?>
          <button class="btn btn-search" type="submit">Search</button>
        </form>

        <div class="list">
          <?php if (!$patients): ?>
            <div class="empty">No patients yet.</div>
          <?php else: foreach ($patients as $p):
            $active = ($patient_id === (int)$p['patient_ID']); ?>
            <a class="patient-item <?= $active ? 'active':'' ?>" href="doctor_patients.php?patient_id=<?= (int)$p['patient_ID'] ?>&q=<?= urlencode($q) ?>">
              <div>
                <div><strong><?= e($p['patient_name'] ?: 'Patient') ?></strong></div>
                <div class="idpill">#<?= (int)$p['patient_ID'] ?></div>
              </div>
              <div>›</div>
            </a>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- RIGHT -->
      <div class="panel">
        <?php if (!$patient_id || !$patientInfo): ?>
          <div class="empty">Select a patient on the left to view details.</div>
        <?php else: ?>
          <div class="patient-header">
            <div>
              <div class="patient-title">
                <?= e(safe_col($patientInfo,'patient_name', 'Patient #'.(int)$patientInfo['patient_ID'])) ?>
              </div>
              <div class="patient-meta">
                ID #<?= (int)$patientInfo['patient_ID'] ?>
                <?php
                  $maybeEmail = safe_col($patientInfo,'patient_email','');
                  $maybePhone = safe_col($patientInfo,'patient_phone','');
                  if ($maybeEmail) echo " · ".e($maybeEmail);
                  if ($maybePhone) echo " · ".e($maybePhone);
                ?>
              </div>
            </div>
          </div>

          <!-- Tabs -->
          <div class="tabs">
            <button class="tab-btn active" data-tab="appointments">Appointments</button>
            <button class="tab-btn" data-tab="records">Health Records</button>
            <button class="tab-btn" data-tab="prescriptions">Prescriptions</button>
          </div>

          <!-- Appointments -->
          <div id="tab-appointments" class="tab-panel active">
            <?php if (!$apptHistory): ?>
              <div class="empty">No confirmed/completed appointments yet.</div>
            <?php else: foreach ($apptHistory as $a):
              $date = date('Y-m-d', strtotime($a['start_dt']));
              $time = date('H:i', strtotime($a['start_dt'])) . '–' . date('H:i', strtotime($a['end_dt']));
              $svc  = $a['service_name'] ?: 'Appointment';
            ?>
              <div class="row">
                <div class="left">
                  <div><strong><?= e($svc) ?></strong> <span class="pill"><?= ucfirst($a['status']) ?></span></div>
                  <div class="meta"><span>📅 <?= e($date) ?></span> <span>🕒 <?= e($time) ?></span></div>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>

          <!-- Health Records -->
          <div id="tab-records" class="tab-panel">
            <div class="actions-bar">
              <h3>Health Records</h3>
              <a class="btn btn-newrecord" href="health_record_create.php?patient_id=<?= (int)$patient_id ?>">+ New Record</a>
            </div>

            <?php if (!$records): ?>
              <div class="empty">No health records yet.</div>
            <?php else: foreach ($records as $r): ?>
              <a class="hr-link" href="health_record_view.php?id=<?= (int)$r['health_record_ID'] ?>">
                <div class="row">
                  <div class="left">
                    <div><strong>Record #<?= (int)$r['health_record_ID'] ?></strong></div>
                    <div class="meta"><span>🗓 <?= e(substr($r['created_at'],0,16)) ?></span> <span>Click to preview</span></div>
                    <div><?= nl2br(e($r['health_record_description'] ?? '')) ?></div>
                  </div>
                  <div>›</div>
                </div>
              </a>
            <?php endforeach; endif; ?>
          </div>

          <!-- Prescriptions -->
          <div id="tab-prescriptions" class="tab-panel">
            <?php if (!$rxs): ?>
              <div class="empty">No prescriptions written by you for this patient yet.</div>
            <?php else: foreach ($rxs as $rx): ?>
              <a class="rx-link" href="prescription_view.php?id=<?= (int)$rx['prescription_ID'] ?>">
                <div class="row">
                  <div class="left">
                    <div><strong>Prescription #<?= (int)$rx['prescription_ID'] ?></strong></div>
                    <div class="meta"><span>Click to preview</span></div>
                  </div>
                  <div>›</div>
                </div>
              </a>
            <?php endforeach; endif; ?>
          </div>

        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
      });
    });
  </script>
</body>
</html>