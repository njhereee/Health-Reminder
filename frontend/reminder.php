<?php
session_start();
if (!isset($_SESSION['username'])) {
  header("Location: login.html");
  exit();
}

// Koneksi database
include '../database/db.php';

function send_sms_reminder($to, $message) {
    // Buang semua non-digit (mis. +, spasi, -, dll)
    $to = preg_replace('/\D/', '', $to);
    // Pastikan leading 0 diganti ke 62
    if (strpos($to, '0') === 0) {
        $to = '62' . substr($to, 1);
    }

    $token       = '528c72957455980e69a81d377d87846d';
    $msg_encoded = urlencode($message);
    $url         = "https://websms.co.id/api/smsgateway?token={$token}&to={$to}&msg={$msg_encoded}";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    $result = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log('cURL Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("API Response [{$httpCode}]: {$result}");
    $resp = json_decode($result, true);
    return isset($resp['status']) && $resp['status'] === 'success';
}


$username = $_SESSION['username'];
$stmtUser = $conn->prepare("SELECT u.user_id, u.phone_number FROM users u WHERE u.username = ?");
$stmtUser->bind_param("s", $username);
$stmtUser->execute();
$stmtUser->bind_result($user_id, $phone_number);
$stmtUser->fetch();
$stmtUser->close();

$today = date('Y-m-d');
$now = date('H:i:s');
$sqlRem = "SELECT reminder_id, title, description, remind_time, sms_phone FROM reminders WHERE user_id = ? AND remind_date = ? AND is_done = 0 AND sms_sent = 0";
$stmtRem = $conn->prepare($sqlRem);
$stmtRem->bind_param("is", $user_id, $today);
$stmtRem->execute();
$resRem = $stmtRem->get_result();
while ($row = $resRem->fetch_assoc()) {
    if ($row['remind_time'] <= $now) {
        $target_phone = !empty($row['sms_phone']) ? $row['sms_phone'] : $phone_number;
        $msg = "Pengingat: " . $row['title'] . " pada " . $row['remind_time'];
        
        if (send_sms_reminder($target_phone, $msg)) {
            // Update SMS sent status and mark as completed
            $upd = $conn->prepare("UPDATE reminders SET sms_sent = 1, is_done = 1 WHERE reminder_id = ?");
            $upd->bind_param("i", $row['reminder_id']);
            $upd->execute();
            $upd->close();
        }
    }
}
$stmtRem->close();

// Get user_id dari session
$username = $_SESSION['username'];
$sql = "SELECT user_id FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_id = $user['user_id'];
$stmt->close();

// Handle delete request
if (isset($_POST['delete_id'])) {
  $delete_id = $_POST['delete_id'];
  $sql = "DELETE FROM reminders WHERE reminder_id = ? AND user_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ii", $delete_id, $user_id);
  
  if ($stmt->execute()) {
    $_SESSION['reminder_success'] = "Pengingat berhasil dihapus!";
  } else {
    $_SESSION['reminder_error'] = "Error menghapus pengingat: " . $stmt->error;
  }
  $stmt->close();
  
  header("Location: " . $_SERVER['PHP_SELF']);
  exit();
}

// Handle status toggle
if (isset($_POST['toggle_status'])) {
  $reminder_id = $_POST['reminder_id'];
  $new_status = $_POST['new_status'];
  
  $sql = "UPDATE reminders SET is_done = ? WHERE reminder_id = ? AND user_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("iii", $new_status, $reminder_id, $user_id);
  
  if ($stmt->execute()) {
    $_SESSION['reminder_success'] = "Status pengingat berhasil diubah!";
  } else {
    $_SESSION['reminder_error'] = "Error mengubah status: " . $stmt->error;
  }
  $stmt->close();
  
  header("Location: " . $_SERVER['PHP_SELF']);
  exit();
}

// Get reminder for editing
$edit_reminder = null;
if (isset($_GET['edit'])) {
  $edit_id = $_GET['edit'];
  $sql = "SELECT * FROM reminders WHERE reminder_id = ? AND user_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ii", $edit_id, $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $edit_reminder = $result->fetch_assoc();
  $stmt->close();
}

