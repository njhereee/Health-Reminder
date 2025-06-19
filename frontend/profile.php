<?php
session_start();
if (!isset($_SESSION['username'])) {
  header("Location: login.html");
  exit();
}

// Include language file
$language = $_SESSION['language'] ?? 'id';
include "lang/{$language}.php";

// Database connection
$host = 'localhost';
$dbname = 'healthreminder';
$dbuser = 'root';
$dbpass = '';

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$username = $_SESSION['username'];

// Get user profile and settings data
$sql = "SELECT u.user_id, u.username, u.email, u.phone_number, u.profile_image, u.created_at, 
        COALESCE(s.theme, 'light') as theme, COALESCE(s.language, 'id') as language 
        FROM users u
        LEFT JOIN user_settings s ON u.user_id = s.user_id
        WHERE u.username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
  $user = $result->fetch_assoc();
  
  // If user has no settings record, create one with defaults
  if (!isset($user['theme']) || !isset($user['language'])) {
    $check_sql = "SELECT * FROM user_settings WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $user['user_id']);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows === 0) {
      $insert_sql = "INSERT INTO user_settings (user_id, theme, language) VALUES (?, 'light', 'id')";
      $insert_stmt = $conn->prepare($insert_sql);
      $insert_stmt->bind_param("i", $user['user_id']);
      $insert_stmt->execute();
    }
    
    $user['theme'] = $user['theme'] ?? 'light';
    $user['language'] = $user['language'] ?? 'id';
  }
  
  // Set session language for future use
  $_SESSION['language'] = $user['language'];
} else {
  echo "User not found.";
  exit();
}

// Handle form submissions
$message = '';
$error = '';

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_image'])) {
  $target_dir = "uploads/profiles/";
  
  // Create directory if it doesn't exist
  if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
  }
  
  $imageFileType = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
  $target_file = $target_dir . $user['user_id'] . "_" . time() . "." . $imageFileType;
  
  // Check if image file is a actual image or fake image
  $check = getimagesize($_FILES["profile_image"]["tmp_name"]);
  if($check !== false) {
    // Check file size (5MB max)
    if ($_FILES["profile_image"]["size"] <= 5000000) {
      // Allow certain file formats
      if($imageFileType == "jpg" || $imageFileType == "png" || $imageFileType == "jpeg" || $imageFileType == "gif" ) {
        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
          // Delete old image if exists
          if ($user['profile_image'] && file_exists($user['profile_image'])) {
            unlink($user['profile_image']);
          }
          
          // Update database
          $update_sql = "UPDATE users SET profile_image = ? WHERE user_id = ?";
          $update_stmt = $conn->prepare($update_sql);
          $update_stmt->bind_param("si", $target_file, $user['user_id']);
          
          if ($update_stmt->execute()) {
            $user['profile_image'] = $target_file;
            $message = '<div class="alert success">' . $lang['profile_image_updated'] . '</div>';
          } else {
            $error = '<div class="alert error">' . $lang['failed_update'] . '</div>';
          }
        } else {
          $error = '<div class="alert error">' . $lang['upload_failed'] . '</div>';
        }
      } else {
        $error = '<div class="alert error">' . $lang['invalid_file_format'] . '</div>';
      }
    } else {
      $error = '<div class="alert error">' . $lang['file_too_large'] . '</div>';
    }
  } else {
    $error = '<div class="alert error">' . $lang['not_an_image'] . '</div>';
  }
}

// Handle profile information update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
  $new_username = trim($_POST['username']);
  $new_email = trim($_POST['email']);
  $new_phone = trim($_POST['phone_number']);
  
  // Validation
  $validation_errors = [];
  
  if (empty($new_username)) {
    $validation_errors[] = $lang['username_required'];
  }
  
  if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
    $validation_errors[] = $lang['valid_email_required'];
  }
  
  if (empty($new_phone)) {
    $validation_errors[] = $lang['phone_required'];
  }
  
  // Check uniqueness only if values are different from current
  if ($new_username !== $user['username']) {
    $check_username = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
    $check_username->bind_param("si", $new_username, $user['user_id']);
    $check_username->execute();
    if ($check_username->get_result()->num_rows > 0) {
      $validation_errors[] = $lang['username_taken'];
    }
  }
  
  if ($new_email !== $user['email']) {
    $check_email = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $check_email->bind_param("si", $new_email, $user['user_id']);
    $check_email->execute();
    if ($check_email->get_result()->num_rows > 0) {
      $validation_errors[] = $lang['email_taken'];
    }
  }
  
  if ($new_phone !== $user['phone_number']) {
    $check_phone = $conn->prepare("SELECT user_id FROM users WHERE phone_number = ? AND user_id != ?");
    $check_phone->bind_param("si", $new_phone, $user['user_id']);
    $check_phone->execute();
    if ($check_phone->get_result()->num_rows > 0) {
      $validation_errors[] = $lang['phone_taken'];
    }
  }
  
  if (empty($validation_errors)) {
    $update_sql = "UPDATE users SET username = ?, email = ?, phone_number = ? WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sssi", $new_username, $new_email, $new_phone, $user['user_id']);
    
    if ($update_stmt->execute()) {
      $user['username'] = $new_username;
      $user['email'] = $new_email;
      $user['phone_number'] = $new_phone;
      $_SESSION['username'] = $new_username;
      $message = '<div class="alert success">' . $lang['profile_updated'] . '</div>';
    } else {
      $error = '<div class="alert error">' . $lang['failed_update'] . '</div>';
    }
  } else {
    $error = '<div class="alert error">' . implode('<br>', $validation_errors) . '</div>';
  }
}

