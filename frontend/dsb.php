<?php
session_start();
if (!isset($_SESSION['username'])) {
  header("Location: login.html");
  exit();
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
    :root {
      --bg-color: #f3f4f6;
      --text-color: #1f2937;
      --sidebar-width: 260px;
      --transition-speed: 0.25s;
      --sidebar-bg: #1f2937;
      --content-max-width: 800px;
    }

    body {
      margin: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: var(--bg-color);
      color: var(--text-color);
      overflow-x: hidden;
      transition: background-color var(--transition-speed);
    }

    /* SIDEBAR */
    nav.sidebar {
      position: fixed;
      top: 0;
      left: 0;
      width: var(--sidebar-width);
      height: 100vh;
      background-color: var(--sidebar-bg);
      transform: translateX(-100%);
      transition: transform var(--transition-speed) ease-in-out, box-shadow var(--transition-speed);
      z-index: 1000;
      box-shadow: none;
    }

    nav.sidebar.open {
      transform: translateX(0);
      box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2);
    }

    nav.sidebar .sidebar-header {
      text-align: center;
      padding: 40px 20px 10px;
    }

    nav.sidebar .sidebar-header img.sidebar-logo {
      width: 60px;
      height: auto;
      margin-bottom: 8px;
      display: inline-block;
    }

    nav.sidebar .sidebar-header h1 {
      margin: 0;
      font-size: 22px;
      color: #fff;
    }

    nav.sidebar ul {
      list-style: none;
      padding: 0;
      margin: 0;
      padding-top: 20px;
    }

    nav.sidebar ul li {
      margin-bottom: 6px;
    }

    nav.sidebar ul li a {
      display: flex;
      align-items: center;
      padding: 12px 20px;
      text-decoration: none;
      color: rgba(255, 255, 255, 0.7);
      transition: background var(--transition-speed), color var(--transition-speed);
    }

    nav.sidebar ul li a:hover {
      background: rgba(255, 255, 255, 0.1);
      color: #fff;
    }

    nav.sidebar ul li a.active {
      background: rgba(255, 255, 255, 0.15);
      color: #fff;
    }

    /* TOGGLE BUTTON */
    .sidebar-toggle {
      position: fixed;
      top: 12px;
      left: 12px;
      width: 36px;
      height: 36px;
      background: rgb(255, 255, 255);
      border: 1px solid rgba(0, 0, 0, 0.1);
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      z-index: 1100;
      transition: transform var(--transition-speed), background var(--transition-speed);
    }

    .sidebar-toggle:hover {
      background: rgba(243, 244, 246);
    }

    .sidebar-toggle.rotate {
      transform: rotate(90deg);
    }

    /* OVERLAY */
    .overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.3);
      opacity: 0;
      visibility: hidden;
      transition: opacity var(--transition-speed);
      z-index: 900;
    }

    .overlay.active {
      opacity: 1;
      visibility: visible;
    }

    /* MAIN CONTENT */
    main {
      padding: 20px;
      margin-left: 60px;
      position: relative;
      text-align: center;
    }

    main .content-wrapper {
      max-width: var(--content-max-width);
      margin: 0 auto;
      text-align: left;
    }

    /* SECTIONS */
    .content-section {
      display: none;
      opacity: 0;
      transition: opacity 0.2s ease-in-out;
    }

    .content-section.active {
      display: block;
      opacity: 1;
    }

    /* GREETING & FORM */
    .greeting-message {
      font-size: 14px;
      background: var(--bg-color);
      color: var(--text-color);
      padding: 6px 12px;
      border-radius: 4px;
      display: inline-block;
      margin-top: 12px;
    }

    .form-select,
    .form-input {
      margin: 8px 0;
      padding: 6px;
      width: 100%;
      max-width: 350px;
    }

    a div:hover {
      transform: translateY(-4px);
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    /* 1. Flex-container yang men-center vertikal + horizontal */
    .dashboard-center {
      display: flex;
      flex-direction: column;
      align-items: center;
      /* center horizontally */
      justify-content: center;
      /* center vertically */
      min-height: calc(100vh - 0px);
      /* full viewport height (kecuali margin/padding atas) */
      text-align: center;
      /* teks <h1> & <p> center */
      gap: 24px;
      /* jarak antar elemen */
    }

    /* 2. Grid kartu dibatasi lebar maksimal + responsive */
    .grid-container {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px;
      width: 100%;
      max-width: var(--content-max-width);
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
    <div class="content-wrapper">
      <!-- Mulai isi dashboard -->
      <h1 style="text-align:center; margin-bottom:16px;">HealthReminder</h1>
      <div class="dashboard-center">
        <p style="text-align:center; font-size:1.1rem; margin-bottom:24px;">
          Apa yang ingin Anda lakukan hari ini?
        </p>

        <!-- Grid 3 kolom untuk kartu -->
        <div class="grid-container" style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-bottom:40px;">
          <!-- Kartu Appointment -->
          <a href="appointments.php" style="text-decoration:none;">
            <div style="padding:20px; border-radius:8px; background:#fff; box-shadow:0 2px 6px rgba(0,0,0,0.1); text-align:center; transition:transform .2s;">
              <i class="fas fa-calendar-check" style="font-size:2rem; margin-bottom:8px; color:#1f2937;"></i>
              <h3 style="margin:0; color:#1f2937;">Appointment</h3>
            </div>
          </a>

          <!-- Kartu To-Do List -->
          <a href="todolist.php" style="text-decoration:none;">
            <div style="padding:20px; border-radius:8px; background:#fff; box-shadow:0 2px 6px rgba(0,0,0,0.1); text-align:center; transition:transform .2s;">
              <i class="fas fa-tasks" style="font-size:2rem; margin-bottom:8px; color:#1f2937;"></i>
              <h3 style="margin:0; color:#1f2937;">To-Do List</h3>
            </div>
          </a>

          <!-- Kartu Blog Kesehatan -->
          <a href="healthblog.php" style="text-decoration:none;">
            <div style="padding:20px; border-radius:8px; background:#fff; box-shadow:0 2px 6px rgba(0,0,0,0.1); text-align:center; transition:transform .2s;">
              <i class="fas fa-blog" style="font-size:2rem; margin-bottom:8px; color:#1f2937;"></i>
              <h3 style="margin:0; color:#1f2937;">Blog Kesehatan</h3>
            </div>
          </a>
        </div>

        <!-- Baris kedua kartu -->
        <div class="grid-container" style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px;">
          <!-- Kartu Tambah Pengingat -->
          <a href="addreminder.php" style="text-decoration:none;">
            <div style="padding:20px; border-radius:8px; background:#fff; box-shadow:0 2px 6px rgba(0,0,0,0.1); text-align:center; transition:transform .2s;">
              <i class="fas fa-bell" style="font-size:2rem; margin-bottom:8px; color:#1f2937;"></i>
              <h3 style="margin:0; color:#1f2937;">Tambah Pengingat</h3>
            </div>
          </a>

          <!-- Kartu Laporan -->
          <a href="reports.php" style="text-decoration:none;">
            <div style="padding:20px; border-radius:8px; background:#fff; box-shadow:0 2px 6px rgba(0,0,0,0.1); text-align:center; transition:transform .2s;">
              <i class="fas fa-chart-line" style="font-size:2rem; margin-bottom:8px; color:#1f2937;"></i>
              <h3 style="margin:0; color:#1f2937;">Laporan</h3>
            </div>
          </a>

          <!-- Kartu Profil -->
          <a href="profile.php" style="text-decoration:none;">
            <div style="padding:20px; border-radius:8px; background:#fff; box-shadow:0 2px 6px rgba(0,0,0,0.1); text-align:center; transition:transform .2s;">
              <i class="fas fa-user-cog" style="font-size:2rem; margin-bottom:8px; color:#1f2937;"></i>
              <h3 style="margin:0; color:#1f2937;">Profil</h3>
            </div>
          </a>
        </div>
        <!-- Akhir isi dashboard -->
      </div>
    </div>
  </main>

  <script>
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const toggleBtn = document.getElementById('toggle-btn');
    const links = sidebar.querySelectorAll('ul li a');
    const sections = document.querySelectorAll('.content-section');
    const username = "<?php echo $_SESSION['username']; ?>";
    const themeSelect = document.getElementById('theme-toggle');
    const langSelect = document.getElementById('language-toggle');

    // Toggle sidebar & overlay
    function toggleSidebar() {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('active');
      toggleBtn.classList.toggle('rotate');
    }
    toggleBtn.addEventListener('click', toggleSidebar);
    overlay.addEventListener('click', toggleSidebar);

    function toggleSidebar() {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('active');
      toggleBtn.classList.toggle('rotate');
    }
    toggleBtn.addEventListener('click', toggleSidebar);
    overlay.addEventListener('click', toggleSidebar);

    // Greeting dynamic
    function updateGreeting() {
      const hr = new Date().getHours();
      const gm = hr < 12 ? 'Selamat pagi' : hr < 18 ? 'Selamat siang' : 'Selamat malam';
      document.getElementById('greeting').innerHTML = `${gm}, <strong>${username}</strong> 👋`;
    }
    updateGreeting();

    // Load & apply saved theme
    themeSelect.addEventListener('change', () => {
      document.body.setAttribute('data-theme', themeSelect.value);
      localStorage.setItem('theme', themeSelect.value);
    });
    const savedTheme = localStorage.getItem('theme') || 'light';
    themeSelect.value = savedTheme;
    document.body.setAttribute('data-theme', savedTheme);

    // Load & apply saved language
    const translations = {
      id: {
        appointment: 'Jadwal Appointment',
        todo: 'To-Do List',
        blog: 'Blog Kesehatan',
        reminder: 'Tambah Pengingat',
        laporan: 'Laporan',
        profil: 'Profil',
        theme: 'Tema',
        language: 'Bahasa'
      },
      en: {
        appointment: 'Appointments',
        todo: 'To-Do List',
        blog: 'Health Blog',
        reminder: 'Add Reminder',
        laporan: 'Reports',
        profil: 'Profile',
        theme: 'Theme',
        language: 'Language'
      }
    };

    function changeLanguage(lang) {
      document.querySelector('label[for="theme-toggle"]').textContent = translations[lang].theme + ':';
      document.querySelector('label[for="language-toggle"]').textContent = translations[lang].language + ':';
      links[0].innerHTML = `<i class="fas fa-calendar-check"></i>&nbsp;${translations[lang].appointment}`;
      links[1].innerHTML = `<i class="fas fa-tasks"></i>&nbsp;${translations[lang].todo}`;
      links[2].innerHTML = `<i class="fas fa-blog"></i>&nbsp;${translations[lang].blog}`;
      links[3].innerHTML = `<i class="fas fa-bell"></i>&nbsp;${translations[lang].reminder}`;
      links[4].innerHTML = `<i class="fas fa-chart-line"></i>&nbsp;${translations[lang].laporan}`;
      links[5].innerHTML = `<i class="fas fa-user-cog"></i>&nbsp;${translations[lang].profil}`;
      ['appointment', 'todo', 'blog', 'reminder', 'laporan', 'profil']
      .forEach(id => document.querySelector(`#${id} h2`).textContent = translations[lang][id]);
      updateGreeting();
    }
    langSelect.addEventListener('change', () => {
      localStorage.setItem('lang', langSelect.value);
      changeLanguage(langSelect.value);
    });
    const savedLang = localStorage.getItem('lang') || 'id';
    langSelect.value = savedLang;
    changeLanguage(savedLang);
  </script>
</body>

</html>