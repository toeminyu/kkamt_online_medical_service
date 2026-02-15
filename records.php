<?php
require_once "session.php";
require_once "db.php";
require_once "helpers.php";
require_login();

if (($_SESSION['role'] ?? '') !== 'patient') redirect_role($_SESSION['role']);

/* Current patient */
$pStmt = $pdo->prepare("SELECT patient_ID, patient_name FROM patient WHERE user_id=? LIMIT 1");
$pStmt->execute([$_SESSION['user_id']]);
$patient = $pStmt->fetch();
$patient_id = (int)($patient['patient_ID'] ?? 0);
if (!$patient_id) { header("Location: login.php"); exit; }

$q = trim($_GET['q'] ?? '');
$params = [$patient_id];

$sql = "
  SELECT hr.health_record_ID, hr.health_record_description, hr.created_at,
         d.doctor_name, s.start_dt
  FROM health_record hr
  LEFT JOIN appointment a ON a.appointment_id = hr.appointment_ID
  LEFT JOIN schedule_slot s ON s.slot_id = a.slot_id
  LEFT JOIN doctor d ON d.doctor_ID = s.doctor_id
  WHERE hr.patient_ID = ?
";
if ($q !== '') {
  $sql .= " AND (hr.health_record_description LIKE ? OR d.doctor_name LIKE ?) ";
  $like = "%{$q}%";
  $params[] = $like; 
  $params[] = $like;
}
$sql .= " ORDER BY hr.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
<?php head_tag("My Medical Records"); ?>
<style>
html, body { margin:0; }
body { background:#f7f7fb; padding-top:0 !important; font-family:'Segoe UI', sans-serif; }

.wrapper { max-width:1100px; margin:0 auto; padding:20px; }
.section { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; }
h2 { margin:0 0 8px; }

.search { display:flex; gap:8px; margin:10px 0 14px; }
.input { width:100%; padding:10px 12px; border:1px solid #e5e7eb; border-radius:10px; }

/* ---------- Stable Black Buttons (Back & Search) ---------- */
.btn-stable {
  background:#111827 !important;
  color:#ffffff !important;
  border:0 !important;
  border-radius:10px !important;
  padding:10px 14px !important;
  font-weight:300 !important;
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
  box-shadow:none !important;
  outline:none !important;
}

/* ---------- Default Buttons (like View) ---------- */
.btn {
  background:#111827; 
  color:#fff; 
  border-radius:10px; 
  padding:10px 14px; 
  text-decoration:none; 
  display:inline-block; 
  border:0; 
  cursor:pointer; 
}

/* ---------- Layout ---------- */
.list { display:flex; flex-direction:column; gap:10px; }
.item { display:flex; justify-content:space-between; align-items:flex-start; padding:12px; border:1px solid #e5e7eb; border-radius:10px; background:#fff; }
.left { display:flex; flex-direction:column; gap:6px; }
.meta { color:#6b7280; font-size:14px; display:flex; gap:12px; flex-wrap:wrap; }
.empty { color:#6b7280; padding:8px; }
</style>
</head>
<body>
<?php include "nav.php"; ?>

<div class="wrapper">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
    <h2>My Medical Records</h2>
    <!-- Back to dashboard button -->
    <a class="btn-stable" href="patient.php">Back to dashboard</a>
  </div>

  <div class="section">
    <form class="search" method="get" action="records.php">
      <input class="input" type="text" name="q" value="<?= e($q) ?>" placeholder="Search by doctor or keywords…">
      <!-- Search button -->
      <button class="btn-stable" type="submit">Search</button>
    </form>

    <div class="list">
      <?php if (!$records): ?>
        <div class="empty">No records found.</div>
      <?php else: foreach ($records as $r):
        $snippet = trim(mb_strimwidth((string)($r['health_record_description'] ?? ''), 0, 140, '…', 'UTF-8'));
        $created = $r['created_at'] ? substr($r['created_at'],0,16) : '';
        $svcTime = $r['start_dt'] ? substr($r['start_dt'],0,16) : '';
      ?>
        <div class="item">
          <div class="left">
            <div><strong><?= e($r['doctor_name'] ?: 'Doctor') ?></strong></div>
            <div class="meta">
              <?php if ($created): ?><span>🗓 Created: <?= e($created) ?></span><?php endif; ?>
              <?php if ($svcTime): ?><span>🕒 Visit: <?= e($svcTime) ?></span><?php endif; ?>
            </div>
            <div><?= nl2br(e($snippet)) ?></div>
          </div>
          <div>
            <a class="btn" href="patient_view_records.php?id=<?= (int)$r['health_record_ID'] ?>">View</a>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
</body>
</html>