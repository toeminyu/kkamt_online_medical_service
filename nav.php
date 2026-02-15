<?php
// nav.php — shared fixed navbar
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'db.php'; // defines $pdo

/* ---- Session ---- */
$userId    = $_SESSION['user_id'] ?? null;
$userEmail = $_SESSION['email']   ?? '';
$userRole  = $_SESSION['role']    ?? '';   // 'patient' | 'doctor'
$userName  = trim($_SESSION['full_name'] ?? ''); // may be empty first time

/* ---- Fill display name from role table once ---- */
if ($userId && $userEmail && $userName === '') {
  try {
    if ($userRole === 'doctor') {
      $st = $pdo->prepare("SELECT doctor_name AS name FROM doctor WHERE user_id=? LIMIT 1");
      $st->execute([$userId]);
      $userName = trim((string)($st->fetch()['name'] ?? ''));
    } elseif ($userRole === 'patient') {
      $st = $pdo->prepare("SELECT patient_name AS name FROM patient WHERE user_id=? LIMIT 1");
      $st->execute([$userId]);
      $userName = trim((string)($st->fetch()['name'] ?? ''));
    }
    if ($userName !== '') { $_SESSION['full_name'] = $userName; }
  } catch (Throwable $e) { /* fallback handled below */ }
}

/* ---- Helpers ---- */
function derive_display_name(?string $name, ?string $email): string {
  $name = trim((string)$name);
  if ($name !== '') return $name;
  if ($email) { $local = explode('@', $email, 2)[0] ?? ''; return $local !== '' ? $local : 'User'; }
  return 'User';
}
function make_initials(string $displayName): string {
  $displayName = trim(preg_replace('/\s+/', ' ', $displayName));
  if ($displayName === '') return 'U';
  $parts = explode(' ', $displayName);
  if (count($parts) >= 2) {
    $f = mb_substr($parts[0], 0, 1, 'UTF-8'); $l = mb_substr(end($parts), 0, 1, 'UTF-8');
    return strtoupper($f.$l);
  }
  return strtoupper(mb_substr($displayName, 0, 2, 'UTF-8'));
}
/* ---- Display + Links ---- */
$displayName   = derive_display_name($userName, $userEmail);
$initials      = make_initials($displayName);
$homeHref      = 'home.php';
$patientDash   = 'patient.php';
$doctorDash    = 'doctor.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .topbar { position:fixed; top:0; left:0; right:0; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
      box-shadow:0 2px 10px rgba(0,0,0,.1); z-index:1000; padding:10px 40px; height:85px;
      display:flex; align-items:center; justify-content:space-between; }
    .nav-left { display:flex; align-items:center; gap:15px; }
    .logo-circle { width:70px; height:70px; border-radius:50%; display:flex; align-items:center; justify-content:center; }
    .logo-circle img { width:70px; height:70px; object-fit:cover; border-radius:50%; }
    .brand-text { color:#fff; font-size:20px; font-weight:bold; white-space:nowrap; margin-right:20px; }
    .nav-links { display:flex; gap:25px; align-items:center; }
    .nav-links a { color:#fff; text-decoration:none; font-size:16px; font-weight:500; padding:8px 15px; border-radius:5px; }
    .nav-links a:hover { background:rgba(255,255,255,.15); transform:translateY(-2px); }
    .nav-right { display:flex; align-items:center; gap:12px; }
    .user-chip { display:inline-flex; align-items:center; gap:10px; padding:8px 12px; background:rgba(255,255,255,.2);
      border-radius:28px; color:#fff; text-decoration:none; transition:.25s; font-weight:600; }
    .user-chip:hover { background:rgba(255,255,255,.3); transform:translateY(-1px); }
    .avatar-initials { width:34px; height:34px; border-radius:50%; background:#09c4ff; display:inline-flex;
      align-items:center; justify-content:center; font-weight:800; letter-spacing:.5px; }
    .user-name { font-weight:700; }
    .btn { padding:10px 18px; background:#fff; color:#667eea; text-decoration:none; border-radius:25px;
      font-weight:600; transition:.2s; border:2px solid #fff; font-size:14px; }
    .btn:hover { background:transparent; color:#fff; transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,.2); }
    .btn-outline { background:transparent; color:#fff; border-color:#fff; }
    .btn-outline:hover { background:#fff; color:#667eea; }
    .topbar-spacer { height:85px; }
    @media (max-width:768px){ .brand-text{font-size:14px; margin-right:10px;} .nav-links{gap:10px;}
      .nav-links a{font-size:14px; padding:6px 10px;} .btn{padding:8px 14px; font-size:13px;}
      .logo-circle img{width:60px; height:60px;} .topbar-spacer{height:75px;} }
  </style>
</head>
<body>
  <nav class="topbar">
    <div class="nav-left">
      <a href="<?= htmlspecialchars($homeHref) ?>" class="logo-circle" title="Online Medical Service">
        <img src="image/logo.jpg" alt="Logo">
      </a>
      <span class="brand-text">Online Medical Service</span>

      <div class="nav-links">
        <?php if (!$userId): ?>
          <!-- Guest -->
          <a href="<?= htmlspecialchars($homeHref) ?>">Home</a>
          <a href="service.php">Services</a>
          <a href="doctorlist.php">Doctor</a>
          <a href="aboutus.php">About Us</a>
        <?php elseif ($userRole === 'patient'): ?>
          <!-- Patient -->
          <a href="<?= htmlspecialchars($homeHref) ?>">Home</a>
          <a href="service.php">Services</a>
          <a href="doctorlist.php">Doctor</a>
          <a href="<?= htmlspecialchars($patientDash) ?>">Patient Dashboard</a>
        <?php elseif ($userRole === 'doctor'): ?>
          <!-- Doctor -->
          <a href="<?= htmlspecialchars($doctorDash) ?>">Doctor Dashboard</a>
        <?php else: ?>
          <!-- Fallback for other roles (optional) -->
          <a href="<?= htmlspecialchars($homeHref) ?>">Home</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="nav-right">
      <?php if (!$userId): ?>
        <a class="btn btn-outline" href="login.php">Log in</a>
        <a class="btn" href="patient_register.php">Sign up</a>
      <?php else: ?>
        <a href="<?= htmlspecialchars($profileHref) ?>" class="user-chip" title="Profile / Edit">
          <span class="user-name"><?= htmlspecialchars($displayName) ?></span>
          <span class="avatar-initials"><?= htmlspecialchars($initials) ?></span>
        </a>
        <a class="btn btn-outline" href="logout.php">Logout</a>
      <?php endif; ?>
    </div>
  </nav>

  <div class="topbar-spacer" aria-hidden="true"></div>
</body>
</html>
