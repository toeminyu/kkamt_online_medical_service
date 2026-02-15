<?php
require_once "db.php";
require_once "session.php";
require_once "helpers.php";

$err = $_SESSION['flash_error']  ?? "";
$ok  = $_SESSION['flash_success']?? "";
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $email = trim($_POST["email"] ?? "");
  $password = $_POST["password"] ?? "";
  $role = $_POST["role"] ?? "patient";

  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === "") {
    $err = "Enter valid email and password.";
  } else {
    $st = $pdo->prepare("SELECT user_id,email,password_hash,role,is_active FROM auth_user WHERE email=? AND role=? LIMIT 1");
    $st->execute([$email,$role]);
    $u = $st->fetch();
    if (!$u) $err = "Account not found.";
    elseif (!(int)$u['is_active']) $err = "Account inactive.";
    elseif (!password_verify($password,$u['password_hash'])) $err = "Wrong password.";
    else {
      $_SESSION['user_id']=$u['user_id'];
      $_SESSION['email']=$u['email'];
      $_SESSION['role']=$u['role'];

      // Prevent session fixation
      on_successful_login();

      // Optional: return to originally requested page (avoid loops)
      $back = $_SESSION['redirect_after_login'] ?? '';
      unset($_SESSION['redirect_after_login']);
      if ($back && !preg_match('~(?:login|forget|reset)_password\.php~i', $back)) {
        header("Location: ".$back);
        exit;
      }

      redirect_role($u['role']);
    }
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>24/7 Online Medical Service - Login</title>
  <style>
    *{box-sizing:border-box;font-family:"Segoe UI",Arial,sans-serif;margin:0;padding:0}
    body{
      background:#f9fafb;
      display:flex;
      justify-content:center;
      align-items:center;
      min-height:100vh;
    }
    .container{
      background:#fff;
      padding:40px 35px;
      border-radius:16px;
      box-shadow:0 4px 15px rgba(0,0,0,0.05);
      width:100%;
      max-width:400px;
      text-align:center;
    }
    .logo img{width:90px;height:90px;margin-bottom:10px;}
    .logo h1{font-size:1.4rem;color:#111827;margin-bottom:5px;}
    .logo p{font-size:0.9rem;color:#6b7280;margin-bottom:25px;}

    form{text-align:left;}
    .form-group{margin-bottom:15px;}
    .form-group label{
      display:block;font-weight:600;margin-bottom:6px;color:#374151;
    }
    .form-group input,.form-group select{
      width:100%;
      padding:10px 12px;
      border:1px solid #d1d5db;
      border-radius:8px;
      font-size:0.95rem;
      color:#111827;
    }
    .form-group input:focus{
      border-color:#0f62fe;
      outline:none;
      box-shadow:0 0 0 3px rgba(15,98,254,0.2);
    }

    button{
      width:100%;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color:#fff;
      padding:12px;
      font-size:1rem;
      border:none;
      border-radius:8px;
      cursor:pointer;
      font-weight:600;
      transition:background 0.2s ease;
    }
    button:hover{background:#667eea;}


    .role-tabs{
      display:flex;
      justify-content:center;
      gap:20px;
      margin-bottom:20px;
    }
    .role-tabs label{
      cursor:pointer;
      font-weight:600;
      display:flex;
      align-items:center;
      gap:5px;
      color:#374151;
    }
    .role-tabs input[type="radio"]{accent-color:#0f62fe;}

    .flash-ok{
      background:#ecfdf5;
      border:1px solid #a7f3d0;
      color:#065f46;
      padding:10px;
      border-radius:8px;
      margin:10px 0;
      text-align:left;
      font-size:0.9rem;
    }
    .flash-err{
      background:#fef2f2;
      border:1px solid #fecaca;
      color:#991b1b;
      padding:10px;
      border-radius:8px;
      margin:10px 0;
      text-align:left;
      font-size:0.9rem;
    }

    .login-footer{
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-top:12px;
    }
    .link{
      color:#0f62fe;
      text-decoration:none;
      font-weight:500;
    }
    .link:hover{text-decoration:underline;}
    .note{
      font-size:0.85rem;
      color:#6b7280;
      text-align:center;
      margin-top:12px;
    }
    @media(max-width:480px){
      .container{margin:20px;padding:30px 20px;}
      .logo h1{font-size:1.2rem;}
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="logo">
      <img src="image/logo.jpg" alt="logo"/>
      <h1>24/7 online medical service</h1>
      <p>Your telemedicine platform</p>
    </div>

    <?php if($ok): ?><div class="flash-ok"><?=e($ok)?></div><?php endif; ?>
    <?php if($err): ?><div class="flash-err"><?=e($err)?></div><?php endif; ?>

    <form method="post">
      <div class="role-tabs">
        <label><input type="radio" name="role" value="patient" checked>👤 Patient</label>
        <label><input type="radio" name="role" value="doctor">🛡 Doctor</label>
      </div>

      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" placeholder="Enter your email" required>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="Enter your password" required>
      </div>
      <button type="submit">Sign In</button>

      <div class="login-footer">
        <a class="link" href="forget_password.php">Forgot password?</a>
      </div>
    </form>
  </div>
</body>
</html>
