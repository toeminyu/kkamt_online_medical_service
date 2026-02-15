<?php
require_once "session.php";
require_once "db.php";
require_once "helpers.php";
date_default_timezone_set('Asia/Bangkok');

require_login();
if (($_SESSION['role'] ?? '') !== 'patient') redirect_role($_SESSION['role']);

// Get patient info
$ps = $pdo->prepare("SELECT patient_ID, patient_name FROM patient WHERE user_id=? LIMIT 1");
$ps->execute([$_SESSION['user_id']]);
$patient = $ps->fetch();
$patient_id = (int)($patient['patient_ID'] ?? 0);
if (!$patient_id) { header("Location: login.php"); exit; }
$displayName = $patient['patient_name'] ?? ($_SESSION['email'] ?? 'Patient');

// Flash
$flash = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

// Today's appointments
$as = $pdo->prepare("
  SELECT a.appointment_id, a.status, a.notes,
         s.start_dt, s.end_dt,
         d.doctor_ID AS doc_id, d.doctor_name
  FROM appointment a
  JOIN schedule_slot s ON s.slot_id = a.slot_id
  JOIN doctor d ON d.doctor_ID = s.doctor_id
  WHERE a.patient_id = ?
    AND DATE(a.requested_at) = CURDATE()
    AND a.status IN ('pending','confirmed','declined')
  ORDER BY s.start_dt ASC
  LIMIT 10
");
$as->execute([$patient_id]);
$appts = $as->fetchAll();

// Summary metrics
$rxStmt = $pdo->prepare("SELECT COUNT(*) FROM prescription WHERE patient_ID = ?");
$rxStmt->execute([$patient_id]);
$rxCount = (int)$rxStmt->fetchColumn();

$recStmt = $pdo->prepare("SELECT COUNT(*) FROM health_record WHERE patient_ID = ?");
$recStmt->execute([$patient_id]);
$recCount = (int)$recStmt->fetchColumn();

function badgeStyle($st){
  if ($st==='declined')   return "background:#fee2e2;color:#991b1b;";
  if ($st==='pending')    return "background:#fef3c7;color:#92400e;";
  if ($st==='confirmed')  return "background:#dcfce7;color:#065f46;";
  return "background:#e5e7eb;color:#374151;";
}
?>
<!doctype html>
<html>
<head>
<?php head_tag("Patient Dashboard"); ?>
<link rel="stylesheet" href="styles.css?v=1">
<style>
/* Remove extra gap — nav.php already handles top spacing */
html, body { margin:0; }
body { background:#f7f7fb; padding-top:0 !important; font-family:'Segoe UI', Arial, sans-serif; }

.wrapper { max-width: 1150px; margin: 0 auto; padding: 16px 20px; }
.flash { background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:10px 12px; border-radius:10px; margin-bottom:12px; }

.header { display:flex; align-items:end; justify-content:space-between; gap:16px; }
.hi  { font-size:22px; font-weight:700; margin:0; }
.sub { color:#6b7280; margin:0; }

/* Quick actions */
.quick-grid {
  display:grid; grid-template-columns: repeat(auto-fit, minmax(260px,1fr));
  gap:16px; margin:30px 0 20px;
}
.quick-card{
  background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:18px;
  box-shadow:0 2px 6px rgba(0,0,0,.05); transition:transform .18s, box-shadow .18s;
}
.quick-card:hover { transform:translateY(-3px); box-shadow:0 8px 16px rgba(0,0,0,.08); }
.btn, .btn-sm { background:#111827; color:#fff; border-radius:10px; padding:10px 14px; text-decoration:none; display:inline-block; margin: 10px; }
.btn-sm { padding:6px 10px; font-size:14px; }

/* 2-column layout */
.grid-2 { display:grid; grid-template-columns:1.1fr .9fr; gap:20px; }
@media (max-width: 980px){ .grid-2 { grid-template-columns:1fr; } }

.section {
  background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:20px;
  box-shadow:0 2px 6px rgba(0,0,0,.05);
}
.section h3{ margin:0 0 4px; }
.section .subtle { color:#6b7280; margin-bottom:10px; }

.list .item {
  display:flex; justify-content:space-between; align-items:center;
  padding:10px; border:1px solid #e5e7eb; border-radius:10px; background:#fff; margin-bottom:10px;
}
.list .meta { color:#6b7280; font-size:14px; display:flex; gap:10px; flex-wrap:wrap; }
.badge { padding:3px 8px; border-radius:999px; font-size:12px; font-weight:700; margin-left:6px;
}

/* Overview cards (plain colors) */
.overview {
  display:grid; grid-template-columns:repeat(auto-fit, minmax(220px,1fr)); gap:14px; margin-bottom:16px;
}
.kpi {
  background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; padding:16px;
  display:flex; align-items:center; justify-content:space-between; gap:10px;
  box-shadow:0 2px 6px rgba(0,0,0,.04);
}
.kpi .icon { font-size:22px; }
.kpi .num  { font-size:26px; font-weight:800; color:#111827; }
.kpi .lbl  { color:#6b7280; font-size:13px; }

/* === Medication Coach (UX Polished) === */
.coach {
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  background: #4338ca;
  padding: 20px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}


.coach h3 {
  margin: 0 0 10px;
  font-size: 1.2rem;
  font-weight: 700;
  color: #ebecedff;
}

/* Accordion styling */
.accordion {
  border-top: 1px dashed #d1d5db;
  margin-top: 12px;
}
.acc-item {
  border-bottom: 1px dashed #e5e7eb;
}
.acc-btn {
  width: 100%;
  text-align: left;
  border: 0;
  cursor: pointer;
  padding: 14px 0;
  font-weight: 600;
  display: flex;
  background: transparent;
  justify-content: space-between;
  align-items: center;
  color: #ebeff3ff;
  transition: color 0.2s ease;
}

.acc-btn .chev {
  font-size: 14px;
  transition: transform 0.18s ease;
}
.acc-btn.active .chev {
  transform: rotate(90deg);
}
.acc-panel {
  display: none;
  padding: 8px 0 14px 0;
  color: #e8eaedff;
  line-height: 1.6;
}

/* Checklist UX */
.checklist {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
  margin-top: 10px;
}
.tick {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #f9fafb;
  transition: background 0.2s ease, transform 0.1s ease;
}
.tick:hover {
  background: #eef2ff;
  transform: scale(1.01);
}
.tick input {
  width: 18px;
  height: 18px;
  accent-color: #667eea;
}
.small {
  color: #6b7280;
  font-size: 13px;
}

/* Quote card */
.quote {
  margin-top: 16px;
  background: #eef2ff;
  color: #4338ca;
  border-radius: 12px;
  padding: 14px;
  text-align: center;
  font-style: italic;
  box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
}
.click_btn{
  background: black;
  color:white;
  border-radius:10px; padding:10px 14px; text-decoration:none; display:inline-block; margin: 10px;
}


</style>
</head>
<body>
<?php include "nav.php"; ?>

<div class="wrapper">
  <?php if ($flash): ?><div class="flash"><?= e($flash) ?></div><?php endif; ?>

  <div class="header">
    <h1 class="hi">👋 Welcome back, <?= e($displayName) ?></h1>
    <p class="sub">Here’s your personal health snapshot and today’s activities.</p>
  </div>

  <!-- Quick actions -->
  <div class="quick-grid">
    <div class="quick-card">
      <h4>📅 Book Appointment</h4>
      <p>Find an available doctor and schedule a consultation instantly.</p>
      <a class="click_btn" href="book.php">Book Now</a>
    </div>
    <div class="quick-card">
      <h4>🩺 Health Records</h4>
      <p>Browse your doctor’s notes and past consultations securely.</p>
      <a class="click_btn" href="records.php">View Records</a>
    </div>
    <div class="quick-card">
      <h4>💊 Prescriptions</h4>
      <p>Check your medication details and dosage reminders.</p>
      <a class="click_btn" href="prescriptions.php">View Prescriptions</a>
    </div>
  </div>

  <div class="grid-2">
    <!-- Left: Today's appointments -->
    <div class="section">
      <h3>Today’s Appointments</h3>
      <div class="subtle">Your sessions created today</div>
      <div class="list">
        <?php if (!$appts): ?>
          <div class="item"><div><strong>No appointments yet today</strong><div class="subtle">Book a slot to get started.</div></div></div>
        <?php else: foreach ($appts as $a):
          $status = $a['status'];
          $label  = $status==='pending' ? 'Awaiting doctor confirmation' : ($status==='declined' ? 'Declined' : $a['doctor_name']);
          $startH = date('H:i', strtotime($a['start_dt']));
          $endH   = date('H:i', strtotime($a['end_dt']));
        ?>
          <div class="item">
            <div>
              <strong><?= e($label) ?></strong>
              <span class="badge" style="<?= badgeStyle($status) ?>"><?= ucfirst($status) ?></span>
              <div class="list meta"><span>🕒 <?= $startH ?> - <?= $endH ?></span></div>
            </div>
            <div>
              <?php if ($status==='declined'): ?>
                <a class="btn-sm" href="book.php?start=<?= e(date('H:i:s', strtotime($a['start_dt']))) ?>">Rebook</a>
              
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Right: Overview + Interactive Coach -->
    <div>
      <div class="overview">
        <div class="kpi">
          <div>
            <div class="lbl">Total Records</div>
            <div class="num"><?= $recCount ?></div>
          </div>
          <div class="icon">🗂</div>
        </div>
        <div class="kpi">
          <div>
            <div class="lbl">Prescriptions</div>
            <div class="num"><?= $rxCount ?></div>
          </div>
          <div class="icon">💊</div>
        </div>
      </div>

      <div class="coach">
        <h3>Medication Coach</h3>

        <div class="accordion">
          <div class="acc-item">
            <button class="acc-btn" type="button"><span>How to take medicines safely</span><span class="chev">›</span></button>
            <div class="acc-panel">
              <ul style="margin:6px 0 0 18px;">
                <li>Always follow the label and your doctor’s instructions.</li>
                <li>Don’t stop antibiotics early, even if you feel better.</li>
                <li>Use a pill organizer or phone alarms to avoid missing a dose.</li>
                <li>Never mix alcohol with medicines unless your doctor says it’s safe.</li>
              </ul>
            </div>
          </div>
          <div class="acc-item">
            <button class="acc-btn" type="button"><span>When to contact your doctor</span><span class="chev">›</span></button>
            <div class="acc-panel">
              <ul style="margin:6px 0 0 18px;">
                <li>New rashes, swelling, trouble breathing, or severe dizziness.</li>
                <li>Pain getting worse or not improving after a few days.</li>
                <li>Unexpected side effects or interactions with other meds.</li>
              </ul>
            </div>
          </div>
          <div class="acc-item">
            <button class="acc-btn" type="button"><span>Storage & organization</span><span class="chev">›</span></button>
            <div class="acc-panel">
              <ul style="margin:6px 0 0 18px;">
                <li>Keep medicines in a cool, dry place away from sunlight.</li>
                <li>Store away from children and pets; use child-proof caps.</li>
                <li>Regularly dispose of expired medicines safely.</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="checklist" id="coachChecklist">
          <label class="tick"><input type="checkbox" data-key="list_meds"> I keep an updated list of my medicines and allergies.</label>
          <label class="tick"><input type="checkbox" data-key="alarms"> I set reminders/alarms for my doses.</label>
          <label class="tick"><input type="checkbox" data-key="storage"> I store medicines correctly and check expiry dates.</label>
        </div>

        <div class="quote">“Small healthy habits every day add up to big results.”</div>
      </div>
    </div>
  </div>
</div>

<script>
// Accordion behavior
document.querySelectorAll('.acc-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    btn.classList.toggle('active');
    const panel = btn.nextElementSibling;
    panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
  });
});

// Checklist with localStorage
const KEY_PREFIX = 'medcoach_';
const boxContainer = document.getElementById('coachChecklist');
if (boxContainer) {
  boxContainer.querySelectorAll('input[type="checkbox"]').forEach(cb => {
    const key = KEY_PREFIX + cb.dataset.key;
    // load saved
    try { cb.checked = localStorage.getItem(key) === '1'; } catch(e){}
    // save on toggle
    cb.addEventListener('change', () => {
      try { localStorage.setItem(key, cb.checked ? '1' : '0'); } catch(e){}
    });
  });
}
</script>
</body>
</html>