<?php
// session.php — single place to manage sessions + access guards

// Start the session safely once, with secure cookie flags
if (session_status() === PHP_SESSION_NONE) {
  if (function_exists('session_set_cookie_params')) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
      'lifetime' => 0,
      'path'     => '/',
      'domain'   => '',
      'secure'   => $secure,
      'httponly' => true,
      'samesite' => 'Lax'
    ]);
  }
  session_start();
}

/** Send no-cache headers to prevent “Back” from showing protected content */
function set_no_cache_headers(): void {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
}

/** Guard: require login */
function require_login(): void {
  set_no_cache_headers();
  if (empty($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? 'patient.php';
    header("Location: login.php");
    exit;
  }
}

/** After successful login, prevent session fixation */
function on_successful_login(): void {
  if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
  }
}

/** Logout: fully destroy session + cookie and send no-cache */
function destroy_session_completely(): void {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
  }
  session_destroy();
  set_no_cache_headers();
}

/**
 * Smart redirect by role
 * (Kept here only if you DON'T also define it in helpers.php — avoid duplicates)
 * If you already define redirect_role() in helpers.php, remove it here.
 */
/*
function redirect_role(string $role): void {
  if     ($role === 'patient') { header("Location: patient.php"); }
  elseif ($role === 'doctor')  { header("Location: doctor.php"); }
  elseif ($role === 'admin')   { header("Location: admin.php"); }
  else                         { header("Location: login.php"); }
  exit;
}
*/
