<?php
// doctor_view.php — pick a time for a single doctor, then go to confirmation
require_once "session.php";
require_once "db.php";
require_once "helpers.php";
require_login();

if (($_SESSION['role'] ?? '') !== 'patient') redirect_role($_SESSION['role']);

/* -------- Inputs -------- */
$doctor_id          = (int)($_GET['id'] ?? $_POST['doctor_id'] ?? 0);
$selected_date      = trim($_GET['date'] ?? $_POST['date'] ?? date('Y-m-d'));
$service_id         = (int)($_GET['service_id'] ?? $_POST['service_id'] ?? 0);
$preferred_language = trim($_GET['language'] ?? $_POST['preferred_language'] ?? '');
$prefill_notes      = trim($_GET['notes'] ?? $_POST['notes'] ?? '');

if (!$doctor_id) { header("Location: book.php"); exit; }

/* Clamp date (today..+7 days) */
$today  = date('Y-m-d');
$maxDay = date('Y-m-d', strtotime('+7 days'));
if ($selected_date < $today || $selected_date > $maxDay) $selected_date = $today;

/* -------- Fetch doctor -------- */
$dq = $pdo->prepare("SELECT doctor_ID, doctor_name FROM doctor WHERE doctor_ID=? LIMIT 1");
$dq->execute([$doctor_id]);
$doctor = $dq->fetch();
if (!$doctor) { header("Location: book.php"); exit; }