// Handle password change (simplified - no current password required)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
  $new_password = $_POST['new_password'];
  $confirm_password = $_POST['confirm_password'];
  
  if ($new_password === $confirm_password) {
    if (strlen($new_password) >= 6) {
      $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
      $update_pass_sql = "UPDATE users SET password = ? WHERE user_id = ?";
      $update_pass_stmt = $conn->prepare($update_pass_sql);
      $update_pass_stmt->bind_param("si", $new_hash, $user['user_id']);
      
      if ($update_pass_stmt->execute()) {
        $message = '<div class="alert success">' . $lang['password_updated'] . '</div>';
      } else {
        $error = '<div class="alert error">' . $lang['failed_update'] . '</div>';
      }
    } else {
      $error = '<div class="alert error">' . $lang['password_min_length'] . '</div>';
    }
  } else {
    $error = '<div class="alert error">' . $lang['password_mismatch'] . '</div>';
  }
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
  $new_theme = $_POST['theme'];
  $new_language = $_POST['language'];
  
  $update_sql = "INSERT INTO user_settings (user_id, theme, language) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                theme = VALUES(theme), 
                language = VALUES(language)";
  
  $update_stmt = $conn->prepare($update_sql);
  $update_stmt->bind_param("iss", $user['user_id'], $new_theme, $new_language);
  
  if ($update_stmt->execute()) {
    $user['theme'] = $new_theme;
    $user['language'] = $new_language;
    $_SESSION['language'] = $new_language;
    $message = '<div class="alert success">' . $lang['settings_updated'] . '</div>';
    
    // Reload language file if language changed
    if ($new_language !== $language) {
      include "lang/{$new_language}.php";
    }
  } else {
    $error = '<div class="alert error">' . $lang['failed_update'] . '</div>';
  }
}

// Get current time for greeting
$hour = date('H');
if ($hour >= 5 && $hour < 12) {
  $greeting = $lang['good_morning'];
  $greeting_icon = "☀️";
} elseif ($hour >= 12 && $hour < 15) {
  $greeting = $lang['good_afternoon'];
  $greeting_icon = "🌤️";
} elseif ($hour >= 15 && $hour < 18) {
  $greeting = $lang['good_evening'];
  $greeting_icon = "🌅";
} else {
  $greeting = $lang['good_night'];
  $greeting_icon = "🌙";
}
?>
<!DOCTYPE html>
<html lang="<?php echo $user['language']; ?>" data-theme="<?php echo $user['theme']; ?>">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo $lang['profile']; ?> - HealthReminder</title>
  <link rel="stylesheet" href="css/style.css" />
  <link rel="stylesheet" href="css/profile.css">
  <link rel="stylesheet" href="css/themes.css">
  <script src="https://kit.fontawesome.com/6a3d0e9851.js" crossorigin="anonymous"></script>
</head>

