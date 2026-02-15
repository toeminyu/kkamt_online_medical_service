<?php
require_once "db.php";
require_once "session.php";
require_once "helpers.php";

$sent  = false;
$error = "";

/** Tiny in-session rate limit (6 tries / 10 min per IP) — no DB change */
function too_many_requests(): bool {
  $key = 'fp_' . ($_SERVER['REMOTE_ADDR'] ?? 'ip');
  $now = time();
  $_SESSION[$key] = $_SESSION[$key] ?? [];
  // keep only the last 10 mins
  $_SESSION[$key] = array_filter($_SESSION[$key], fn($t)=> ($now - $t) < 600);
  if (count($_SESSION[$key]) >= 6) return true;
  $_SESSION[$key][] = $now;
  return false;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (too_many_requests()) {
    // Always generic — no account enumeration
    $sent = true;
  } else {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = "Please enter a valid email.";
    } else {
      // Always end with generic success
      $sent = true;

      $q = $pdo->prepare("SELECT user_id, email, password_hash, is_active FROM auth_user WHERE email=? LIMIT 1");
      $q->execute([$email]);

      if ($u = $q->fetch()) {
        if ($u['is_active']) {
          // Build stateless token (15 min)
          $token = make_reset_token((int)$u['user_id'], (string)$u['password_hash'], 900);

          // Link
          $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http')
                . '://' . $_SERVER['HTTP_HOST']
                . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
          $link = $base . '/reset_password.php?token=' . urlencode($token);

          // TODO: send $link to $u['email'] via SMTP/Mail API (PHPMailer/SendGrid/etc.)
          // Example (pseudo):
          // send_password_reset_email($u['email'], $link);
        }
      }
    }
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Forgot password</title>
  <style>
    body{background:#f9fafb;font-family:Inter,system-ui,Arial,sans-serif}
    .card{max-width:480px;margin:40px auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;}
    .flash-ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:10px;border-radius:8px;margin:10px 0;}
    .flash-err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:10px;border-radius:8px;margin:10px 0;}
    .input{width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:10px}
    .btn{background:#111827;color:#fff;border:0;border-radius:10px;padding:10px 14px}
    a.link{color:#0f62fe;text-decoration:none}
  </style>
</head>
<body>
  <div class="card">
    <h2>Forgot password</h2>
    <p>Enter your account email and we’ll send you a reset link.</p>

    <?php if($error): ?><div class="flash-err"><?=e($error)?></div><?php endif; ?>
    <?php if($sent): ?>
      <div class="flash-ok">If the email exists, a reset link has been sent. Check your inbox (and spam).</div>
      <p><a class="link" href="login.php">Back to login</a></p>
    <?php else: ?>
      <form method="post">
        <input class="input" type="email" name="email" placeholder="you@example.com" required>
        <div style="margin-top:10px"><button class="btn" type="submit">Send reset link</button></div>
      </form>
      <p style="margin-top:10px"><a class="link" href="login.php">Back to login</a></p>
    <?php endif; ?>
  </div>
</body>
</html>