/* -------- Fetch available slots for that date -------- */
$sq = $pdo->prepare("
  SELECT slot_id, start_dt, end_dt
  FROM schedule_slot
  WHERE doctor_id = ?
    AND status = 'available'
    AND DATE(start_dt) = ?
    AND start_dt > NOW()
  ORDER BY start_dt
");
$sq->execute([$doctor_id, $selected_date]);
$slots = $sq->fetchAll();

/* Helpful helpers */
function drname($name){ return preg_match('/^\s*dr\.?/i',$name) ? $name : "Dr.".$name; }
function time_label($row){ return date('H:i', strtotime($row['start_dt'])) . ' - ' . date('H:i', strtotime($row['end_dt'])); }

/* Build back link with current filters */
$backQS = array_filter([
  'service_id' => $service_id ?: null,
  'language'   => $preferred_language ?: null,
  'date'       => $selected_date,
], fn($v)=>!is_null($v)&&$v!=='');
$backHref = 'book.php' . (empty($backQS)?'':('?'.http_build_query($backQS)));
?>
<!doctype html>
<html>
<head>
  <?php head_tag("Choose a time"); ?>
  <style>
    body{ background:#f7f7fb; margin:0; font-family:'Segoe UI', Tahoma, sans-serif; }
    .wrap{ max-width:1100px; margin:auto; padding:22px 20px; }

    a.back-link{ color:#4338ca; text-decoration:none; display:inline-block; margin-bottom:12px; }

    .title{ font-size:28px; font-weight:800; margin:0 0 6px; }
    .sub{ color:#6b7280; margin-bottom:18px; }

    .pill{ display:inline-block; background:#eef2ff; color:#4338ca; padding:6px 10px;
           border-radius:999px; font-weight:700; font-size:12px; }

    /* Time grid */
    .grid{ display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
    @media(max-width:1000px){ .grid{ grid-template-columns:repeat(3,1fr); } }
    @media(max-width:720px){ .grid{ grid-template-columns:repeat(2,1fr); } }
    @media(max-width:460px){ .grid{ grid-template-columns:1fr; } }

    .slot{
      background:#fff; border:2px solid #e5e7eb; border-radius:12px;
      padding:14px; cursor:pointer; text-align:center; transition:.15s;
    }
    .slot:hover{ border-color:#4338ca; box-shadow:0 4px 12px rgba(67,56,202,.15); }
    .slot.selected{ border-color:#4338ca; background:#eef2ff; }

    .slot-time{ font-weight:400; }
    .slot-sub{ color:#6b7280; font-size:12px; }

    .panel{ background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; margin-top:18px; }
    .label{ font-weight:700; margin-bottom:8px; display:block; }
    .input{ width:100%; padding:10px 12px; border:1px solid #e5e7eb; border-radius:10px; background:#fff; }
    textarea.input{ min-height:110px; resize:vertical; }

    /* Buttons: stable black primary + light gray cancel, same as book.php */
    .actions{ margin-top:12px; display:flex; gap:10px; flex-wrap:wrap; }
    .btn-stable{
      display:inline-block; background:#111827 !important; color:#ffffff !important;
      border:0 !important; border-radius:10px !important;
      padding:10px 14px !important; font-weight:700 !important; font-size:14px !important;
      text-decoration:none !important; line-height:1 !important; user-select:none; transition:none !important;
    }
    .btn-stable:hover, .btn-stable:focus, .btn-stable:active{
      background:#111827 !important; color:#ffffff !important; box-shadow:none !important; transform:none !important;
    }

    .btn-cancel{
      display:inline-block; background:black !important; color:white !important;
      border:1px solid #e5e7eb !important; border-radius:10px !important;
      padding:10px 14px !important; font-weight:700 !important; font-size:14px !important;
      text-decoration:none !important; line-height:1 !important; user-select:none; transition:none !important;
    }
    
    .muted{ color:#6b7280; font-size:13px; margin-top:6px; }
    .empty{ color:#6b7280; padding:18px 0; }
  </style>
</head>
<body>
  <?php include "nav.php"; ?>
  <div class="wrap">
    <a class="back-link" href="<?= e($backHref) ?>">← Back to doctor list</a>
    <h2 class="title"><?= e(drname($doctor['doctor_name'])) ?></h2>
    <div class="sub">
      <span class="pill">Date: <?= e(date('D, M d, Y', strtotime($selected_date))) ?></span>
      <?php if($service_id): ?><span class="pill" style="margin-left:8px;">Service ID: <?= (int)$service_id ?></span><?php endif; ?>
      <?php if($preferred_language!==''): ?><span class="pill" style="margin-left:8px;">Lang: <?= e($preferred_language) ?></span><?php endif; ?>
    </div>

    <?php if (empty($slots)): ?>
      <div class="empty">No available times on this date. Please pick another day.</div>
    <?php else: ?>
      <div class="grid" id="timeGrid">
        <?php foreach($slots as $row): ?>
          <?php $lbl = time_label($row); ?>
          <div class="slot" data-slot="<?= (int)$row['slot_id'] ?>" data-time="<?= e($lbl) ?>">
            <div class="slot-time"><?= e($lbl) ?></div>
            <div class="slot-sub">Available</div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="panel" id="confirmForm">
        <label class="label">Reason for Visit (optional)</label>
        <form method="post" id="bookForm" action="book_confirm.php">
          <textarea class="input" name="notes" placeholder="Describe your symptoms or reason..."><?= e($prefill_notes) ?></textarea>

          <!-- Hidden fields to carry forward -->
          <input type="hidden" name="slot_id" id="slot_id">
          <input type="hidden" name="doctor_id" value="<?= (int)$doctor_id ?>">
          <input type="hidden" name="date" value="<?= e($selected_date) ?>">
          <input type="hidden" name="service_id" value="<?= (int)$service_id ?>">
          <input type="hidden" name="preferred_language" value="<?= e($preferred_language) ?>">

          <div class="actions">
            <button type="submit" class="btn-stable" id="proceedBtn" disabled>Proceed to confirmation</button>
            <a href="<?= e($backHref) ?>" class="btn-cancel">Cancel</a>
          </div>
          <div class="muted">Select a time above. You can edit these notes later on the confirmation page if needed.</div>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <script>
    // Handle slot selection
    const grid = document.getElementById('timeGrid');
    const slotInput = document.getElementById('slot_id');
    const proceedBtn = document.getElementById('proceedBtn');

    if (grid) {
      grid.addEventListener('click', (e) => {
        const card = e.target.closest('.slot');
        if (!card) return;

        // clear previous selection
        document.querySelectorAll('.slot.selected').forEach(el => el.classList.remove('selected'));
        card.classList.add('selected');

        // fill hidden input & enable button
        slotInput.value = card.dataset.slot;
        proceedBtn.disabled = false;

        // scroll to form (mobile)
        document.getElementById('confirmForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    }
  </script>
</body>
</html>