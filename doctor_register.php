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

        // Insert into auth_user
        $insUser = $pdo->prepare("
          INSERT INTO auth_user (email, password_hash, role, is_active, created_at, updated_at)
          VALUES (?, ?, 'doctor', 0, NOW(), NOW())
        ");
        $insUser->execute([$email, $hash]);
        $userId = (int)$pdo->lastInsertId();

        // Insert into doctor table
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
        $doctorId = (int)$pdo->lastInsertId();

        // Insert selected languages
        if (!empty($_POST['languages'])) {
          $insLang = $pdo->prepare("
            INSERT INTO doctor_languages (doctor_id, language, proficiency, is_primary, created_at)
            VALUES (?, ?, ?, ?, NOW())
          ");
          foreach ($_POST['languages'] as $lang) {
            $prof = $_POST['proficiency'][$lang] ?? null;
            $isPrimary = ($prof === 'native') ? 1 : 0;
            $insLang->execute([$doctorId, $lang, $prof, $isPrimary]);
          }
        }

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
      width:100%;max-width:540px; /* widened from 380px */
      background:#fff;border-radius:12px;
      padding:20px 40px;
      box-shadow:0 8px 20px rgba(0,0,0,.2);
      animation:fadeIn .5s ease;
      text-align:center;
      overflow-y:auto;
      max-height:95vh;
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

    /* === Language section tweaks === */
    .lang-wrap{margin-top:6px;}
    .lang-grid{
      display:grid;
      grid-template-columns:repeat(auto-fill,minmax(190px,1fr));
      gap:10px;
    }
    .lang-card{
      border:1px solid #e2e8f0;
      border-radius:10px;
      padding:6px 8px;
      transition:box-shadow .2s,border-color .2s;
    }
    .lang-card.active{border-color:#667eea;box-shadow:0 0 0 2px rgba(102,126,234,.18);}
    .lang-row{
      display:flex;align-items:center;justify-content:space-between;gap:8px;
    }
    .lang-left{
      display:flex;align-items:center;gap:8px;
    }
    .lang-label{
      font-size:13px;color:#2d3748;font-weight:600;
    }
    .lang-select{
      width:auto;min-width:120px;display:none;
    }
    .lang-hint{
      font-size:10.5px;color:#718096;margin-top:4px;display:flex;gap:6px;align-items:center;
    }
    .lang-dot{width:6px;height:6px;border-radius:50%;background:#a0aec0;display:inline-block;}
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

      <!-- === Language Section === -->
      <div class="form-group">
        <label>Languages to consult with</label>
        <div class="lang-wrap">
          <div class="lang-grid">
            <?php
              $languages = ['English','Thai','Chinese','Myanmar'];
              foreach ($languages as $lang):
                $id = 'lang_' . preg_replace('/\W+/','_', strtolower($lang));
            ?>
            <div class="lang-card">
              <div class="lang-row">
                <div class="lang-left">
                  <input type="checkbox" name="languages[]" value="<?= $lang ?>" id="<?= $id ?>">
                  <label class="lang-label" for="<?= $id ?>"><?= $lang ?></label>
                </div>
                <select class="lang-select" name="proficiency[<?= $lang ?>]">
                  <option disabled selected>Proficiency</option>
                  <option value="native">Native</option>
                  <option value="fluent">Fluent</option>
                  <option value="conversational">Conversational</option>
                </select>
              </div>
              <div class="lang-hint"><span class="lang-dot"></span><span>Select a language, then choose proficiency.</span></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <button type="submit">Create Account</button>
    </form>

    <?php if($err): ?><p class="message error"><?= e($err) ?></p><?php endif; ?>
    <?php if($msg): ?><p class="message success"><?= e($msg) ?></p><?php endif; ?>

    <p class="note">Already have an account? <a href="login.php">Login</a></p>
  </div>

  <script>
    document.querySelectorAll('.lang-card').forEach(card=>{
      const checkbox = card.querySelector('input[type="checkbox"]');
      const select   = card.querySelector('.lang-select');
      const sync = ()=>{
        if (checkbox.checked) {
          card.classList.add('active');
          select.style.display = 'inline-block';
        } else {
          card.classList.remove('active');
          select.style.display = 'none';
          select.value = 'Proficiency';
        }
      };
      checkbox.addEventListener('change', sync);
      sync();
    });
  </script>
</body>
</html>
