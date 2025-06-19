<?php
session_start();
if (!isset($_SESSION['username'])) {
  header("Location: login.html");
  exit();
}

// Database connection
include '../database/db.php';

// Get user ID
$username = $_SESSION['username'];
$query = "SELECT user_id FROM users WHERE username = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
$user_id = $user['user_id'];

// Delete appointment if requested
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
  $delete_id = intval($_GET['delete']);
  $delete_query = "DELETE FROM appointments WHERE appointment_id = ? AND user_id = ?";
  $delete_stmt = mysqli_prepare($conn, $delete_query);
  mysqli_stmt_bind_param($delete_stmt, "ii", $delete_id, $user_id);
  mysqli_stmt_execute($delete_stmt);
  
  // Redirect to remove the delete parameter from URL
  header("Location: appointments.php");
  exit();
}

// Edit appointment if form submitted
if (isset($_POST['edit_id']) && isset($_POST['edit_title']) && isset($_POST['edit_date']) && isset($_POST['edit_time'])) {
  $edit_id = intval($_POST['edit_id']);
  $edit_title = $_POST['edit_title'];
  $edit_date = $_POST['edit_date'];
  $edit_time = $_POST['edit_time'];
  
  // Validate inputs
  if (!empty($edit_title) && !empty($edit_date) && !empty($edit_time)) {
    $update_query = "UPDATE appointments SET title = ?, date = ?, time = ? WHERE appointment_id = ? AND user_id = ?";
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, "sssii", $edit_title, $edit_date, $edit_time, $edit_id, $user_id);
    
    if (mysqli_stmt_execute($update_stmt)) {
      // Success message will be shown
      $success_message = "Janji temu berhasil diperbarui!";
    } else {
      $error_message = "Error: " . mysqli_error($conn);
    }
  } else {
    $error_message = "Semua field harus diisi!";
  }
}

// Create new appointment if form submitted
if (isset($_POST['title']) && isset($_POST['date']) && isset($_POST['time'])) {
  $title = $_POST['title'];
  $date = $_POST['date'];
  $time = $_POST['time'];
  
  // Validate inputs
  if (!empty($title) && !empty($date) && !empty($time)) {
    $insert_query = "INSERT INTO appointments (user_id, title, date, time) VALUES (?, ?, ?, ?)";
    $insert_stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($insert_stmt, "isss", $user_id, $title, $date, $time);
    
    if (mysqli_stmt_execute($insert_stmt)) {
      // Success message will be shown
      $success_message = "Janji temu berhasil ditambahkan!";
    } else {
      $error_message = "Error: " . mysqli_error($conn);
    }
  } else {
    $error_message = "Semua field harus diisi!";
  }
}

// Fetch appointment data for editing if requested
$edit_data = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
  $edit_id = intval($_GET['edit']);
  $edit_query = "SELECT * FROM appointments WHERE appointment_id = ? AND user_id = ?";
  $edit_stmt = mysqli_prepare($conn, $edit_query);
  mysqli_stmt_bind_param($edit_stmt, "ii", $edit_id, $user_id);
  mysqli_stmt_execute($edit_stmt);
  $edit_result = mysqli_stmt_get_result($edit_stmt);
  
  if (mysqli_num_rows($edit_result) > 0) {
    $edit_data = mysqli_fetch_assoc($edit_result);
  }
}

