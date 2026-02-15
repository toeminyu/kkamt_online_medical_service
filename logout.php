<?php
require_once __DIR__ . '/session.php';
destroy_session_completely();
header("Location: login.php");
exit;
