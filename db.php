
<?php
// Single-file DB connector (PDO)
$DB_HOST = "localhost";
$DB_NAME = "kkamt";
$DB_USER = "root";
$DB_PASS = ""; // set your password

try {
  $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) {
  http_response_code(500);
  echo "<pre>Database connection failed.</pre>";
  exit;
}
