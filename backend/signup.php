<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "healthreminder";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    // Log error database, jangan tampilkan ke pengguna akhir
    // error_log("Koneksi gagal: " . $conn->connect_error);
    die("Terjadi kesalahan sistem. Silakan coba lagi nanti.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data form dan sanitasi dasar
    $username         = trim($_POST['username'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $phone_input      = trim($_POST['phone_number'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // --- Validasi Input ---
    if (empty($username) || empty($email) || empty($phone_input) || empty($password) || empty($confirm_password)) {
        echo "Semua kolom wajib diisi!";
        exit;
    }

    // Validasi email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Format email tidak valid!";
        exit;
    }

    // Validasi password cocok
    if ($password !== $confirm_password) {
        echo "Password tidak cocok!";
        exit;
    }

    // Normalisasi & validasi nomor telepon
    $phone_clean = preg_replace('/[^\d\+]/', '', $phone_input); // Hanya digit dan '+'
    $phone = "";
    if (preg_match('/^0\d{9,11}$/', $phone_clean)) {
        $phone = '+62' . substr($phone_clean, 1);
    } elseif (preg_match('/^\+62\d{9,11}$/', $phone_clean)) {
        $phone = $phone_clean;
    } else {
        echo "Format nomor telepon tidak valid. Harus 10–12 digit, mulai 0 atau +62.";
        exit;
    }

    // --- Cek Duplikasi (Username, Email, No. Telepon) ---
    $check_sql = "SELECT COUNT(*) FROM users WHERE username = ? OR email = ? OR phone_number = ?";
    $check_stmt = $conn->prepare($check_sql);
    if (!$check_stmt) {
        // error_log("PREPARE check_sql FAILED: " . $conn->error);
        echo "Terjadi kesalahan saat memeriksa data. Silakan coba lagi.";
        exit;
    }
    $check_stmt->bind_param("sss", $username, $email, $phone);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $row = $check_result->fetch_row();
    if ($row[0] > 0) {
        echo "Username, email, atau nomor telepon sudah terdaftar!";
        exit;
    }
    $check_stmt->close();

    // --- Enkripsi password ---
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // --- Simpan ke database ---
    $sql = "INSERT INTO users (username, email, password, phone_number) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // error_log("PREPARE INSERT FAILED: " . $conn->error);
        echo "Terjadi kesalahan saat menyiapkan pendaftaran.";
        exit;
    }
    $stmt->bind_param("ssss", $username, $email, $hashed_password, $phone);

    if ($stmt->execute()) {
        // Redirect to login page
        // Asumsi struktur: /healthreminder/backend/signup.php dan /healthreminder/frontend/login.html
        header("Location: ../frontend/login.html");
        exit();
    } else {
        // error_log("Gagal daftar: " . $conn->error);
        echo "Terjadi kesalahan saat pendaftaran. Silakan coba lagi nanti.";
    }

    $stmt->close();

} else {
    // Jika bukan POST request, arahkan kembali ke halaman pendaftaran
    header("Location: ../frontend/signup.html");
    exit();
}

$conn->close();
?>