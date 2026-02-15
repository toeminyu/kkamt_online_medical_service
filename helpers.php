<?php
// helpers.php

// ========= Basic helpers (yours) =========

// HTML escape helper
if (!function_exists('e')) {
  function e($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
  }
}

// Role-based redirect (define only once)
if (!function_exists('redirect_role')) {
  function redirect_role(string $role): void {
    // IMPORTANT: Call this before any output
    if ($role === 'patient') {
      header("Location: patient.php");
    } elseif ($role === 'doctor') {
      header("Location: doctor.php");
    } elseif ($role === 'admin') {
      header("Location: admin.php");
    } else {
      header("Location: login.php");
    }
    exit;
  }
}

// Top navigation
if (!function_exists('nav')) {
  function nav() {
    echo '<nav class="nav">
      <div class="nav__brand">24/7 Online Medical</div>
      <div class="nav__links">
        <a href="index.php">Home</a>
        <a href="login.php">Log in</a>
        <a class="btn" href="register.php">Sign up</a>
      </div>
    </nav>';
  }
}

// <head> tag contents
if (!function_exists('head_tag')) {
  function head_tag($title = "24/7 Online Medical Service") {
    echo '<meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="styles.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <title>' . e($title) . '</title>';
  }
}

// ========= Stateless Reset Token (NO DB CHANGE) =========

// App secret (since you don't have config.php). Change this to a long random string.
// You can generate one with:  `openssl rand -hex 48`
if (!defined('APP_SECRET')) {
  define('APP_SECRET', 'CHANGE_ME_to_a_long_random_secret_string_generated_once');
}

if (!function_exists('b64url_encode')) {
  function b64url_encode($data){
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }
}
if (!function_exists('b64url_decode')) {
  function b64url_decode($data){
    return base64_decode(strtr($data, '-_', '+/'));
  }
}

/**
 * Create a stateless reset token with user_id + expiry + HMAC.
 * signature = HMAC_SHA256( user_id|exp|password_hash , APP_SECRET )
 * Default TTL = 15 minutes (900s).
 */
if (!function_exists('make_reset_token')) {
  function make_reset_token(int $user_id, string $password_hash, int $ttl_seconds = 900): string {
    $exp = time() + $ttl_seconds;
    $payload = $user_id . '|' . $exp;
    $sig = hash_hmac('sha256', $payload . '|' . $password_hash, APP_SECRET);
    return b64url_encode((string)$user_id) . '.' . b64url_encode((string)$exp) . '.' . b64url_encode($sig);
  }
}

/** Verify a stateless token; returns [bool ok, mixed message_or_user_id] */
if (!function_exists('verify_reset_token')) {
  function verify_reset_token(string $token, PDO $pdo): array {
    $parts = explode('.', (string)$token);
    if (count($parts) !== 3) return [false, 'Invalid token format.'];

    [$u_b64, $e_b64, $s_b64] = $parts;
    $user_id = (int)b64url_decode($u_b64);
    $exp     = (int)b64url_decode($e_b64);
    $sig     = (string)b64url_decode($s_b64);

    if ($user_id <= 0 || $exp <= 0 || $sig === '') return [false, 'Invalid token.'];
    if ($exp < time()) return [false, 'This link has expired. Please request a new one.'];

    // fetch current password_hash so old links die after password change
    $st = $pdo->prepare("SELECT user_id, password_hash, is_active FROM auth_user WHERE user_id=? LIMIT 1");
    $st->execute([$user_id]);
    $u = $st->fetch();

    if (!$u || !$u['is_active']) return [false, 'Invalid account.'];

    $payload  = $user_id . '|' . $exp;
    $expected = hash_hmac('sha256', $payload . '|' . $u['password_hash'], APP_SECRET);

    if (!hash_equals($expected, $sig)) return [false, 'Invalid or already used link.'];

    return [true, $user_id];
  }
}