// Proses form ketika disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['delete_id']) && !isset($_POST['toggle_status'])) {
  $title = $_POST['title'];
  $description = $_POST['description'];
  $remind_date = $_POST['remind_date'];
  $remind_time = !empty($_POST['remind_time']) ? $_POST['remind_time'] : NULL;
  $sms_enabled = isset($_POST['sms_enabled']) ? 1 : 0;
  $sms_phone = '';
  
  // Determine SMS phone number
  if ($sms_enabled) {
    if (isset($_POST['use_my_number']) && $_POST['use_my_number'] === '1') {
      $sms_phone = $phone_number; // Use user's registered phone number
    } else if (!empty($_POST['custom_phone'])) {
      $sms_phone = $_POST['custom_phone']; // Use custom phone number
    }
  }
  
  if (isset($_POST['edit_id']) && !empty($_POST['edit_id'])) {
    // Update existing reminder
    $edit_id = $_POST['edit_id'];
    $sql = "UPDATE reminders SET title = ?, description = ?, remind_date = ?, remind_time = ?, sms_enabled = ?, sms_phone = ?
            WHERE reminder_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssisii", $title, $description, $remind_date, $remind_time, $sms_enabled, $sms_phone, $edit_id, $user_id);
    
    if ($stmt->execute()) {
      $_SESSION['reminder_success'] = "Pengingat berhasil diperbarui!";
      
      // Send immediate SMS if enabled and reminder is for today
      if ($sms_enabled && !empty($sms_phone) && $remind_date === date('Y-m-d')) {
        $msg = "Pengingat baru: " . $title . " pada " . ($remind_time ? $remind_time : 'hari ini');
        if (send_sms_reminder($sms_phone, $msg)) {
          $_SESSION['reminder_success'] .= " SMS pengingat telah dikirim.";
        }
      }
    } else {
      $_SESSION['reminder_error'] = "Error: " . $stmt->error;
    }
  } else {
    // Insert new reminder (default status is not done)
    $is_done = 0;
    $sms_sent = 0;
    $sql = "INSERT INTO reminders (user_id, title, description, remind_date, remind_time, is_done, sms_enabled, sms_phone, sms_sent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssiisi", $user_id, $title, $description, $remind_date, $remind_time, $is_done, $sms_enabled, $sms_phone, $sms_sent);
    
    if ($stmt->execute()) {
      $_SESSION['reminder_success'] = "Pengingat baru berhasil ditambahkan!";
      
      // Send immediate SMS if enabled and reminder is for today
      if ($sms_enabled && !empty($sms_phone) && $remind_date === date('Y-m-d')) {
        $msg = "Pengingat baru: " . $title . " pada " . ($remind_time ? $remind_time : 'hari ini');
        if (send_sms_reminder($sms_phone, $msg)) {
          $_SESSION['reminder_success'] .= " SMS pengingat telah dikirim.";
          // Update SMS sent status
          $reminder_id = $conn->insert_id;
          $upd = $conn->prepare("UPDATE reminders SET sms_sent = 1 WHERE reminder_id = ?");
          $upd->bind_param("i", $reminder_id);
          $upd->execute();
          $upd->close();
        }
      }
    } else {
      $_SESSION['reminder_error'] = "Error: " . $stmt->error;
    }
  }
  
  $stmt->close();
  
  // Redirect untuk mencegah form resubmission
  header("Location: " . $_SERVER['PHP_SELF']);
  exit();
}