// Fetch all appointments for this user
$fetch_query = "SELECT * FROM appointments WHERE user_id = ? ORDER BY date ASC, time ASC";
$fetch_stmt = mysqli_prepare($conn, $fetch_query);
mysqli_stmt_bind_param($fetch_stmt, "i", $user_id);
mysqli_stmt_execute($fetch_stmt);
$appointments = mysqli_stmt_get_result($fetch_stmt);
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Jadwal Janji Temu - HealthReminder</title>
  <link rel="stylesheet" href="css/style.css" />
  <link rel="stylesheet" href="css/appointments.css">
  <script src="https://kit.fontawesome.com/6a3d0e9851.js" crossorigin="anonymous"></script>
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
  <main id="appointment-content">
    <div class="appointment-header">
      <h1>Jadwal Janji Temu</h1>
      <p style="color: #f0f9f3">Kelola jadwal kunjungan dokter dan pemeriksaan kesehatan Anda di sini.</p>
    </div>

    <!-- Notifications -->
    <?php if (isset($success_message)): ?>
      <div class="notification success">
        <i class="fas fa-check-circle"></i>
        <?php echo $success_message; ?>
      </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
      <div class="notification error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $error_message; ?>
      </div>
    <?php endif; ?>

    <!-- Daftar Appointment -->
    <div class="section-title-container">
      <h3 class="section-title">Daftar Janji Temu</h3>
      <button id="open-modal-btn" class="add-btn">
        <i class="fas fa-plus-circle"></i> Tambah Janji Temu
      </button>
    </div>
    
    <?php if (mysqli_num_rows($appointments) > 0): ?>
      <ul id="appointment-list">
        <?php while ($row = mysqli_fetch_assoc($appointments)): ?>
          <li>
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <div>
                <div class="appointment-title"><?php echo htmlspecialchars($row['title']); ?></div>
                <div class="appointment-time">
                  <i class="fas fa-calendar-day"></i>
                  <?php 
                    $date = new DateTime($row['date']);
                    echo $date->format('d F Y'); 
                  ?> 
                  <i class="fas fa-clock" style="margin-left: 10px;"></i>
                  <?php 
                    $time = new DateTime($row['time']);
                    echo $time->format('H:i'); 
                  ?>
                </div>
              </div>
              <div class="btn-group">
                <a href="appointments.php?edit=<?php echo $row['appointment_id']; ?>" class="icon-btn edit-btn" title="Edit">
                  <i class="fas fa-edit"></i>
                </a>
                <a href="appointments.php?delete=<?php echo $row['appointment_id']; ?>" 
                   class="icon-btn delete-btn"
                   onclick="return confirm('Apakah Anda yakin ingin menghapus appointment ini?');"
                   title="Hapus">
                  <i class="fas fa-trash"></i>
                </a>
              </div>
            </div>
          </li>
        <?php endwhile; ?>
      </ul>
    <?php else: ?>
      <div class="empty-state">
        <i class="fas fa-calendar-times"></i>
        <p>Belum ada janji temu yang ditambahkan.</p>
        <p>Tambahkan janji temu pertama Anda dengan mengklik tombol "Tambah Janji Temu".</p>
      </div>
    <?php endif; ?>
  </main>

  <!-- Modal Form Tambah Appointment -->
  <div id="appointment-modal" class="modal">
    <div class="modal-content">
      <span class="close-modal">&times;</span>
      <h3 class="modal-title"><i class="fas fa-plus-circle"></i> Tambah Janji Temu Baru</h3>
      
      <form id="appointment-form" method="POST" action="appointments.php">
        <div class="form-row">
          <div>
            <label for="title"><i class="fas fa-pen"></i> Judul Janji Temu</label>
            <input type="text" id="title" name="title" placeholder="Contoh: Pemeriksaan Gigi" required>
          </div>
        </div>
        <div class="form-row">
          <div>
            <label for="date"><i class="fas fa-calendar"></i> Tanggal</label>
            <input type="date" id="date" name="date" required>
          </div>
          <div>
            <label for="time"><i class="fas fa-clock"></i> Waktu</label>
            <input type="time" id="time" name="time" required>
          </div>
        </div>
        <button type="submit"><i class="fas fa-plus-circle"></i> Tambah Janji Temu</button>
      </form>
    </div>
  </div>
  
  <!-- Modal Form Edit Appointment -->
  <div id="edit-modal" class="modal">
    <div class="modal-content">
      <span class="close-modal" id="close-edit-modal">&times;</span>
      <h3 class="modal-title"><i class="fas fa-edit"></i> Edit Janji Temu</h3>
      
      <form id="edit-appointment-form" method="POST" action="appointments.php">
        <input type="hidden" id="edit_id" name="edit_id" value="<?php echo $edit_data ? $edit_data['appointment_id'] : ''; ?>">
        <div class="form-row">
          <div>
            <label for="edit_title"><i class="fas fa-pen"></i> Judul Janji Temu</label>
            <input type="text" id="edit_title" name="edit_title" placeholder="Contoh: Pemeriksaan Gigi" value="<?php echo $edit_data ? htmlspecialchars($edit_data['title']) : ''; ?>" required>
          </div>
        </div>
        <div class="form-row">
          <div>
            <label for="edit_date"><i class="fas fa-calendar"></i> Tanggal</label>
            <input type="date" id="edit_date" name="edit_date" value="<?php echo $edit_data ? $edit_data['date'] : ''; ?>" required>
          </div>
          <div>
            <label for="edit_time"><i class="fas fa-clock"></i> Waktu</label>
            <input type="time" id="edit_time" name="edit_time" value="<?php echo $edit_data ? $edit_data['time'] : ''; ?>" required>
          </div>
        </div>
        <button type="submit"><i class="fas fa-save"></i> Simpan Perubahan</button>
      </form>
    </div>
  </div>

  <script>
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const toggleBtn = document.getElementById('toggle-btn');
    const modal = document.getElementById('appointment-modal');
    const editModal = document.getElementById('edit-modal');
    const openModalBtn = document.getElementById('open-modal-btn');
    const closeModalBtn = document.querySelector('.close-modal');
    const closeEditModalBtn = document.getElementById('close-edit-modal');
    
    // Toggle sidebar & overlay
    function toggleSidebar() {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('active');
      toggleBtn.classList.toggle('rotate');
    }
    
    // Open modal
    function openModal() {
      modal.style.display = 'block';
      setTimeout(() => {
        modal.classList.add('show');
      }, 10);
    }
    
    // Close modal
    function closeModal() {
      modal.classList.remove('show');
      setTimeout(() => {
        modal.style.display = 'none';
      }, 300);
    }
    
    // Open edit modal
    function openEditModal() {
      editModal.style.display = 'block';
      setTimeout(() => {
        editModal.classList.add('show');
      }, 10);
    }
    
    // Close edit modal
    function closeEditModal() {
      editModal.classList.remove('show');
      setTimeout(() => {
        editModal.style.display = 'none';
      }, 300);
    }
    
    // Event listeners
    toggleBtn.addEventListener('click', toggleSidebar);
    overlay.addEventListener('click', toggleSidebar);
    openModalBtn.addEventListener('click', openModal);
    closeModalBtn.addEventListener('click', closeModal);
    
    // Close modal when clicking outside of it
    window.addEventListener('click', (event) => {
      if (event.target === modal) {
        closeModal();
      }
      if (event.target === editModal) {
        closeEditModal();
      }
    });
    
    // Close edit modal with X button
    if (closeEditModalBtn) {
      closeEditModalBtn.addEventListener('click', closeEditModal);
    }
    
    // Format date display in a more readable format
    document.addEventListener('DOMContentLoaded', function() {
      // If there are success or error messages, automatically hide them after 5 seconds
      const messages = document.querySelectorAll('.notification');
      messages.forEach(msg => {
        setTimeout(() => {
          msg.style.transition = 'opacity 1s, transform 1s';
          msg.style.opacity = '0';
          msg.style.transform = 'translateY(-20px)';
          setTimeout(() => msg.style.display = 'none', 1000);
        }, 5000);
      });
      
      // Add the current date to the date input by default for new appointments
      const dateInput = document.getElementById('date');
      if (dateInput) {
        const today = new Date();
        const yyyy = today.getFullYear();
        let mm = today.getMonth() + 1;
        let dd = today.getDate();
        
        if (dd < 10) dd = '0' + dd;
        if (mm < 10) mm = '0' + mm;
        
        const formattedToday = yyyy + '-' + mm + '-' + dd;
        dateInput.value = formattedToday;
      }
      
      // If there's a success message, auto-close the modals
      <?php if (isset($success_message)): ?>
        closeModal();
        closeEditModal();
      <?php endif; ?>
      
      // If edit parameter is present in URL, open edit modal automatically
      <?php if ($edit_data): ?>
        openEditModal();
      <?php endif; ?>
      
      // Highlight upcoming appointments (within 2 days)
      const appointmentItems = document.querySelectorAll('#appointment-list li');
      appointmentItems.forEach(item => {
        const dateText = item.querySelector('.appointment-time').textContent;
        const dateParts = dateText.match(/(\d+)\s+(\w+)\s+(\d+)/);
        
        if (dateParts) {
          const months = {
            "Januari": 0, "Februari": 1, "Maret": 2, "April": 3, "Mei": 4, "Juni": 5,
            "Juli": 6, "Agustus": 7, "September": 8, "Oktober": 9, "November": 10, "Desember": 11
          };
          
          const day = parseInt(dateParts[1]);
          const month = months[dateParts[2]];
          const year = parseInt(dateParts[3]);
          
          const appointmentDate = new Date(year, month, day);
          const today = new Date();
          
          // Reset time to compare just the dates
          today.setHours(0, 0, 0, 0);
          
          // Calculate days difference
          const timeDiff = appointmentDate.getTime() - today.getTime();
          const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24));
          
          // If appointment is today or within next 2 days
          if (daysDiff >= 0 && daysDiff <= 2) {
            item.style.borderLeft = '4px solid #e53e3e';
            item.style.background = '#fffaf0';
            item.classList.add('pulse');
            
            // Add badge for urgent appointments
            const badge = document.createElement('span');
            badge.textContent = 'Segera';
            badge.style.background = '#e53e3e';
            badge.style.color = 'white';
            badge.style.padding = '3px 8px';
            badge.style.borderRadius = '10px';
            badge.style.fontSize = '12px';
            badge.style.marginLeft = '10px';
            
            item.querySelector('.appointment-title').appendChild(badge);
          }
        }
      });
    });
  </script>
</body>

</html>

<?php
// Close database connection
mysqli_close($conn);
?>