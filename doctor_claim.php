<?php
require_once "session.php";
require_once "db.php";
require_once "helpers.php";
require_login();
if (($_SESSION['role'] ?? '') !== 'doctor') redirect_role($_SESSION['role']);

$action         = $_POST['action'] ?? '';
$appointment_id = (int)($_POST['appointment_id'] ?? 0);

if (!$appointment_id || !in_array($action, ['accept','decline'], true)) {
  $_SESSION['flash_error'] = "Invalid request.";
  header("Location: doctor.php"); exit;
}

/* Current doctor */
$dStmt = $pdo->prepare("SELECT doctor_ID FROM doctor WHERE user_id=? LIMIT 1");
$dStmt->execute([$_SESSION['user_id']]);
$doctor_id = (int)($dStmt->fetch()['doctor_ID'] ?? 0);
if (!$doctor_id) { header("Location: login.php"); exit; }

try {
  $pdo->beginTransaction();

  $q = $pdo->prepare("
    SELECT a.appointment_id, a.status, s.slot_id, s.doctor_id, s.status AS slot_status
    FROM appointment a
    JOIN schedule_slot s ON s.slot_id = a.slot_id
    WHERE a.appointment_id = ? FOR UPDATE
  ");
  $q->execute([$appointment_id]);
  $row = $q->fetch();

  if (!$row) throw new Exception("Appointment not found.");
  if ($row['status'] !== 'pending') throw new Exception("Only pending requests can be processed.");
  if ((int)$row['doctor_id'] !== $doctor_id) throw new Exception("This request belongs to another doctor.");

  if ($action === 'accept') {
    if (!in_array($row['slot_status'], ['available','held'], true)) throw new Exception("Slot not free.");
    $pdo->prepare("UPDATE appointment SET status='confirmed', confirmed_at=NOW() WHERE appointment_id=?")->execute([$appointment_id]);
    $pdo->prepare("UPDATE schedule_slot SET status='booked' WHERE slot_id=?")->execute([(int)$row['slot_id']]);
    $_SESSION['flash_success'] = "✅ Appointment accepted.";
  } else {
    $pdo->prepare("UPDATE appointment SET status='canceled', canceled_at=NOW() WHERE appointment_id=?")->execute([$appointment_id]);
    $pdo->prepare("UPDATE schedule_slot SET status='available' WHERE slot_id=?")->execute([(int)$row['slot_id']]);
    $_SESSION['flash_success'] = "❎ Appointment declined.";
  }

  $pdo->commit();
} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  $_SESSION['flash_error'] = $e->getMessage();
}

header("Location: doctor.php");
exit;