<body class="<?php echo $user['theme']; ?>-theme">
  <!-- tombol toggle -->
  <div class="sidebar-toggle" id="toggle-btn">
    <i class="fas fa-bars"></i>
  </div>

  <?php include 'sidebar.php' ?>
  <!-- overlay -->
  <div class="overlay" id="overlay"></div>

  <!-- konten utama -->
  <main id="profile-content">
    <!-- Header Section -->
    <div class="dashboard-header">
      <h1><?php echo $lang['user_profile']; ?></h1>
      <p class="subtitle"><?php echo $lang['manage_account']; ?></p>
    </div>

    <!-- Greeting Section -->
    <div class="greeting-card">
      <div class="greeting-message">
        <span id="greeting-text"><?php echo $greeting; ?></span>, <span class="user-name"><?php echo htmlspecialchars($user['username']); ?></span>
        <span class="greeting-emoji" id="greeting-icon"><?php echo $greeting_icon; ?></span>
      </div>
    </div>

    <?php if ($message): ?>
      <?php echo $message; ?>
    <?php endif; ?>
    
    <?php if ($error): ?>
      <?php echo $error; ?>
    <?php endif; ?>

    <!-- Stats Section -->
    <div class="stats-section">
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-user-clock"></i>
        </div>
        <div class="stat-value"><?php echo date('d', strtotime($user['created_at'])); ?></div>
        <div class="stat-label"><?php echo $lang['day_joined']; ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="stat-value"><?php echo date('M', strtotime($user['created_at'])); ?></div>
        <div class="stat-label"><?php echo $lang['month_joined']; ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-trophy"></i>
        </div>
        <div class="stat-value"><?php echo date('Y', strtotime($user['created_at'])); ?></div>
        <div class="stat-label"><?php echo $lang['year_joined']; ?></div>
      </div>
    </div>

    <!-- Profile & Settings Container -->
    <div class="profile-container">
      <!-- Profile Image Card -->
      <div class="profile-card">
        <div class="profile-header">
          <div class="profile-avatar">
            <?php if ($user['profile_image'] && file_exists($user['profile_image'])): ?>
              <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile Image">
            <?php else: ?>
              <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
            <?php endif; ?>
          </div>
          <div class="profile-title">
            <h2><?php echo htmlspecialchars($user['username']); ?></h2>
            <p><?php echo $lang['member_since']; ?> <?php echo date('d M Y', strtotime($user['created_at'])); ?></p>
          </div>
        </div>

        <!-- Profile Image Upload Form -->
        <form method="POST" enctype="multipart/form-data" class="image-upload-form">
          <div class="form-group">
            <label for="profile_image">
              <i class="fas fa-camera"></i> <?php echo $lang['profile_image']; ?>:
            </label>
            <input type="file" name="profile_image" id="profile_image" accept="image/*" required>
          </div>
          <div class="form-actions">
            <button type="submit" name="upload_image" class="btn btn-secondary">
              <i class="fas fa-upload"></i>
              <?php echo $lang['upload_image']; ?>
            </button>
          </div>
        </form>
      </div>

      <!-- Profile Information Edit Card -->
      <div class="profile-card">
        <h2><i class="fas fa-user-edit"></i><?php echo $lang['edit_profile']; ?></h2>
        <form method="POST" action="">
          <div class="form-group">
            <label for="username">
              <i class="fas fa-user"></i> <?php echo $lang['username']; ?>:
            </label>
            <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
          </div>
          
          <div class="form-group">
            <label for="email">
              <i class="fas fa-envelope"></i> Email:
            </label>
            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
          </div>
          
          <div class="form-group">
            <label for="phone_number">
              <i class="fas fa-phone"></i> <?php echo $lang['phone_number']; ?>:
            </label>
            <input type="tel" name="phone_number" id="phone_number" value="<?php echo htmlspecialchars($user['phone_number']); ?>" required>
          </div>
          
          <div class="form-actions">
            <button type="submit" name="update_profile" class="btn btn-primary">
              <i class="fas fa-save"></i>
              <?php echo $lang['save_changes']; ?>
            </button>
          </div>
        </form>
      </div>

      <!-- Password Change Card -->
      <div class="profile-card">
        <h2><i class="fas fa-lock"></i><?php echo $lang['change_password']; ?></h2>
        <form method="POST" action="">
          <div class="form-group">
            <label for="new_password">
              <i class="fas fa-lock"></i> <?php echo $lang['new_password']; ?>:
            </label>
            <input type="password" name="new_password" id="new_password" required minlength="6">
          </div>
          
          <div class="form-group">
            <label for="confirm_password">
              <i class="fas fa-lock"></i> <?php echo $lang['confirm_password']; ?>:
            </label>
            <input type="password" name="confirm_password" id="confirm_password" required minlength="6">
          </div>
          
          <div class="form-actions">
            <button type="submit" name="change_password" class="btn btn-warning">
              <i class="fas fa-key"></i>
              <?php echo $lang['change_password']; ?>
            </button>
          </div>
        </form>
      </div>

      <!-- Settings Card -->
      <div class="settings-card">
        <h2><i class="fas fa-cog"></i><?php echo $lang['settings']; ?></h2>
        <form method="POST" action="">
          <div class="form-group">
            <label for="theme">
              <i class="fas fa-palette"></i> <?php echo $lang['theme']; ?>:
            </label>
            <select name="theme" id="theme">
              <option value="light" <?php echo $user['theme'] === 'light' ? 'selected' : ''; ?>><?php echo $lang['light_mode']; ?></option>
              <option value="dark" <?php echo $user['theme'] === 'dark' ? 'selected' : ''; ?>><?php echo $lang['dark_mode']; ?></option>
            </select>
          </div>
          
          <div class="form-group">
            <label for="language">
              <i class="fas fa-language"></i> <?php echo $lang['language']; ?>:
            </label>
            <select name="language" id="language">
              <option value="id" <?php echo $user['language'] === 'id' ? 'selected' : ''; ?>>Bahasa Indonesia</option>
              <option value="en" <?php echo $user['language'] === 'en' ? 'selected' : ''; ?>>English</option>
            </select>
          </div>
          
          <div class="form-actions">
            <button type="submit" name="update_settings" class="btn btn-primary">
              <i class="fas fa-save"></i>
              <?php echo $lang['save_changes']; ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </main>

  <script>
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const toggleBtn = document.getElementById('toggle-btn');
    
    // Toggle sidebar & overlay
    function toggleSidebar() {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('active');
      toggleBtn.classList.toggle('rotate');
    }
    
    toggleBtn.addEventListener('click', toggleSidebar);
    overlay.addEventListener('click', toggleSidebar);

    // Update greeting based on current time
    function updateGreeting() {
      const hour = new Date().getHours();
      let greeting, greetingIcon;
      
      if (hour >= 5 && hour < 12) {
        greeting = "<?php echo $lang['good_morning']; ?>";
        greetingIcon = "☀️";
      } else if (hour >= 12 && hour < 15) {
        greeting = "<?php echo $lang['good_afternoon']; ?>";
        greetingIcon = "🌤️";
      } else if (hour >= 15 && hour < 18) {
        greeting = "<?php echo $lang['good_evening']; ?>";
        greetingIcon = "🌅";
      } else {
        greeting = "<?php echo $lang['good_night']; ?>";
        greetingIcon = "🌙";
      }
      
      document.getElementById('greeting-text').textContent = greeting;
      document.getElementById('greeting-icon').textContent = greetingIcon;
    }

    // Add hover effect for the greeting card
    function initializeAnimations() {
      const greetingCard = document.querySelector('.greeting-card');
      const statCards = document.querySelectorAll('.stat-card');
      const profileCards = document.querySelectorAll('.profile-card');
      const settingsCard = document.querySelector('.settings-card');

      // Greeting card hover effect
      greetingCard.addEventListener('mouseenter', function() {
        this.style.boxShadow = '0 10px 25px rgba(78, 84, 200, 0.15)';
        this.style.transform = 'translateY(-5px)';
      });
      
      greetingCard.addEventListener('mouseleave', function() {
        this.style.boxShadow = '0 4px 10px rgba(0, 0, 0, 0.05)';
        this.style.transform = 'translateY(0)';
      });

      // Add animation delays for cards
      profileCards.forEach((card, index) => {
        setTimeout(() => {
          card.style.opacity = '1';
          card.style.transform = 'translateY(0)';
        }, 100 * (index + 1));
      });

      setTimeout(() => {
        settingsCard.style.opacity = '1';
        settingsCard.style.transform = 'translateY(0)';
      }, 100 * (profileCards.length + 1));

      // Set initial styles for animation
      [...profileCards, settingsCard].forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.5s ease';
      });
    }

    // Password confirmation validation
    document.addEventListener('DOMContentLoaded', function() {
      updateGreeting();
      initializeAnimations();
      
      const newPassword = document.getElementById('new_password');
      const confirmPassword = document.getElementById('confirm_password');
      
      function validatePasswords() {
        if (newPassword.value !== confirmPassword.value) {
          confirmPassword.setCustomValidity('<?php echo $lang['password_mismatch']; ?>');
        } else {
          confirmPassword.setCustomValidity('');
        }
      }
      
      newPassword.addEventListener('input', validatePasswords);
      confirmPassword.addEventListener('input', validatePasswords);

      const alerts = document.querySelectorAll('.alert');
  alerts.forEach(alert => {
    // setelah 3 detik, tambahkan class 'hide' untuk fade-out
    setTimeout(() => {
      alert.classList.add('hide');
      // setelah animasi selesai (0.5s), hapus elemen dari DOM
      setTimeout(() => alert.remove(), 500);
    }, 3000);
  });
    });

    // Apply theme immediately
    function applyTheme(theme) {
      document.documentElement.setAttribute('data-theme', theme);
      document.body.className = theme + '-theme';
    }

    // Theme change handler
    document.getElementById('theme').addEventListener('change', function() {
      applyTheme(this.value);
    });
  </script>
</body>

</html>