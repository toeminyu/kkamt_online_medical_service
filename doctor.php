<?php
require_once "session.php";
require_once "db.php";
require_once "helpers.php";
require_login();
if (($_SESSION['role'] ?? '') !== 'doctor') redirect_role($_SESSION['role']);

/* Current doctor */
$dStmt = $pdo->prepare("SELECT doctor_ID, doctor_name FROM doctor WHERE user_id = ? LIMIT 1");
$dStmt->execute([$_SESSION['user_id']]);
$doctor = $dStmt->fetch();
$doctor_id   = (int)($doctor['doctor_ID'] ?? 0);
$displayName = $doctor['doctor_name'] ?? ($_SESSION['email'] ?? 'Doctor');
if (!$doctor_id) { header("Location: login.php"); exit; }

/* Flash (from doctor_claim.php) */
$flash_ok   = $_SESSION['flash_success'] ?? "";
$flash_err  = $_SESSION['flash_error']   ?? "";
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

/* Helper */
function preview_text($txt, $max = 80){
  $t = trim((string)$txt);
  if ($t === '') return '';
  return (mb_strlen($t,'UTF-8') > $max) ? (mb_substr($t,0,$max,'UTF-8').'…') : $t;
}

/* Pending requests (future only) */
$pendingStmt = $pdo->prepare("
  SELECT a.appointment_id, a.status, a.notes, a.preferred_language,
         s.start_dt, s.end_dt,
         p.patient_name, sv.service_name
  FROM appointment a
  JOIN schedule_slot s ON s.slot_id = a.slot_id
  JOIN patient p       ON p.patient_ID = a.patient_id
  LEFT JOIN service sv ON sv.service_ID = a.service_id
  WHERE s.doctor_id = ? AND a.status = 'pending' AND s.start_dt >= NOW()
  ORDER BY s.start_dt ASC
");
$pendingStmt->execute([$doctor_id]);
$pendings = $pendingStmt->fetchAll();

/* Today's confirmed */
$todayStmt = $pdo->prepare("
  SELECT a.appointment_id, a.status, a.notes, a.preferred_language,
         s.start_dt, s.end_dt,
         p.patient_name, sv.service_name
  FROM appointment a
  JOIN schedule_slot s ON s.slot_id = a.slot_id
  JOIN patient p       ON p.patient_ID = a.patient_id
  LEFT JOIN service sv ON sv.service_ID = a.service_id
  WHERE s.doctor_id = ? AND a.status = 'confirmed' AND DATE(s.start_dt)=CURDATE()
  ORDER BY s.start_dt ASC
");
$todayStmt->execute([$doctor_id]);
$appointmentsToday = $todayStmt->fetchAll();

/* Recent history */
$histStmt = $pdo->prepare("
  SELECT a.appointment_id, a.status, a.notes, a.preferred_language,
         s.start_dt, p.patient_name, sv.service_name
  FROM appointment a
  JOIN schedule_slot s ON s.slot_id = a.slot_id
  JOIN patient p       ON p.patient_ID = a.patient_id
  LEFT JOIN service sv ON sv.service_ID = a.service_id
  WHERE s.doctor_id = ? AND s.start_dt < NOW()
  ORDER BY s.start_dt DESC
  LIMIT 6
");
$histStmt->execute([$doctor_id]);
$history = $histStmt->fetchAll();
?>
<!doctype html>
<html>
<head>
  <?php head_tag("Doctor Dashboard"); ?>
  <style>
    body { background:#f7f7fb; margin:0; }
    .wrapper { max-width:1100px; margin:auto; padding:20px; }
    .header .hi { font-size:1.4rem; font-weight:700; }
    .header .sub { color:#6b7280; margin-bottom:12px; }

    .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    @media (max-width:900px){ .grid-2{ grid-template-columns:1fr; } }

    .list .item { display:flex; justify-content:space-between; align-items:flex-start; background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:12px; margin-bottom:10px; }
    .meta { color:#6b7280; font-size:14px; margin-top:4px; }
    .badge { background:#eef2ff; color:#3730a3; border-radius:999px; padding:2px 8px; font-size:12px; font-weight:700; margin-left:6px; }
    .btn-sm { background:#111827; color:#fff; border-radius:8px; padding:6px 10px; text-decoration:none; font-size:14px; border:0; cursor:pointer; }
    .btn-red { background:#991b1b; }
    .flash-ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:10px;border-radius:8px;margin-bottom:10px;}
    .flash-err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:10px;border-radius:8px;margin-bottom:10px;}

    /* Modal */
    .backdrop{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:9999;}
    .modal{background:#fff;border-radius:12px;border:1px solid #e5e7eb;max-width:520px;width:92%;padding:18px;box-shadow:0 10px 30px rgba(0,0,0,.15);}
    .modal h3{margin:0 0 8px;}
    .modal .msg{color:#374151;margin-bottom:14px;}
    .row-right{display:flex;gap:10px;justify-content:flex-end;}
    .btn-light{background:#e5e7eb;color:#111827;border:0;border-radius:8px;padding:8px 12px;cursor:pointer;}
    /* Quick Actions Grid */
.quick-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
  margin: 30px 0;
}

.quick-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 24px 20px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  transition: all 0.25s ease;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.quick-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 20px rgba(102, 126, 234, 0.25);
}

/* Headings & text */
.quick-card h4 {
  font-size: 1.1rem;
  font-weight: 700;
  color: #111827;
  margin-bottom: 8px;
}

.quick-card p {
  color: #6b7280;
  font-size: 0.95rem;
  line-height: 1.5;
  flex-grow: 1;
  margin-bottom: 16px;
}

/* Button inside card */
.quick-card .btn {
  background: #111827;          /* black button */
  color: #fff;
  border-radius: 10px;
  padding: 8px 14px;
  font-size: 14px;
  text-decoration: none;
  text-align: center;
  transition: background 0.2s ease;
}

.quick-card .btn:hover {
  background: #374151;          /* dark gray hover */
  color: #fff;
}

/* Responsive */
@media (max-width: 950px) {
  .quick-grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 600px) {
  .quick-grid {
    grid-template-columns: 1fr;
  }
}

  </style>
</head>
<body>
  <?php include "nav.php"; ?>

  <div class="wrapper">
    <?php if ($flash_ok): ?><div class="flash-ok"><?= e($flash_ok) ?></div><?php endif; ?>
    <?php if ($flash_err): ?><div class="flash-err"><?= e($flash_err) ?></div><?php endif; ?>

    <div class="header">
      <div class="hi">Welcome back, Dr. <?= e($displayName) ?></div>
      <div class="sub">Manage your pending requests and today’s schedule.</div>
    </div>

    <div class="quick-grid">
      <div class="quick-card">
        <h4>📅 Manage Shifts</h4>
        <p>Update your availability and working hours.</p>
        <a class="btn" href="shift_manage.php">Manage Shifts</a>
      </div>
      <div class="quick-card">
        <h4>📝 Write Prescription</h4>
        <p>Create and send prescriptions to your patients.</p>
        <a class="btn" href="prescription_list.php">New Prescription</a>
      </div>
      <div class="quick-card">
        <h4>📖 Patient Records</h4>
        <p>Review medical records you’ve written.</p>
        <a class="btn" href="doctor_patients.php">View Records</a>
      </div>
    </div>

    <div class="grid-2">
      <!-- Pending -->
      <div class="section">
        <h3>Pending Requests</h3>
        <div class="list">
          <?php if (!$pendings): ?>
            <div class="item"><strong>No pending requests</strong></div>
          <?php else: foreach ($pendings as $a): ?>
            <div class="item">
              <div>
                <div><strong><?= e($a['patient_name']) ?></strong><span class="badge"><?= e($a['status']) ?></span></div>
                <div class="meta">🕒 <?= date('H:i', strtotime($a['start_dt'])) ?> - <?= date('H:i', strtotime($a['end_dt'])) ?></div>
                <div class="meta">🧾 <?= e($a['service_name'] ?: 'General') ?> | 🗣️ <?= e($a['preferred_language'] ?: '-') ?></div>
                <?php if ($a['notes']): ?><div class="meta">📝 <?= e(preview_text($a['notes'])) ?></div><?php endif; ?>
              </div>
              <div>
                <!-- Accept -->
                <form class="act-form" action="doctor_claim.php" method="POST" style="display:inline;">
                  <input type="hidden" name="appointment_id" value="<?= (int)$a['appointment_id'] ?>">
                  <input type="hidden" name="action" value="accept">
                  <button class="btn-sm" type="submit">Accept</button>
                </form>
                <!-- Decline -->
                <form class="act-form" action="doctor_claim.php" method="POST" style="display:inline;">
                  <input type="hidden" name="appointment_id" value="<?= (int)$a['appointment_id'] ?>">
                  <input type="hidden" name="action" value="decline">
                  <button class="btn-sm btn-red" type="submit">Decline</button>
                </form>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- Today -->
      <div class="section">
        <h3>Today's Appointments</h3>
        <div class="list">
          <?php if (!$appointmentsToday): ?>
            <div class="item"><strong>No appointments today</strong></div>
          <?php else: foreach ($appointmentsToday as $a): ?>
            <div class="item">
              <div><strong><?= e($a['patient_name']) ?></strong><span class="badge"><?= e($a['status']) ?></span></div>
              <div class="meta">🕒 <?= date('H:i', strtotime($a['start_dt'])) ?> - <?= date('H:i', strtotime($a['end_dt'])) ?></div>
              <a class="btn-sm" href="appointment_view.php?id=<?= (int)$a['appointment_id'] ?>">Details</a>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <!-- History -->
    <div class="section" style="margin-top:16px">
      <h3>Recent History</h3>
      <div class="list">
        <?php if (!$history): ?>
          <div class="item"><strong>No history yet</strong></div>
        <?php else: foreach ($history as $h): ?>
          <div class="item">
            <div><strong><?= e($h['patient_name']) ?></strong> <span class="badge"><?= e($h['status']) ?></span></div>
            <div class="meta">📅 <?= date('Y-m-d', strtotime($h['start_dt'])) ?> · 🕒 <?= date('H:i', strtotime($h['start_dt'])) ?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <!-- Accept Modal (single, reused) -->
  <div id="acceptBackdrop" class="backdrop" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="acceptTitle">
      <h3 id="acceptTitle">Confirm Appointment Acceptance</h3>
      <p class="msg">Do you want to confirm this appointment and send notifications?</p>
      <div class="row-right">
        <button type="button" class="btn-light" id="btnCancelAccept">Cancel</button>
        <button type="button" class="btn-sm" id="btnConfirmAccept">Confirm & Send</button>
      </div>
      <input type="hidden" id="accept_appointment_id" />
    </div>
  </div>

  <script>
    // === CONFIG ===
    const WEBHOOK_URL = "http://localhost:5678/webhook-test/creatingappointment";

    // === EVENT HANDLING (Accept/Decline) ===
    document.querySelectorAll('.act-form').forEach(form => {
      form.addEventListener('submit', (e) => {
        const action = form.querySelector('input[name="action"]').value;
        const apptId = form.querySelector('input[name="appointment_id"]').value;

        if (action === 'accept') {
          // intercept and open modal
          e.preventDefault();
          showAcceptModal(apptId);
        } else {
          // decline flow: confirm then proceed to doctor_claim.php
          if (!confirm('Decline this appointment?')) e.preventDefault();
        }
      });
    });

    // === MODAL ===
    const backdrop = document.getElementById('acceptBackdrop');
    const btnCancel = document.getElementById('btnCancelAccept');
    const btnConfirm = document.getElementById('btnConfirmAccept');

    function showAcceptModal(appointmentId){
      document.getElementById('accept_appointment_id').value = appointmentId;
      backdrop.style.display = 'flex';
      backdrop.setAttribute('aria-hidden', 'false');
    }
    function hideAcceptModal(){
      backdrop.style.display = 'none';
      backdrop.setAttribute('aria-hidden', 'true');
    }
    btnCancel.addEventListener('click', hideAcceptModal);

    // === MAIN: Confirm & Send ===
    btnConfirm.addEventListener('click', async () => {
      const apptId = document.getElementById('accept_appointment_id').value;

      // 1) Fire n8n webhook silently (do not navigate)
      try {
        const payload = new URLSearchParams({ appointment_id: apptId });
        await fetch(WEBHOOK_URL, { method: "POST", body: payload });
      } catch (err) {
        console.error("n8n webhook error:", err);
        // continue anyway; DB confirmation still happens
      }

      // 2) Programmatically post to doctor_claim.php (server updates DB + redirects back)
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = 'doctor_claim.php';

      const fAction = document.createElement('input');
      fAction.type = 'hidden';
      fAction.name = 'action';
      fAction.value = 'accept';

      const fId = document.createElement('input');
      fId.type = 'hidden';
      fId.name = 'appointment_id';
      fId.value = apptId;

      form.appendChild(fAction);
      form.appendChild(fId);
      document.body.appendChild(form);

      hideAcceptModal();
      form.submit();
    });
  </script>
</body>
</html>
