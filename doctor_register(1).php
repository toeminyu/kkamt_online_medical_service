<?php
require_once "db.php";
require_once "session.php";
require_once "helpers.php";

$err = "";
$msg = "";

function null_if_empty($v){ $v = trim((string)$v); return ($v === "") ? null : $v; }

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $name        = trim($_POST["name"] ?? "");
  $email       = trim($_POST["email"] ?? "");
  $password    = $_POST["password"] ?? "";
  $degree      = trim($_POST["degree"] ?? "");
  $description = trim($_POST["description"] ?? "");
  $phone       = trim($_POST["phone"] ?? "");
  $gender      = trim($_POST["gender"] ?? "");

  if ($name === "") {
    $err = "Full name is required.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = "Invalid email address.";
  } elseif (strlen($password) < 6) {
    $err = "Password must be at least 6 characters.";
  } elseif ($degree === "") {
    $err = "Doctor degree / specialization is required.";
  } elseif ($gender !== "" && !in_array($gender, ["Male","Female","Other"], true)) {
    $err = "Please select a valid gender.";
  }

  if (!$err) {
    try {
      $chk = $pdo->prepare("SELECT user_id FROM auth_user WHERE email=? LIMIT 1");
      $chk->execute([$email]);
      if ($chk->fetch()) {
        $err = "Email already registered.";
      } else {
        $pdo->beginTransaction();
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $insUser = $pdo->prepare("
          INSERT INTO auth_user (email, password_hash, role, is_active, created_at, updated_at)
          VALUES (?, ?, 'doctor', 0, NOW(), NOW())
        ");
        $insUser->execute([$email, $hash]);
        $userId = (int)$pdo->lastInsertId();

        $insDoc = $pdo->prepare("
          INSERT INTO doctor
            (user_id, doctor_name, doctor_email, doctor_ph, doctor_gender, doctor_degree, doctor_description, created_at, updated_at)
          VALUES
            (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $insDoc->execute([
          $userId,
          $name,
          $email,
          null_if_empty($phone),
          null_if_empty($gender),
          $degree,
          null_if_empty($description),
        ]);

        $pdo->commit();
        $msg = "Signup successful! Your account is pending admin approval.";
      }
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $err = "Signup failed: " . $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Doctor Sign Up</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0;}
    body{
      font-family:'Segoe UI',Tahoma,Verdana,sans-serif;
      background:linear-gradient(135deg,#667eea,#764ba2);
      height:100vh;display:flex;justify-content:center;align-items:center;
    }
    .container{
      width:100%;max-width:380px;
      background:#fff;border-radius:12px;
      padding:20px 40px;
      box-shadow:0 8px 20px rgba(0,0,0,.2);
      animation:fadeIn .5s ease;
      text-align:center;
    }
    @keyframes fadeIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
    .logo img{width:50px;height:50px;}
    .logo h1{font-size:20px;margin:8px 0 3px;color:#2d3748;}
    .logo p{font-size:12px;color:#718096;margin-bottom:14px;}
    form{text-align:left;}
    .form-group{margin-bottom:10px;}
    label{font-size:12px;font-weight:600;color:#4a5568;margin-bottom:4px;display:block;}
    input,select,textarea{
      width:100%;padding:8px 10px;font-size:13px;
      border:1px solid #cbd5e0;border-radius:8px;outline:none;
    }
    input:focus,select:focus,textarea:focus{
      border-color:#667eea;box-shadow:0 0 0 2px rgba(102,126,234,.25);
    }
    textarea{resize:vertical;min-height:70px;}
    button{
      width:100%;padding:8px 10px;font-size:14px;
      background:linear-gradient(135deg,#667eea,#764ba2);
      color:#fff;border:none;border-radius:8px;cursor:pointer;
      font-weight:600;margin-top:6px;
    }
    button:hover{opacity:.9;}
    .message{margin-top:8px;font-size:13px;font-weight:600;}
    .error{color:#e53e3e;}
    .success{color:#38a169;}
    .note{margin-top:10px;font-size:12px;color:#4a5568;}
    .note a{color:#667eea;font-weight:600;text-decoration:none;}
    .note a:hover{text-decoration:underline;}
  </style>
</head>
<body>
  <div class="container">
    <div class="logo">
      <img src="image/logo.jpg" alt="logo"/>
      <h1>Doctor Sign Up</h1>
      <p>Join as a healthcare provider</p>
    </div>

    <form method="post">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="name" required value="<?= e($_POST['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>
      <div class="form-group">
  <label>Specialization / Service</label>
  <select name="degree" required>
    <option value="">-- Select Service --</option>
    <?php
      // Fetch service list from DB
      $stmt = $pdo->query("SELECT service_ID, service_name FROM service ORDER BY service_name");
      $services = $stmt->fetchAll();

      foreach ($services as $srv) {
        $selected = ((string)($_POST['degree'] ?? '') === $srv['service_name']) ? 'selected' : '';
        echo "<option value=\"".htmlspecialchars($srv['service_name'])."\" $selected>"
             .htmlspecialchars($srv['service_name'])
             ."</option>";
      }
    ?>
  </select>
</div>

      <div class="form-group">
        <label>Phone (optional)</label>
        <input type="text" name="phone" value="<?= e($_POST['phone'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Gender (optional)</label>
        <select name="gender">
          <option value="">Select</option>
          <?php
            $g = $_POST['gender'] ?? '';
            foreach (['Male','Female','Other'] as $opt) {
              $sel = ($g === $opt) ? 'selected' : '';
              echo "<option $sel>$opt</option>";
            }
          ?>
        </select>
      </div>
      <div class="form-group">
        <label>Professional Bio (optional)</label>
        <textarea name="description"><?= e($_POST['description'] ?? '') ?></textarea>
      </div>
      <button type="submit">Create Account</button>
    </form>

    <?php if($err): ?><p class="message error"><?= e($err) ?></p><?php endif; ?>
    <?php if($msg): ?><p class="message success"><?= e($msg) ?></p><?php endif; ?>

    <p class="note">Already have an account? <a href="login.php">Login</a></p>
  </div>
</body>
</html>
