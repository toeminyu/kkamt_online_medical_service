<?php
// about.php — Team page (DB-driven)
session_start();
require_once __DIR__ . '/db.php';      // must define $pdo (PDO::ERRMODE_EXCEPTION etc.)

// Helper: safe escape
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Fetch doctors (adapt field names if yours differ)
$sql = "
  SELECT 
    doctor_ID,
    doctor_name,
    doctor_gender,          -- VARCHAR(45) in your ERD
    doctor_email,
    doctor_degree,          -- e.g., MD, Pediatrics
    doctor_description,     -- short bio
    created_at
  FROM doctor
  ORDER BY doctor_name ASC
";
$doctors = $pdo->query($sql)->fetchAll();

// Helper: resolve a photo path for each doctor (tries uploads, then gender placeholder)
function doctor_photo(array $d): string {
  $id = (int)($d['doctor_ID'] ?? 0);
  $candidates = [
    "uploads/doctors/{$id}.jpg",
    "uploads/doctors/{$id}.png",
    "images/doctors/{$id}.jpg",
    "images/doctors/{$id}.png",
  ];
  foreach ($candidates as $p) {
    if (is_file(__DIR__ . "/$p")) return $p;
  }
  $g = strtolower(trim($d['doctor_gender'] ?? ''));
  if ($g === 'female' || $g === 'f') return 'assets/doctor-female.png';
  if ($g === 'male'   || $g === 'm') return 'assets/doctor-male.png';
  return 'assets/doctor-generic.png';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>About Us - 24/7 Online Medical Service</title>
<style>
  body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;margin:0;background:#f5f7fa;color:#2d3748;}
  .main-content{flex:1;width:100%;}
  .team-section{background:linear-gradient(to bottom,#f8f9fa,white);padding:60px 20px;text-align:center;}
  .team-section h2{font-size:32px;color:#2d3748;margin-bottom:40px;}
  .team-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:25px;justify-content:center;max-width:1100px;margin:0 auto;}
  .team-card{background:#fff;padding:50px;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,.1);transition:all .3s ease;position:relative;overflow:hidden}
  .team-card:hover{transform:translateY(-4px);box-shadow:0 12px 28px rgba(102,126,234,.25)}
  .team-card img{width:120px;height:120px;border-radius:50%;object-fit:cover;margin-bottom:15px;border:3px solid #eef2ff}
  .team-card h3{color:#667eea;font-size:18px;margin-bottom:5px}
  .team-card p{color:#4a5568;font-size:14px;margin:0}
  .bio-popup{position:absolute;inset:0;background:rgba(102,126,234,.95);color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;opacity:0;transform:scale(.96);transition:all .25s ease;border-radius:15px}
  .team-card:hover .bio-popup{opacity:1;transform:scale(1)}
  .bio-popup h4{margin-bottom:10px;font-size:18px}
  .bio-popup p{font-size:14px;line-height:1.6;text-align:center;max-width:90%; color:white;}
</style>
</head>
<body>
<?php include 'nav.php'; ?>

<main class="main-content">
  <section class="team-section">
    <h2>Our Doctors</h2>

    <div class="team-grid">
      <?php if (!$doctors): ?>
        <div class="team-card">
          <h3>No doctors found</h3>
          <p>Please add doctor records in the <code>doctor</code> table.</p>
        </div>
      <?php else: ?>
        <?php foreach ($doctors as $d): 
          $img = doctor_photo($d);
          $name = $d['doctor_name'] ?: 'Doctor';
          $degree = $d['doctor_degree'] ?: 'Medical Practitioner';
          $bio = $d['doctor_description'] ?: 'Available for online consultations and digital prescriptions.';
        ?>
        <div class="team-card">
          <h3><?= h($name) ?></h3>
          <p><?= h($degree) ?></p>
          <div class="bio-popup">
            <h4><?= h($name) ?></h4>
            <p><?= h($bio) ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php include 'footer.php'; ?>
</body>
</html>
