<?php
require_once "db.php";
require_once "session.php";
require_once "helpers.php";

set_no_cache_headers();

$token   = trim($_GET['token'] ?? '');
$err     = "";
$user_id = null;

// CSRF token for the reset form
if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

// Validate token on initial load (GET)
if ($token !== '') {
  // Only accept tokens that are not expired right now
  $q = $pdo->prepare("SELECT user_id FROM password_resets WHERE token=? AND expires_at > NOW() LIMIT 1");
  $q->execute([$token]);
  $row = $q->fetch();
  if ($row) {
    $user_id = (int)$row['user_id'];
  } else {
    $err = "Invalid or expired reset link. Please request a new one.";
  }
} else {
  $err = "Missing token.";
}

// Handle password change (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id) {
  // CSRF check
  $csrf = $_POST['csrf'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    $err = "Invalid request. Please reload the page and try again.";
  } else {
    $p1 = $_POST['password'] ?? '';
    $p2 = $_POST['confirm']  ?? '';
    if (strlen($p1) < 8) {
      $err = "Password must be at least 8 characters.";
    } elseif ($p1 !== $p2) {
      $err = "Passwords do not match.";
    } else {
      try {
        $pdo->beginTransaction();

        // Double-check the token is still valid to prevent race conditions
        $chk = $pdo->prepare("SELECT user_id FROM password_resets WHERE token=? AND user_id=? AND expires_at > NOW() LIMIT 1");
        $chk->execute([$token, $user_id]);
        if (!$chk->fetch()) {
          throw new Exception("This reset link is no longer valid. Please request a new one.");
        }

        // Update password
        $hash = password_hash($p1, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE auth_user SET password_hash=? WHERE user_id=?");
        $upd->execute([$hash, $user_id]);

        // Invalidate all outstanding reset tokens for this user (single-use)
        $del = $pdo->prepare("DELETE FROM password_resets WHERE user_id=?");
        $del->execute([$user_id]);

        $pdo->commit();

        // End any existing session (force fresh login)
        destroy_session_completely();

        $_SESSION['flash_success'] = "Your password has been updated. You can log in now.";
        header("Location: login.php");
        exit;
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = "Something went wrong. Please request a new reset link and try again.";
      }
    }
  }
}
?>
<!doctype html>
<html>
<head>
  <title>Reset password</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .card{max-width:480px;margin:40px auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;}
    .flash-err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:10px;border-radius:10px;margin:10px 0;}
    .input{width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:10px}
    .btn{background:#111827;color:#fff;border:0;border-radius:10px;padding:10px 14px}
    a.link{color:#0f62fe;text-decoration:none}
  </style>
</head>
<body>
  <div class="card">
    <h2>Reset password</h2>
    <?php if($err): ?><div class="flash-err"><?=e($err)?></div><?php endif; ?>

    <?php if($user_id): ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?=e($CSRF)?>">
        <div style="margin-bottom:10px">
          <label>New password</label>
          <input class="input" type="password" name="password" required minlength="8" placeholder="At least 8 characters">
        </div>
        <div style="margin-bottom:10px">
          <label>Confirm password</label>
          <input class="input" type="password" name="confirm" required minlength="8">
        </div>
        <button class="btn" type="submit">Update password</button>
      </form>
    <?php else: ?>
      <p><a class="link" href="forget_password.php">Request a new link</a> or <a class="link" href="login.php">Back to login</a></p>
    <?php endif; ?>
  </div>
</body>
</html>
