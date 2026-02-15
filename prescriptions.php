<?php
require_once "session.php";
require_once "db.php";
require_once "helpers.php";
date_default_timezone_set('Asia/Bangkok');

require_login();
if (($_SESSION['role'] ?? '') !== 'patient') redirect_role($_SESSION['role']);

$ps = $pdo->prepare("SELECT patient_ID, patient_name FROM patient WHERE user_id=? LIMIT 1");
$ps->execute([$_SESSION['user_id']]);
$patient = $ps->fetch();
$patient_id = (int)($patient['patient_ID'] ?? 0);
if (!$patient_id) { header("Location: login.php"); exit; }

$pres = $pdo->prepare("
  SELECT p.prescription_ID, p.prescription_date, p.prescription_description, 
         d.doctor_name
  FROM prescription p
  JOIN doctor d ON d.doctor_ID = p.doctor_ID
  WHERE p.patient_ID = ?
  ORDER BY p.prescription_date DESC
");
$pres->execute([$patient_id]);
$list = $pres->fetchAll();
?>
<!doctype html>
<html>
<head><?php head_tag("My Prescriptions"); ?>
<style>
body { background:#f7f7fb; padding:20px; }
.wrapper { max-width:900px; margin:auto; }
.list .item { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:14px; margin-bottom:10px; }
.meta { color:#6b7280; font-size:14px; margin-top:4px; }
.btn-sm{ background:#111827; color:#fff; border-radius:8px; padding:6px 10px; text-decoration:none; font-size:14px; }
</style>
</head>
<body>
<?php include "nav.php"; ?>
<div class="wrapper">
  <h2>My Prescriptions</h2>
  <p class="sub">All prescriptions issued by your doctors</p>
  <div class="list">
  <?php if (!$list): ?>
    <div class="item"><strong>No prescriptions found.</strong></div>
  <?php else: foreach($list as $p): ?>
    <div class="item">
      <div><strong><?= e($p['prescription_description'] ?: 'Prescription') ?></strong></div>
      <div class="meta">Doctor: <?= e($p['doctor_name']) ?> | Date: <?= e(substr($p['prescription_date'],0,10)) ?></div>
      <div style="margin-top:8px;">
        <a class="btn-sm" href="patient_view_prescriptions.php?id=<?= (int)$p['prescription_ID'] ?>">View</a>
      </div>
    </div>
  <?php endforeach; endif; ?>
  </div>
</div>
</body>
</html>
