<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$db   = "healthreminder";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die("Koneksi gagal: " . $conn->connect_error);
}

// Ambil data form
$identifier = $_POST['identifier'];
$password   = $_POST['password'];

// Cari user by email, username, or phone
$sql = "
  SELECT *
    FROM users
   WHERE email        = ?
      OR username     = ?
      OR phone_number = ?
  LIMIT 1
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
  echo "PREPARE_FAILED";
  exit();
}

$stmt->bind_param("sss", $identifier, $identifier, $identifier);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
  $user = $result->fetch_assoc();

  if (password_verify($password, $user['password'])) {
    $_SESSION['user_id']  = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    echo "success";
  } else {
    echo "wrong_password";
  }
} else {
  echo "not_found";
}
?>
