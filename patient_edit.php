<?php
// edit_profile.php
require_once 'session.php';   // if you have require_login() here, call it
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$userId   = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? ''; // 'patient' | 'doctor' | 'admin'

// Helpers
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function null_if_empty($v){ $v = trim((string)$v); return $v === '' ? null : $v; }
function avatar_path_for($userId){
  $base = __DIR__ . "/uploads/avatars";
  if (!is_dir($base)) @mkdir($base, 0775, true);
  foreach (['jpg','jpeg','png','gif','webp'] as $ext){
    $p = "$base/avatar_{$userId}.$ext";
    if (file_exists($p)) return "uploads/avatars/avatar_{$userId}.$ext";
  }
  return "assets/default-avatar.png"; // put a default img here
}

// Prefill data
$u = [
  'email'      => '',
  'full_name'  => '',
];
$r = []; // role fields

try {
  $st = $pdo->prepare("SELECT email, COALESCE(NULLIF(TRIM(full_name),''), '') AS full_name FROM auth_user WHERE user_id=? LIMIT 1");
  $st->execute([$userId]);
  $u = $st->fetch() ?: $u;

  if ($userRole === 'doctor') {
    $st = $pdo->prepare("
      SELECT doctor_name, doctor_email, doctor_ph, doctor_gender, doctor_degree, doctor_description
      FROM doctor WHERE user_id=? LIMIT 1
    ");
    $st->execute([$userId]);
    $r = $st->fetch() ?: [];
  } elseif ($userRole === 'patient') {
    $st = $pdo->prepare("
      SELECT patient_name, patient_email, patient_ph, patient_gender, patient_dob, patient_address
      FROM patient WHERE user_id=? LIMIT 1
    ");
    $st->execute([$userId]);
    $r = $st->fetch() ?: [];
  }
} catch (Throwable $e) {
  // you can log $e->getMessage()
}

$errors = [];
$success = "";

// Handle POST (Save)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Common fields from the form
  $username   = trim($_POST['username'] ?? '');              // display name
  $first_name = trim($_POST['first_name'] ?? '');
  $last_name  = trim($_POST['last_name'] ?? '');
  $org_name   = trim($_POST['org_name'] ?? '');              // optional (for display only)
  $location   = trim($_POST['location'] ?? '');              // -> patient_address
  $email_new  = trim($_POST['email'] ?? '');
  $phone      = trim($_POST['phone'] ?? '');
  $birthday   = trim($_POST['birthday'] ?? '');              // -> patient_dob (Y-m-d)
  $gender     = trim($_POST['gender'] ?? '');                // -> *_gender

  // For doctors
  $doctor_degree = trim($_POST['doctor_degree'] ?? '');
  $doctor_desc   = trim($_POST['doctor_description'] ?? '');

  // Build display name
  $display_name = $username !== '' ? $username : trim($first_name . ' ' . $last_name);

  // Basic validations
  if ($email_new === '' || !filter_var($email_new, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Please enter a valid email address.";
  }
  if ($display_name === '') {
    $errors[] = "Please provide a username or first/last name.";
  }

  // Birthday normalize (optional)
  if ($birthday !== '') {
    // Accept dd/mm/yyyy or yyyy-mm-dd
    if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $birthday)) {
      $dob_sql = $birthday;
    } else {
      // try to parse other formats
      $ts = strtotime($birthday);
      $dob_sql = $ts ? date('Y-m-d', $ts) : null;
      if (!$ts) $errors[] = "Invalid birthday format.";
    }
  } else {
    $dob_sql = null;
  }

  // Handle avatar upload (optional)
  $avatar_url = avatar_path_for($userId); // current (or default)
  if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
      $errors[] = "Error uploading image.";
    } else {
      $maxBytes = 5 * 1024 * 1024; // 5MB
      if ($_FILES['avatar']['size'] > $maxBytes) {
        $errors[] = "Image too large. Max 5 MB.";
      } else {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['avatar']['tmp_name']);
        finfo_close($finfo);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
          $errors[] = "Only JPG or PNG or WEBP images are allowed.";
        } else {
          $ext = $allowed[$mime];
          $dir = __DIR__ . "/uploads/avatars";
          if (!is_dir($dir)) @mkdir($dir, 0775, true);
          $destFs = "$dir/avatar_{$userId}.$ext";
          // remove old avatars with other extensions
          foreach (glob("$dir/avatar_{$userId}.*") as $old) { @unlink($old); }
          if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $destFs)) {
            $errors[] = "Failed to save uploaded image.";
          } else {
            $avatar_url = "uploads/avatars/avatar_{$userId}.$ext";
          }
        }
      }
    }
  }

  if (!$errors) {
    try {
      $pdo->beginTransaction();

      // Update auth_user
      $st = $pdo->prepare("UPDATE auth_user SET email=?, full_name=?, updated_at=NOW() WHERE user_id=?");
      $st->execute([$email_new, $display_name, $userId]);

      // Update role-specific table
      if ($userRole === 'doctor') {
        $st = $pdo->prepare("
          UPDATE doctor
          SET doctor_name=?,
              doctor_email=?,
              doctor_ph=?,
              doctor_gender=?,
              doctor_degree=?,
              doctor_description=?,
              updated_at=NOW()
          WHERE user_id=?");
        $st->execute([
          $display_name,
          $email_new,
          null_if_empty($phone),
          null_if_empty($gender),
          null_if_empty($doctor_degree),
          null_if_empty($doctor_desc),
          $userId
        ]);
      } elseif ($userRole === 'patient') {
        $st = $pdo->prepare("
          UPDATE patient
          SET patient_name=?,
              patient_email=?,
              patient_ph=?,
              patient_gender=?,
              patient_dob=?,
              patient_address=?,
              updated_at=NOW()
          WHERE user_id=?");
        $st->execute([
          $display_name,
          $email_new,
          null_if_empty($phone),
          null_if_empty($gender),
          $dob_sql,
          null_if_empty($location),
          $userId
        ]);
      } else {
        // admin: nothing extra to update beyond auth_user here
      }

      $pdo->commit();

      // Refresh session values
      $_SESSION['email']      = $email_new;
      $_SESSION['full_name']  = $display_name;

      $success = "Profile updated successfully.";
      // reload current values for display
      header("Location: edit_profile.php?ok=1");
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = "Failed to save changes. " . $e->getMessage();
    }
  }
}

// compute display fields (fall back)
$avatar_url = avatar_path_for($userId);
$display_name = $u['full_name'] ?: ($userRole === 'doctor' ? ($r['doctor_name'] ?? '') : ($r['patient_name'] ?? ''));
$display_email = $u['email'] ?: ($userRole === 'doctor' ? ($r['doctor_email'] ?? '') : ($r['patient_email'] ?? ''));

// role-map helpers
$phone_val   = $userRole === 'doctor' ? ($r['doctor_ph'] ?? '') : ($r['patient_ph'] ?? '');
$gender_val  = $userRole === 'doctor' ? ($r['doctor_gender'] ?? '') : ($r['patient_gender'] ?? '');
$dob_val     = $userRole === 'patient' ? ($r['patient_dob'] ?? '') : '';
$addr_val    = $userRole === 'patient' ? ($r['patient_address'] ?? '') : '';
$degree_val  = $userRole === 'doctor' ? ($r['doctor_degree'] ?? '') : '';
$desc_val    = $userRole === 'doctor' ? ($r['doctor_description'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Edit Profile</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root { --bg:#f5f7fb; --card:#fff; --muted:#718096; --brand:#3b82f6; --radius:16px; }
    *{box-sizing:border-box}
    body{margin:0;font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,'Apple Color Emoji','Segoe UI Emoji';background:var(--bg);color:#1f2937}
    .container{max-width:1100px;margin:40px auto;padding:0 16px}
    .grid{display:grid;grid-template-columns: 1fr 2fr; gap:24px}
    .card{background:var(--card);border-radius:var(--radius);box-shadow:0 10px 25px rgba(0,0,0,.08)}
    .card .header{padding:16px 20px;border-bottom:1px solid #edf2f7;font-weight:600}
    .card .body{padding:20px}
    .muted{color:var(--muted);font-size:14px}

    .avatar-wrap{display:flex;flex-direction:column;align-items:center;gap:16px}
    .avatar{width:160px;height:160px;border-radius:50%;object-fit:cover;box-shadow:0 4px 20px rgba(0,0,0,.12)}
    .btn{appearance:none;border:none;background:var(--brand);color:#fff;border-radius:10px;padding:10px 14px;font-weight:600;cursor:pointer}
    .btn.secondary{background:#e5e7eb;color:#111827}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .field{margin-bottom:14px}
    .field label{display:block;margin:6px 2px 6px;font-size:14px;color:#374151}
    .field input,.field select,.field textarea{
      width:100%;padding:12px 12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;font-size:15px}
    .actions{margin-top:12px;display:flex;gap:12px;justify-content:flex-end}

    .alert{padding:12px 14px;border-radius:10px;margin-bottom:16px}
    .alert.err{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
    .alert.ok{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}

    @media (max-width:900px){ .grid{grid-template-columns:1fr} }
  </style>
</head>
<body>

<?php include 'nav.php'; ?>

<div class="container">
  <form method="post" enctype="multipart/form-data">
    <div class="grid">
      <!-- Left: avatar -->
      <div class="card">
        <div class="header">Profile Picture</div>
        <div class="body">
          <div class="avatar-wrap">
            <img class="avatar" src="<?= e($avatar_url) ?>" alt="Avatar" />
            <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp" />
            <div class="muted">JPG, PNG or WEBP • up to 5 MB</div>
          </div>
        </div>
      </div>

      <!-- Right: account details -->
      <div class="card">
        <div class="header">Account Details</div>
        <div class="body">
          <?php if (!empty($_GET['ok']) || $success): ?>
            <div class="alert ok">Profile updated successfully.</div>
          <?php endif; ?>
          <?php if ($errors): ?>
            <div class="alert err">
              <?= e(implode(" ", $errors)) ?>
            </div>
          <?php endif; ?>

          <div class="field">
            <label>Username (how your name appears)</label>
            <input type="text" name="username" value="<?= e($display_name) ?>" placeholder="username" />
          </div>

          <div class="row">
            <div class="field">
              <label>First name</label>
              <input type="text" name="first_name" placeholder="First name" />
            </div>
            <div class="field">
              <label>Last name</label>
              <input type="text" name="last_name" placeholder="Last name" />
            </div>
          </div>



          <div class="field">
            <label>Email address</label>
            <input type="email" name="email" value="<?= e($display_email) ?>" placeholder="name@example.com" required />
          </div>

          <div class="row">
            <div class="field">
              <label>Phone number</label>
              <input type="text" name="phone" value="<?= e($phone_val) ?>" placeholder="eg. 555-123-4567" />
            </div>
            <div class="field">
              <label>Birthday</label>
              <input type="date" name="birthday" value="<?= e($dob_val) ?>" />
            </div>
          </div>

          <div class="row">
            <div class="field">
              <label>Gender</label>
              <select name="gender">
                <?php
                  $g = strtoupper((string)$gender_val);
                  $opts = ['M' => 'Male', 'F' => 'Female', 'O' => 'Other', '' => 'Prefer not to say'];
                  foreach ($opts as $k => $label) {
                    $sel = ($k !== '' && $g === $k) ? 'selected' : '';
                    if ($k === '' && $g === '') $sel = 'selected';
                    echo "<option value='".e($k)."' $sel>".e($label)."</option>";
                  }
                ?>
              </select>
            </div>

            <?php if ($userRole === 'doctor'): ?>
              <div class="field">
                <label>Medical Degree</label>
                <input type="text" name="doctor_degree" value="<?= e($degree_val) ?>" placeholder="MBBS, MD, etc." />
              </div>
            <?php else: ?>
              <div class="field">
                <!-- spacer to keep grid aligned -->
              </div>
            <?php endif; ?>
          </div>

          <?php if ($userRole === 'doctor'): ?>
            <div class="field">
              <label>About / Description</label>
              <textarea name="doctor_description" rows="4" placeholder="Short bio for patients"><?= e($desc_val) ?></textarea>
            </div>
          <?php endif; ?>

          <div class="actions">
            <button class="btn" type="submit">Save changes</button>
            <a class="btn secondary" href="home.php" role="button">Cancel</a>
          </div>

          <div class="muted" style="margin-top:8px;">
            Changes affect how your name appears across the site and emails.
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

</body>
</html>