// Fetch existing reminders for the user
$sql = "SELECT * FROM reminders WHERE user_id = ? ORDER BY remind_date ASC, remind_time ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reminders = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Pengingat Kesehatan - HealthReminder</title>
  <link rel="stylesheet" href="css/style.css" />
  <link rel="stylesheet" href="css/reminder.css">
  <script src="https://kit.fontawesome.com/6a3d0e9851.js" crossorigin="anonymous"></script>
  <style>
    .sms-section {
      border: 2px solid #e3f2fd;
      border-radius: 12px;
      padding: 20px;
      margin: 20px 0;
      background: linear-gradient(135deg, #f8f9ff 0%, #e8f4fd 100%);
      transition: all 0.3s ease;
    }
    
    .sms-section.active {
      border-color: #2196f3;
      box-shadow: 0 4px 15px rgba(33, 150, 243, 0.1);
    }
    
    .sms-header {
      display: flex;
      align-items: center;
      margin-bottom: 15px;
    }
    
    .sms-toggle {
      display: flex;
      align-items: center;
      cursor: pointer;
      font-weight: 500;
      color: #1976d2;
    }
    
    .sms-toggle input[type="checkbox"] {
      margin-right: 10px;
      transform: scale(1.2);
    }
    
    .phone-options {
      display: none;
      margin-top: 15px;
      padding-top: 15px;
      border-top: 1px solid #e0e0e0;
    }
    
    .phone-options.show {
      display: block;
      animation: slideDown 0.3s ease;
    }
    
    @keyframes slideDown {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .phone-option {
      display: flex;
      align-items: center;
      margin-bottom: 12px;
      padding: 10px;
      border-radius: 8px;
      transition: background-color 0.2s ease;
    }
    
    .phone-option:hover {
      background-color: rgba(33, 150, 243, 0.05);
    }
    
    .phone-option input[type="radio"] {
      margin-right: 10px;
    }
    
    .phone-option label {
      flex: 1;
      cursor: pointer;
      display: flex;
      align-items: center;
    }
    
    .phone-display {
      font-weight: 500;
      color: #1976d2;
      margin-left: 5px;
    }
    
    .custom-phone-input {
      margin-top: 10px;
      display: none;
    }
    
    .custom-phone-input.show {
      display: block;
      animation: slideDown 0.3s ease;
    }
    
    .reminder-sms-info {
      display: flex;
      align-items: center;
      margin-top: 5px;
      font-size: 0.85em;
      color: #666;
    }
    
    .reminder-sms-info i {
      margin-right: 5px;
      color: #2196f3;
    }
    
    .status-completed {
      background: linear-gradient(135deg, #4caf50, #45a049);
      color: white;
    }
    
    .status-sent {
      background: linear-gradient(135deg, #ff9800, #f57c00);
      color: white;
    }
  </style>
</head>

<body>
  <!-- tombol toggle -->
  <div class="sidebar-toggle" id="toggle-btn">
    <i class="fas fa-bars"></i>
  </div>

  <?php include 'sidebar.php' ?>
  <!-- overlay -->
  <div class="overlay" id="overlay"></div>

  <!-- konten utama -->
  <main id="dashboard-content">
    <!-- Header Section -->
    <div class="dashboard-header">
      <h1>Pengingat Kesehatan</h1>
      <p class="subtitle">Kelola pengingat kesehatan anda untuk hidup lebih teratur</p>
    </div>

    <!-- Success Message -->
    <?php if (isset($_SESSION['reminder_success']) && $_SESSION['reminder_success']): ?>
    <div class="alert alert-success">
      <i class="fas fa-check-circle alert-icon"></i>
      <div>
        <strong>Berhasil!</strong> <?php echo $_SESSION['reminder_success']; ?>
      </div>
    </div>
    <?php 
    // Clear the session variable
    unset($_SESSION['reminder_success']); 
    endif; 
    ?>

    <!-- Error Message -->
    <?php if (isset($_SESSION['reminder_error'])): ?>
    <div class="alert alert-danger">
      <i class="fas fa-exclamation-circle alert-icon"></i>
      <div>
        <strong>Error!</strong> <?php echo $_SESSION['reminder_error']; ?>
      </div>
    </div>
    <?php 
    // Clear the session variable
    unset($_SESSION['reminder_error']); 
    endif; 
    ?>

    <!-- Reminders List -->
    <div class="reminders-list">
      <div class="reminders-header">
        <h2><i class="fas fa-bell" style="margin-right: 15px;"></i> Daftar Pengingat</h2>
        <button id="showFormBtn" class="add-reminder-btn">
          <i class="fas fa-plus"></i> Tambah Pengingat
        </button>
      </div>

      <?php if ($reminders->num_rows > 0): ?>
        <?php while($row = $reminders->fetch_assoc()): ?>
          <div class="reminder-item <?php echo ($row['is_done'] ? 'reminder-done' : ''); ?>">
            <div class="reminder-content">
              <div class="reminder-title"><?php echo htmlspecialchars($row['title']); ?></div>
              <?php if (!empty($row['description'])): ?>
                <div class="reminder-desc"><?php echo htmlspecialchars($row['description']); ?></div>
              <?php endif; ?>
              <div class="reminder-info">
                <div class="reminder-date">
                  <i class="fas fa-calendar-alt"></i> 
                  <?php 
                    $date = new DateTime($row['remind_date']);
                    echo $date->format('d M Y'); 
                  ?>
                </div>
                <?php if (!empty($row['remind_time'])): ?>
                  <div class="reminder-time">
                    <i class="fas fa-clock"></i> 
                    <?php 
                      $time = new DateTime($row['remind_time']);
                      echo $time->format('H:i'); 
                    ?>
                  </div>
                <?php endif; ?>
              </div>
              <?php if ($row['sms_enabled']): ?>
                <div class="reminder-sms-info">
                  <i class="fas fa-sms"></i>
                  SMS akan dikirim ke: <?php echo htmlspecialchars(!empty($row['sms_phone']) ? $row['sms_phone'] : $phone_number); ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="reminder-actions">
              <div class="reminder-status">
                <form method="POST" style="display: inline;">
                  <input type="hidden" name="reminder_id" value="<?php echo $row['reminder_id']; ?>">
                  <input type="hidden" name="new_status" value="<?php echo $row['is_done'] ? 0 : 1; ?>">
                  <button type="submit" name="toggle_status" class="status-toggle-btn" style="border:none;" title="<?php echo $row['is_done'] ? 'Tandai belum selesai' : 'Tandai selesai'; ?>">
                    <span class="status-badge <?php 
                      if ($row['is_done']) {
                        echo 'status-completed';
                      } elseif ($row['sms_sent']) {
                        echo 'status-sent';
                      } else {
                        echo 'status-pending';
                      }
                    ?>">
                      <i class="fas <?php 
                        if ($row['is_done']) {
                          echo 'fa-check-circle';
                        } elseif ($row['sms_sent']) {
                          echo 'fa-paper-plane';
                        } else {
                          echo 'fa-clock';
                        }
                      ?>"></i>
                      <?php 
                        if ($row['is_done']) {
                          echo 'Selesai';
                        } elseif ($row['sms_sent']) {
                          echo 'Terkirim';
                        } else {
                          echo 'Pending';
                        }
                      ?>
                    </span>
                  </button>
                </form>
              </div>
              <a href="?edit=<?php echo $row['reminder_id']; ?>" class="action-btn btn-edit" title="Edit">
                <i class="fas fa-edit"></i>
              </a>
              <form method="POST" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pengingat ini?')">
                <input type="hidden" name="delete_id" value="<?php echo $row['reminder_id']; ?>">
                <button type="submit" class="action-btn btn-delete" title="Hapus">
                  <i class="fas fa-trash-alt"></i>
                </button>
              </form>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="empty-reminders">
          <div class="empty-icon">
            <i class="fas fa-bell-slash"></i>
          </div>
          <p>Belum ada pengingat. Klik "Tambah Pengingat" untuk mulai membuat pengingat baru.</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Form Container -->
    <div id="formContainer" class="form-container" <?php echo ($edit_reminder || isset($_GET['edit'])) ? 'style="display: block;"' : ''; ?>>
      <button id="closeFormBtn" class="close-form">
        <i class="fas fa-times"></i>
      </button>
      <h2>
        <i class="fas <?php echo $edit_reminder ? 'fa-edit' : 'fa-plus-circle'; ?>" style="margin-right: 10px;"></i> 
        <?php echo $edit_reminder ? 'Edit Pengingat' : 'Buat Pengingat Baru'; ?>
      </h2>
      
      <form id="reminderForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <?php if ($edit_reminder): ?>
          <input type="hidden" name="edit_id" value="<?php echo $edit_reminder['reminder_id']; ?>">
        <?php endif; ?>
        
        <!-- Quick Categories -->
        <div class="category-pills">
          <div class="category-pill" data-category="Obat">
            <i class="fas fa-pills"></i> Obat
          </div>
          <div class="category-pill" data-category="Dokter">
            <i class="fas fa-user-md"></i> Dokter
          </div>
          <div class="category-pill" data-category="Olahraga">
            <i class="fas fa-running"></i> Olahraga
          </div>
          <div class="category-pill" data-category="Vitamin">
            <i class="fas fa-capsules"></i> Vitamin
          </div>
          <div class="category-pill" data-category="Lainnya">
            <i class="fas fa-plus"></i> Lainnya
          </div>
        </div>
        
        <div class="form-group">
          <label for="title">Judul Pengingat <span class="required">*</span></label>
          <input 
            type="text" 
            id="title" 
            name="title" 
            class="form-control"
            maxlength="100" 
            required 
            placeholder="Contoh: Minum Obat, Check-up ke Dokter"
            value="<?php echo $edit_reminder ? htmlspecialchars($edit_reminder['title']) : ''; ?>"
          >
        </div>
        
        <div class="form-group">
          <label for="description">Deskripsi</label>
          <textarea 
            id="description" 
            name="description" 
            class="form-control"
            placeholder="Tambahkan detail pengingat di sini..."
          ><?php echo $edit_reminder ? htmlspecialchars($edit_reminder['description']) : ''; ?></textarea>
        </div>
        
        <div class="form-row">
          <div class="form-group date-time-container">
            <label for="remindDate">Tanggal</label>
            <input 
              type="date" 
              id="remindDate" 
              name="remind_date" 
              class="form-control"
              required
              value="<?php echo $edit_reminder ? $edit_reminder['remind_date'] : ''; ?>"
            >
          </div>
          
          <div class="form-group date-time-container">
            <label for="remindTime">Waktu</label>
            <input 
              type="time" 
              id="remindTime" 
              name="remind_time"
              class="form-control"
              value="<?php echo $edit_reminder ? $edit_reminder['remind_time'] : ''; ?>"
            >
          </div>
        </div>
        
        <!-- SMS Section -->
        <div class="sms-section" id="smsSection">
          <div class="sms-header">
            <label class="sms-toggle">
              <input type="checkbox" name="sms_enabled" id="smsEnabled" 
                     <?php echo ($edit_reminder && $edit_reminder['sms_enabled']) ? 'checked' : ''; ?>>
              <i class="fas fa-sms" style="margin-right: 8px;"></i>
              Kirim pengingat via SMS
            </label>
          </div>
          
          <div class="phone-options" id="phoneOptions">
            <div class="phone-option">
              <input type="radio" name="phone_choice" id="useMyNumber" value="my_number" checked>
              <input type="hidden" name="use_my_number" id="useMyNumberHidden" value="0">
              <label for="useMyNumber">
                <i class="fas fa-user" style="margin-right: 8px;"></i>
                Gunakan nomor saya
                <span class="phone-display">(<?php echo htmlspecialchars($phone_number); ?>)</span>
              </label>
            </div>
            
            <div class="phone-option">
              <input type="radio" name="phone_choice" id="useCustomNumber" value="custom_number"
                     <?php echo ($edit_reminder && !empty($edit_reminder['sms_phone']) && $edit_reminder['sms_phone'] !== $phone_number) ? 'checked' : ''; ?>>
              <label for="useCustomNumber">
                <i class="fas fa-phone" style="margin-right: 8px;"></i>
                Gunakan nomor lain
              </label>
            </div>
            
            <div class="custom-phone-input" id="customPhoneInput">
              <input 
                type="tel" 
                name="custom_phone" 
                id="customPhone"
                class="form-control"
                placeholder="Masukkan nomor telepon (contoh: 08123456789)"
                pattern="[0-9]{10,15}"
                value="<?php echo ($edit_reminder && !empty($edit_reminder['sms_phone']) && $edit_reminder['sms_phone'] !== $phone_number) ? htmlspecialchars($edit_reminder['sms_phone']) : ''; ?>"
              >
              <small style="color: #666; font-size: 0.85em;">
                Format: 08xxxxxxxxxx atau 62xxxxxxxxxx
              </small>
            </div>
          </div>
        </div>
        
        <div class="action-buttons">
          <button type="submit" class="btn-primary">
            <i class="fas fa-save"></i> <?php echo $edit_reminder ? 'Update Pengingat' : 'Simpan Pengingat'; ?>
          </button>
          <button type="button" class="btn-secondary" id="cancelBtn">
            <i class="fas fa-times"></i> Batal
          </button>
        </div>
      </form>
    </div>
  </main>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Toggle sidebar functionality
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('overlay');
      const toggleBtn = document.getElementById('toggle-btn');
      
      function toggleSidebar() {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
        toggleBtn.classList.toggle('rotate');
      }
      
      toggleBtn.addEventListener('click', toggleSidebar);
      overlay.addEventListener('click', toggleSidebar);
      
      // SMS functionality
      const smsEnabled = document.getElementById('smsEnabled');
      const smsSection = document.getElementById('smsSection');
      const phoneOptions = document.getElementById('phoneOptions');
      const useMyNumber = document.getElementById('useMyNumber');
      const useCustomNumber = document.getElementById('useCustomNumber');
      const customPhoneInput = document.getElementById('customPhoneInput');
      const useMyNumberHidden = document.getElementById('useMyNumberHidden');
      
      function toggleSmsOptions() {
        if (smsEnabled.checked) {
          smsSection.classList.add('active');
          phoneOptions.classList.add('show');
        } else {
          smsSection.classList.remove('active');
          phoneOptions.classList.remove('show');
        }
      }
      
      function toggleCustomPhone() {
        if (useCustomNumber.checked) {
          customPhoneInput.classList.add('show');
          useMyNumberHidden.value = '0';
        } else {
          customPhoneInput.classList.remove('show');
          useMyNumberHidden.value = '1';
        }
      }
      
      smsEnabled.addEventListener('change', toggleSmsOptions);
      useMyNumber.addEventListener('change', toggleCustomPhone);
      useCustomNumber.addEventListener('change', toggleCustomPhone);
      
      // Initialize SMS options based on edit data
      <?php if ($edit_reminder): ?>
      if (<?php echo $edit_reminder['sms_enabled'] ? 'true' : 'false'; ?>) {
        toggleSmsOptions();
        <?php if (!empty($edit_reminder['sms_phone']) && $edit_reminder['sms_phone'] !== $phone_number): ?>
        useCustomNumber.checked = true;
        toggleCustomPhone();
        <?php endif; ?>
      }
      <?php endif; ?>
      
      // Form toggle functionality
      const formContainer = document.getElementById('formContainer');
      const showFormBtn = document.getElementById('showFormBtn');
      const closeFormBtn = document.getElementById('closeFormBtn');
      const cancelBtn = document.getElementById('cancelBtn');
      
      showFormBtn.addEventListener('click', function() {
        // Clear the URL parameter and reset the form if opening for new reminder
        if (window.location.search.includes('edit=')) {
          window.location.href = window.location.pathname;
          return;
        }
        
        formContainer.style.display = 'block';
        // Add animation
        formContainer.style.opacity = '0';
        formContainer.style.transform = 'translateY(20px)';
        setTimeout(() => {
          formContainer.style.opacity = '1';
          formContainer.style.transform = 'translateY(0)';
          formContainer.style.transition = 'all 0.4s ease';
        }, 10);
        
        // Scroll to form
        formContainer.scrollIntoView({ behavior: 'smooth' });
      });
      
      function hideForm() {
        formContainer.style.opacity = '0';
        formContainer.style.transform = 'translateY(20px)';
        setTimeout(() => {
          formContainer.style.display = 'none';
          // Clear URL parameters when closing form
          if (window.location.search.includes('edit=')) {
            window.location.href = window.location.pathname;
          }
        }, 400);
      }
      
      closeFormBtn.addEventListener('click', hideForm);
      cancelBtn.addEventListener('click', hideForm);
      
      // Set default date to today only for new reminders
      <?php if (!$edit_reminder): ?>
      const today = new Date().toISOString().split('T')[0];
      document.getElementById('remindDate').value = today;
      <?php endif; ?>
      
      // Category pills functionality
      const categoryPills = document.querySelectorAll('.category-pill');
      categoryPills.forEach(pill => {
        pill.addEventListener('click', function() {
          // Remove active class from all pills
          categoryPills.forEach(p => p.classList.remove('active'));
          // Add active class to clicked pill
          this.classList.add('active');
          
          // Set the title based on the selected category (only for new reminders)
          <?php if (!$edit_reminder): ?>
          const categoryName = this.getAttribute('data-category');
          const titleInput = document.getElementById('title');
          
          // Only update if title is empty or has a default value
          if (!titleInput.value || titleInput.value === "Minum Obat" || 
              titleInput.value === "Kunjungan Dokter" || 
              titleInput.value === "Olahraga Rutin" || 
              titleInput.value === "Konsumsi Vitamin" || 
              titleInput.value === "Pengingat Kesehatan") {
              
            switch(categoryName) {
              case "Obat":
                titleInput.value = "Minum Obat";
                break;
              case "Dokter":
                titleInput.value = "Kunjungan Dokter";
                break;
              case "Olahraga":
                titleInput.value = "Olahraga Rutin";
                break;
              case "Vitamin":
                titleInput.value = "Konsumsi Vitamin"; 
                break;
              case "Lainnya":
                titleInput.value = "Pengingat Kesehatan";
                break;
            }
          }
          <?php endif; ?>
        });
      });
      
      // Phone number validation
      const customPhone = document.getElementById('customPhone');
      if (customPhone) {
        customPhone.addEventListener('input', function() {
          let value = this.value.replace(/\D/g, ''); // Remove non-digits
          
          // Format phone number
          if (value.startsWith('62')) {
            // International format
            this.value = value;
          } else if (value.startsWith('0')) {
            // National format
            this.value = value;
          } else if (value.length > 0) {
            // Add 0 prefix if missing
            this.value = '0' + value;
          }
          
          // Validate length
          if (this.value.length < 10 || this.value.length > 15) {
            this.setCustomValidity('Nomor telepon harus 10-15 digit');
          } else {
            this.setCustomValidity('');
          }
        });
      }
      
      // Form validation
      const reminderForm = document.getElementById('reminderForm');
      reminderForm.addEventListener('submit', function(e) {
        if (smsEnabled.checked && useCustomNumber.checked) {
          const phoneValue = customPhone.value.trim();
          if (!phoneValue) {
            e.preventDefault();
            alert('Silakan masukkan nomor telepon atau pilih "Gunakan nomor saya"');
            customPhone.focus();
            return false;
          }
          
          // Validate phone format
          const phoneRegex = /^(0[0-9]{9,13}|62[0-9]{9,13})$/;
          if (!phoneRegex.test(phoneValue)) {
            e.preventDefault();
            alert('Format nomor telepon tidak valid. Gunakan format: 08xxxxxxxxxx atau 62xxxxxxxxxx');
            customPhone.focus();
            return false;
          }
        }
      });
      
      // Add animations to form elements
      function animateFormElements() {
        const formElements = document.querySelectorAll('.form-group, .category-pills, .sms-section');
        formElements.forEach((element, index) => {
          setTimeout(() => {
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
          }, 100 * index);
        });
      }
      
      // Add initial styles for animation
      const formElements = document.querySelectorAll('.form-group, .category-pills, .sms-section');
      formElements.forEach(element => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        element.style.transition = 'all 0.5s ease';
      });
      
      // Auto-hide success message after 5 seconds
      const successAlert = document.querySelector('.alert-success');
      if (successAlert) {
        setTimeout(() => {
          successAlert.style.opacity = '0';
          successAlert.style.height = '0';
          successAlert.style.padding = '0';
          successAlert.style.margin = '0';
          successAlert.style.transition = 'all 0.5s ease';
        }, 5000);
      }

      // Start animations when form is shown
      showFormBtn.addEventListener('click', function() {
        setTimeout(animateFormElements, 300);
      });
      
      // If editing, show animations immediately
      <?php if ($edit_reminder): ?>
      setTimeout(animateFormElements, 100);
      <?php endif; ?>
      
      // Initialize SMS options on page load
      toggleSmsOptions();
      toggleCustomPhone();
    });
  </script>
</body>
</html>