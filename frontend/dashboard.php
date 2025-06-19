<?php
session_start();
if (!isset($_SESSION['username'])) {
  header("Location: login.html");
  exit();
}

// Get current time for greeting - Updated with more granular time periods
$hour = date('H');
if ($hour >= 5 && $hour < 12) {
  $greeting = "Selamat Pagi";
  $greeting_icon = "☀️";
} elseif ($hour >= 12 && $hour < 15) {
  $greeting = "Selamat Siang";
  $greeting_icon = "🌤️";
} elseif ($hour >= 15 && $hour < 18) {
  $greeting = "Selamat Sore";
  $greeting_icon = "🌅";
} else {
  $greeting = "Selamat Malam";
  $greeting_icon = "🌙";
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard - HealthReminder</title>
  <link rel="stylesheet" href="css/style.css" />
  <script src="https://kit.fontawesome.com/6a3d0e9851.js" crossorigin="anonymous"></script>
  <style>
    main {
      padding: 20px;
    }
    
    .dashboard-header {
      background: linear-gradient(135deg, #399bc8, #6cc4d8);
      color: white;
      padding: 30px 20px;
      border-radius: 15px;
      margin-bottom: 30px;
      text-align: center;
      position: relative;
      overflow: hidden;
      box-shadow: 0 5px 20px rgba(78, 84, 200, 0.3);
    }
    
    .dashboard-header::before {
      content: '';
      position: absolute;
      bottom: -50px;
      right: -50px;
      width: 200px;
      height: 200px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 50%;
    }
    
    .dashboard-header::after {
      content: '';
      position: absolute;
      top: -30px;
      left: -30px;
      width: 130px;
      height: 130px;
      background: rgba(255, 255, 255, 0.08);
      border-radius: 50%;
    }
    
    .dashboard-header h1 {
      font-size: 38px;
      margin: 0;
      font-weight: 700;
      position: relative;
      z-index: 1;
      text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .dashboard-header .subtitle {
      font-size: 18px;
      opacity: 0.9;
      margin: 5px 0 0;
      position: relative;
      z-index: 1;
      color: #f0f9f3;
    }
    
    .greeting-card {
      background: white;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 30px;
      text-align: center;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
      position: relative;
      overflow: hidden;
      border-left: 4px solid #399bc8;
    }
    
    .greeting-message {
      font-size: 24px;
      font-weight: 600;
      color: #2d3748;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
    }
    
    .greeting-message .user-name {
      color: #399bc8;
      margin: 0 5px;
    }
    
    .greeting-message .greeting-emoji {
      font-size: 28px;
      margin-left: 10px;
      animation: wave 2s infinite;
    }
    
    /* Menu Section With Gap */
    .menu-container {
      margin-top: 40px;
    }
    
    .menu-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 25px;
      margin-bottom: 30px;
    }
    
    .menu-card {
      background: white;
      border-radius: 15px;
      padding: 25px 15px;
      text-align: center;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      cursor: pointer;
      color: #2d3748;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-decoration: none;
    }
    
    .menu-card::before {
      content: '';
      position: absolute;
      width: 100%;
      height: 5px;
      bottom: 0;
      left: 0;
      background: linear-gradient(to right, #399bc8, #6cc4d8);
      transform: scaleX(0);
      transform-origin: left;
      transition: transform 0.3s ease;
    }
    
    .menu-card:hover {
      transform: translateY(-10px);
      box-shadow: 0 10px 25px rgba(78, 84, 200, 0.15);
    }
    
    .menu-card:hover::before {
      transform: scaleX(1);
    }
    
    .menu-card:hover .menu-icon {
      transform: scale(1.2) rotate(5deg);
      color: #399bc8;
    }
    
    .menu-icon {
      font-size: 40px;
      margin-bottom: 15px;
      transition: all 0.3s ease;
      color: #4a5568;
    }
    
    .menu-title {
      font-size: 18px;
      font-weight: 600;
      margin: 0;
      transition: all 0.3s ease;
    }
    
    .menu-card:hover .menu-title {
      color: #399bc8;
    }
    
    .menu-description {
      font-size: 14px;
      color: #718096;
      margin-top: 8px;
      opacity: 0;
      height: 0;
      transition: all 0.3s ease;
      overflow: hidden;
    }
    
    .menu-card:hover .menu-description {
      opacity: 1;
      height: auto;
      margin-top: 8px;
    }
    
    .date-info {
      text-align: center;
      margin-bottom: 20px;
    }
    
    .current-date {
      display: inline-flex;
      align-items: center;
      padding: 8px 18px;
      background: white;
      border-radius: 50px;
      font-size: 16px;
      color: #4a5568;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }
    
    .current-date i {
      margin-right: 8px;
      color: #399bc8;
    }
    
    @keyframes wave {
      0% { transform: rotate(0deg); }
      10% { transform: rotate(14deg); }
      20% { transform: rotate(-8deg); }
      30% { transform: rotate(14deg); }
      40% { transform: rotate(-4deg); }
      50% { transform: rotate(10deg); }
      60% { transform: rotate(0deg); }
      100% { transform: rotate(0deg); }
    }
    
    @keyframes float {
      0% { transform: translateY(0px); }
      50% { transform: translateY(-8px); }
      100% { transform: translateY(0px); }
    }
    
    @keyframes pulse {
      0% { box-shadow: 0 0 0 0 rgba(78, 84, 200, 0.3); }
      70% { box-shadow: 0 0 0 15px rgba(78, 84, 200, 0); }
      100% { box-shadow: 0 0 0 0 rgba(78, 84, 200, 0); }
    }
    
    /* Stats Section */
    .stats-section {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .stat-card {
      background: white;
      border-radius: 12px;
      padding: 20px;
      text-align: center;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }
    
    .stat-icon {
      font-size: 30px;
      margin-bottom: 10px;
      color: #399bc8;
    }
    
    .stat-value {
      font-size: 28px;
      font-weight: 700;
      color: #2d3748;
      margin: 0;
    }
    
    .stat-label {
      font-size: 14px;
      color: #718096;
      margin: 5px 0 0;
    }

    .action-btn {
      padding: 12px 20px;
      background: linear-gradient(to right, #399bc8, #6cc4d8);
      color: white;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 4px 10px rgba(78, 84, 200, 0.2);
    }
    
    .action-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 15px rgba(78, 84, 200, 0.3);
    }
    
    /* Responsive adjustments */
    @media (max-width: 1200px) {
      .top-info-section {
        flex-direction: column;
      }
      
      .greeting-card {
        max-width: 100%;
        margin-bottom: 20px;
      }
      
      .date-info {
        text-align: center;
        width: 100%;
      }
    }
    
    @media (max-width: 992px) {
      /* Retain existing styles */
    }
    
    @media (max-width: 768px) {
      .top-info-section {
        flex-direction: column;
      }
      
      .greeting-card {
        max-width: 100%;
        margin-bottom: 20px;
      }
      
      .date-info {
        text-align: center;
        width: 100%;
      }
      
      .menu-grid {
        grid-template-columns: 1fr 1fr;
      }
      
      .stats-section {
        grid-template-columns: 1fr;
      }
    }
    
    @media (max-width: 480px) {
      .menu-grid {
        grid-template-columns: 1fr;
      }
      
      .dashboard-header h1 {
        font-size: 28px;
      }
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
        <h1>HealthReminder</h1>
        <p class="subtitle">Partner kesehatan terpercaya untuk Anda</p>
      </div>

      <!-- Current Date & Greeting Section -->
      <div class="top-info-section">
        <!-- Greeting Section -->
        <div class="greeting-card">
          <div class="greeting-message">
            <span id="greeting-text"><?php echo $greeting; ?></span>, <span class="user-name"><?php echo $_SESSION['username']; ?></span>
            <span class="greeting-emoji" id="greeting-icon"><?php echo $greeting_icon; ?></span>
          </div>
      </div>
        </div>

        <!-- Current Date -->
        <div class="date-info">
          <div class="current-date">
            <i class="fas fa-calendar-day"></i>
            <span id="currentDate">Loading...</span>
          </div>
        </div>
      </div>

      <!-- Main Menu Section -->
      <div class="menu-container">
        <div class="menu-grid">
        <!-- Kartu Appointment -->
        <a href="appointments.php" class="menu-card">
          <div class="menu-icon">
            <i class="fas fa-calendar-check"></i>
          </div>
          <h3 class="menu-title">Janji Temu</h3>
          <p class="menu-description">Kelola jadwal kunjungan dokter dan pemeriksaan kesehatan Anda</p>
        </a>

        <!-- Kartu To-Do List -->
        <a href="todolist.php" class="menu-card">
          <div class="menu-icon">
            <i class="fas fa-tasks"></i>
          </div>
          <h3 class="menu-title">To-Do List</h3>
          <p class="menu-description">Buat dan kelola daftar tugas kesehatan harian Anda</p>
        </a>

        <!-- Kartu Blog Kesehatan -->
        <a href="healthblog.php" class="menu-card">
          <div class="menu-icon">
            <i class="fas fa-blog"></i>
          </div>
          <h3 class="menu-title">Blog Kesehatan</h3>
          <p class="menu-description">Baca artikel kesehatan terbaru dan tips kesehatan</p>
        </a>

        <!-- Kartu Pengingat -->
        <a href="reminder.php" class="menu-card">
          <div class="menu-icon">
            <i class="fas fa-bell"></i>
          </div>
          <h3 class="menu-title">Pengingat</h3>
          <p class="menu-description">Atur pengingat untuk jadwal minum obat atau kegiatan kesehatan</p>
        </a>

        <!-- Kartu Laporan -->
        <a href="reports.php" class="menu-card">
          <div class="menu-icon">
            <i class="fas fa-chart-line"></i>
          </div>
          <h3 class="menu-title">Laporan</h3>
          <p class="menu-description">Lihat laporan dan analisis kesehatan Anda</p>
        </a>

        <!-- Kartu Profil -->
        <a href="profile.php" class="menu-card">
          <div class="menu-icon">
            <i class="fas fa-user-cog"></i>
          </div>
          <h3 class="menu-title">Profil</h3>
          <p class="menu-description">Kelola informasi dan pengaturan profil Anda</p>
        </a>
      </div>
  </main>

  <script>
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const toggleBtn = document.getElementById('toggle-btn');
    const links = document.querySelectorAll('ul li a');
    const sections = document.querySelectorAll('.content-section');
    const username = "<?php echo $_SESSION['username']; ?>";
    
    // Toggle sidebar & overlay
    function toggleSidebar() {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('active');
      toggleBtn.classList.toggle('rotate');
    }
    
    toggleBtn.addEventListener('click', toggleSidebar);
    overlay.addEventListener('click', toggleSidebar);
    
    // Format current date
    function formatCurrentDate() {
      const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
      const today = new Date();
      document.getElementById('currentDate').textContent = today.toLocaleDateString('id-ID', options);
    }
    
    // Update greeting based on current time
    function updateGreeting() {
      const hour = new Date().getHours();
      let greeting, greetingIcon;
      
      if (hour >= 5 && hour < 12) {
        greeting = "Selamat Pagi";
        greetingIcon = "☀️";
      } else if (hour >= 12 && hour < 15) {
        greeting = "Selamat Siang";
        greetingIcon = "🌤️";
      } else if (hour >= 15 && hour < 18) {
        greeting = "Selamat Sore";
        greetingIcon = "🌅";
      } else {
        greeting = "Selamat Malam";
        greetingIcon = "🌙";
      }
      
      document.getElementById('greeting-text').textContent = greeting;
      document.getElementById('greeting-icon').textContent = greetingIcon;
    }
    
    // Add staggered animation to menu cards
    function animateMenuCards() {
      const cards = document.querySelectorAll('.menu-card');
      cards.forEach((card, index) => {
        setTimeout(() => {
          card.style.opacity = '1';
          card.style.transform = 'translateY(0)';
        }, 100 * index);
      });
    }
    
    // Initialize when document is ready
    document.addEventListener('DOMContentLoaded', function() {
      formatCurrentDate();
      updateGreeting(); // Update greeting on page load
      
      // Add initial styles for animation
      const cards = document.querySelectorAll('.menu-card');
      cards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.5s ease';
      });
      
      // Start animations
      setTimeout(animateMenuCards, 300);
      
      // Add hover effect for the greeting card
      const greetingCard = document.querySelector('.greeting-card');
      greetingCard.addEventListener('mouseenter', function() {
        this.style.boxShadow = '0 10px 25px rgba(78, 84, 200, 0.15)';
        this.style.transform = 'translateY(-5px)';
      });
      
      greetingCard.addEventListener('mouseleave', function() {
        this.style.boxShadow = '0 4px 10px rgba(0, 0, 0, 0.05)';
        this.style.transform = 'translateY(0)';
      });
      
      // Set transition for greeting card
      greetingCard.style.transition = 'all 0.3s ease';
    });
  </script>
</body>

</html>