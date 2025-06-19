<?php
session_start();
if (!isset($_SESSION['username'])) {
  header("Location: login.html");
  exit();
}

// Database connection
include '../database/db.php';

// Get user ID from session
$username = $_SESSION['username'];
$user_query = "SELECT user_id FROM users WHERE username = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_id = $user['user_id'];
$stmt->close();

// Process report generation if requested
$report_generated = false;
$report_data = [];
$report_type = "";
$report_title = "";
$date_filter = "";
$show_preview = false;

if (isset($_POST['generate_report'])) {
  $report_type = isset($_POST['report_type']) ? $_POST['report_type'] : '';
  $date_filter = isset($_POST['date_filter']) ? $_POST['date_filter'] : 'all';
  $report_title = "";
  
  // Determine date range based on filter
  $date_condition = "";
  $current_date = date('Y-m-d');
  
  switch ($date_filter) {
    case '1_day':
      $date_condition = "AND DATE(COALESCE(date, remind_date, due_date)) = '$current_date'";
      break;
    case '3_days':
      $start_date = date('Y-m-d', strtotime('-3 days'));
      $date_condition = "AND DATE(COALESCE(date, remind_date, due_date)) BETWEEN '$start_date' AND '$current_date'";
      break;
    case '1_week':
      $start_date = date('Y-m-d', strtotime('-1 week'));
      $date_condition = "AND DATE(COALESCE(date, remind_date, due_date)) BETWEEN '$start_date' AND '$current_date'";
      break;
    case '1_month':
      $start_date = date('Y-m-d', strtotime('-1 month'));
      $date_condition = "AND DATE(COALESCE(date, remind_date, due_date)) BETWEEN '$start_date' AND '$current_date'";
      break;
    case '3_months':
      $start_date = date('Y-m-d', strtotime('-3 months'));
      $date_condition = "AND DATE(COALESCE(date, remind_date, due_date)) BETWEEN '$start_date' AND '$current_date'";
      break;
    case '1_year':
      $start_date = date('Y-m-d', strtotime('-1 year'));
      $date_condition = "AND DATE(COALESCE(date, remind_date, due_date)) BETWEEN '$start_date' AND '$current_date'";
      break;
    case 'all':
    default:
      $date_condition = "";
      break;
  }
  
  switch ($report_type) {
    case 'appointments':
      $report_title = "Laporan Janji Temu";
      // Get appointments data with date filter
      $appointments_condition = str_replace('COALESCE(date, remind_date, due_date)', 'date', $date_condition);
      $query = "SELECT title as 'Judul', date as 'Tanggal', time as 'Waktu' FROM appointments WHERE user_id = ? $appointments_condition ORDER BY date ASC, time ASC";
      $stmt = $conn->prepare($query);
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $result = $stmt->get_result();
      
      while ($row = $result->fetch_assoc()) {
        $report_data[] = $row;
      }
      $stmt->close();
      break;
      
    case 'reminders':
      $report_title = "Laporan Pengingat";
      // Get reminders data with date filter
      $reminders_condition = str_replace('COALESCE(date, remind_date, due_date)', 'remind_date', $date_condition);
      $query = "SELECT title as 'Judul', remind_date as 'Tanggal', remind_time as 'Waktu', 
                CASE WHEN is_done = 1 THEN 'Selesai' ELSE 'Belum Selesai' END as 'Status' 
                FROM reminders WHERE user_id = ? $reminders_condition ORDER BY remind_date ASC, remind_time ASC";
      $stmt = $conn->prepare($query);
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $result = $stmt->get_result();
      
      while ($row = $result->fetch_assoc()) {
        $report_data[] = $row;
      }
      $stmt->close();
      break;
      
    case 'todos':
      $report_title = "Laporan To-Do List";
      // Get todos data with date filter
      $todos_condition = str_replace('COALESCE(date, remind_date, due_date)', 'due_date', $date_condition);
      $query = "SELECT task as 'Tugas', due_date as 'Tenggat Waktu', 
                CASE WHEN is_done = 1 THEN 'Selesai' ELSE 'Belum Selesai' END as 'Status' 
                FROM todos WHERE user_id = ? $todos_condition ORDER BY due_date ASC";
      $stmt = $conn->prepare($query);
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $result = $stmt->get_result();
      
      while ($row = $result->fetch_assoc()) {
        $report_data[] = $row;
      }
      $stmt->close();
      break;
      
    case 'comprehensive':
      $report_title = "Laporan Komprehensif";
      // Get all data combined
      $all_data = [];
      
      // Appointments
      $appointments_condition = str_replace('COALESCE(date, remind_date, due_date)', 'date', $date_condition);
      $query = "SELECT 'Appointment' as 'Kategori', title as 'Detail', date as 'Tanggal', time as 'Waktu', 'Terjadwal' as 'Status' FROM appointments WHERE user_id = ? $appointments_condition";
      $stmt = $conn->prepare($query);
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $result = $stmt->get_result();
      while ($row = $result->fetch_assoc()) {
        $all_data[] = $row;
      }
      $stmt->close();
      
      // Reminders
      $reminders_condition = str_replace('COALESCE(date, remind_date, due_date)', 'remind_date', $date_condition);
      $query = "SELECT 'Pengingat' as 'Kategori', title as 'Detail', remind_date as 'Tanggal', remind_time as 'Waktu', 
                CASE WHEN is_done = 1 THEN 'Selesai' ELSE 'Belum Selesai' END as 'Status' 
                FROM reminders WHERE user_id = ? $reminders_condition";
      $stmt = $conn->prepare($query);
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $result = $stmt->get_result();
      while ($row = $result->fetch_assoc()) {
        $all_data[] = $row;
      }
      $stmt->close();
      
      // Todos
      $todos_condition = str_replace('COALESCE(date, remind_date, due_date)', 'due_date', $date_condition);
      $query = "SELECT 'To-Do' as 'Kategori', task as 'Detail', due_date as 'Tanggal', '' as 'Waktu', 
                CASE WHEN is_done = 1 THEN 'Selesai' ELSE 'Belum Selesai' END as 'Status' 
                FROM todos WHERE user_id = ? $todos_condition";
      $stmt = $conn->prepare($query);
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $result = $stmt->get_result();
      while ($row = $result->fetch_assoc()) {
        $all_data[] = $row;
      }
      $stmt->close();
      
      // Sort by date
      usort($all_data, function($a, $b) {
        $dateA = strtotime($a['Tanggal']);
        $dateB = strtotime($b['Tanggal']);
        
        if ($dateA == $dateB) {
          // If dates are the same, sort by time
          $timeA = strtotime($a['Waktu']);
          $timeB = strtotime($b['Waktu']);
          return $timeA - $timeB;
        }
        
        return $dateA - $dateB;
      });
      
      $report_data = $all_data;
      break;
  }
  
  $report_generated = true;
  $show_preview = true;
  
  // Store in session for potential saving
  $_SESSION['preview_report'] = [
    'data' => $report_data,
    'title' => $report_title,
    'type' => $report_type,
    'date_filter' => $date_filter
  ];
}

// Handle save report after preview
if (isset($_POST['save_report']) && isset($_SESSION['preview_report'])) {
  $preview_data = $_SESSION['preview_report'];
  
  if (count($preview_data['data']) > 0) {
    $report_json = json_encode($preview_data['data']);
    $pdf_filename = $preview_data['type'] . '_' . $preview_data['date_filter'] . '_' . date('Y-m-d_H-i-s') . '.pdf';
    $save_report = "INSERT INTO reports (user_id, type, data, pdf_filename, date_filter) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($save_report);
    $stmt->bind_param("issss", $user_id, $preview_data['type'], $report_json, $pdf_filename, $preview_data['date_filter']);
    $stmt->execute();
    $stmt->close();
    
    // Store in session for PDF generation
    $_SESSION['current_report'] = [
      'data' => $preview_data['data'],
      'title' => $preview_data['title'],
      'type' => $preview_data['type'],
      'date_filter' => $preview_data['date_filter'],
      'filename' => $pdf_filename
    ];
    
    // Clear preview
    unset($_SESSION['preview_report']);
    $show_preview = false;
  }
}

// Handle view existing report
if (isset($_GET['view_report'])) {
  $report_id = intval($_GET['view_report']);
  $view_query = "SELECT * FROM reports WHERE report_id = ? AND user_id = ?";
  $stmt = $conn->prepare($view_query);
  $stmt->bind_param("ii", $report_id, $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $existing_report = $result->fetch_assoc();
  $stmt->close();
  
  if ($existing_report) {
    $report_data = json_decode($existing_report['data'], true);
    $report_type = $existing_report['type'];
    $date_filter = $existing_report['date_filter'];
    
    $report_titles = [
      'appointments' => 'Laporan Janji Temu',
      'reminders' => 'Laporan Pengingat',
      'todos' => 'Laporan To-Do List',
      'comprehensive' => 'Laporan Komprehensif'
    ];
    
    $report_title = $report_titles[$report_type] ?? 'Laporan';
    $report_generated = true;
    $show_preview = false;
  }
}

// Handle report deletion
if (isset($_POST['delete_report'])) {
  $report_id = isset($_POST['report_id']) ? intval($_POST['report_id']) : 0;
  if ($report_id > 0) {
    $delete_query = "DELETE FROM reports WHERE report_id = ? AND user_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("ii", $report_id, $user_id);
    $stmt->execute();
    $stmt->close();
  }
}

// Get previous reports
$previous_reports = [];
$prev_query = "SELECT report_id, type, generated_at, pdf_filename, date_filter FROM reports WHERE user_id = ? ORDER BY generated_at DESC";
$stmt = $conn->prepare($prev_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
  $previous_reports[] = $row;
}
$stmt->close();

// Get statistics
$stats = [
  'appointments' => 0,
  'reminders' => 0,
  'total_todos' => 0
];

// Count appointments
$query = "SELECT COUNT(*) as count FROM appointments WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stats['appointments'] = $row['count'];
$stmt->close();

// Count reminders
$query = "SELECT COUNT(*) as count FROM reminders WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stats['reminders'] = $row['count'];
$stmt->close();

// Count total todos
$query = "SELECT COUNT(*) as count FROM todos WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stats['total_todos'] = $row['count'];
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Laporan - HealthReminder</title>
  <link rel="stylesheet" href="css/style.css" />
  <link rel="stylesheet" href="css/reports.css">
  <script src="https://kit.fontawesome.com/6a3d0e9851.js" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
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
  <main>
    <!-- Header Section -->
    <div class="page-header">
      <h1>Laporan Kesehatan</h1>
      <p class="subtitle">Kelola dan lihat laporan kesehatan Anda</p>
    </div>
    
    <!-- Stats Section -->
    <div class="stats-section">
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-calendar-check"></i>
        </div>
        <p class="stat-value"><?php echo $stats['appointments']; ?></p>
        <p class="stat-label">Total Janji Temu</p>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-bell"></i>
        </div>
        <p class="stat-value"><?php echo $stats['reminders']; ?></p>
        <p class="stat-label">Total Pengingat</p>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-tasks"></i>
        </div>
        <p class="stat-value"><?php echo $stats['total_todos']; ?></p>
        <p class="stat-label">Total Tugas</p>
      </div>
    </div>
    
    <!-- Report Generator -->
    <div class="report-generator">
      <h2>Buat Laporan Baru</h2>
      
      <form class="report-form" method="POST" action="">
        <div class="form-row">
          <div class="form-group">
            <label for="report_type">Jenis Laporan</label>
            <select id="report_type" name="report_type" required>
              <option value="">-- Pilih Jenis Laporan --</option>
              <option value="appointments" <?php echo (isset($_POST['report_type']) && $_POST['report_type'] == 'appointments') ? 'selected' : ''; ?>>Laporan Janji Temu</option>
              <option value="reminders" <?php echo (isset($_POST['report_type']) && $_POST['report_type'] == 'reminders') ? 'selected' : ''; ?>>Laporan Pengingat</option>
              <option value="todos" <?php echo (isset($_POST['report_type']) && $_POST['report_type'] == 'todos') ? 'selected' : ''; ?>>Laporan To-Do List</option>
              <option value="comprehensive" <?php echo (isset($_POST['report_type']) && $_POST['report_type'] == 'comprehensive') ? 'selected' : ''; ?>>Laporan Komprehensif</option>
            </select>
          </div>
          
          <div class="form-group">
            <label for="date_filter">Rentang Waktu</label>
            <select id="date_filter" name="date_filter" required>
              <option value="">-- Pilih Rentang Waktu --</option>
              <option value="1_day" <?php echo (isset($_POST['date_filter']) && $_POST['date_filter'] == '1_day') ? 'selected' : ''; ?>>1 Hari Terakhir</option>
              <option value="3_days" <?php echo (isset($_POST['date_filter']) && $_POST['date_filter'] == '3_days') ? 'selected' : ''; ?>>3 Hari Terakhir</option>
              <option value="1_week" <?php echo (isset($_POST['date_filter']) && $_POST['date_filter'] == '1_week') ? 'selected' : ''; ?>>1 Minggu Terakhir</option>
              <option value="1_month" <?php echo (isset($_POST['date_filter']) && $_POST['date_filter'] == '1_month') ? 'selected' : ''; ?>>1 Bulan Terakhir</option>
              <option value="3_months" <?php echo (isset($_POST['date_filter']) && $_POST['date_filter'] == '3_months') ? 'selected' : ''; ?>>3 Bulan Terakhir</option>
              <option value="1_year" <?php echo (isset($_POST['date_filter']) && $_POST['date_filter'] == '1_year') ? 'selected' : ''; ?>>1 Tahun Terakhir</option>
              <option value="all" <?php echo (isset($_POST['date_filter']) && $_POST['date_filter'] == 'all') ? 'selected' : ''; ?>>Keseluruhan Data</option>
            </select>
          </div>
        </div>
        
        <button type="submit" name="generate_report" class="submit-btn">
          <i class="fas fa-search"></i> Preview Laporan
        </button>
      </form>
    </div>
    
    <!-- Report Preview/Results -->
    <?php if ($report_generated && count($report_data) > 0): ?>
    <div class="report-results active" id="reportResults">
      <h2>
        <?php echo $report_title; ?>
        <span class="report-date"><?php echo date('d M Y, H:i'); ?></span>
      </h2>
      
      <?php if ($show_preview): ?>
      <div class="alert alert-info">
        <span class="alert-icon"><i class="fas fa-info-circle"></i></span>
        <span class="alert-message">Preview laporan. Klik "Simpan & Unduh" untuk menyimpan laporan ini.</span>
      </div>
      <?php endif; ?>
      
      <table class="report-table" id="reportTable">
        <thead>
          <tr>
            <?php
            // Dynamic headers based on report type
            if (count($report_data) > 0) {
              foreach (array_keys($report_data[0]) as $header) {
                echo "<th>{$header}</th>";
              }
            }
            ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($report_data as $row): ?>
          <tr>
            <?php foreach ($row as $key => $value): ?>
              <td><?php echo htmlspecialchars($value ?? ''); ?></td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      
      <div class="report-actions">
        <?php if ($show_preview): ?>
        <form method="POST" style="display: inline;">
          <button type="submit" name="save_report" class="save-btn">
            <i class="fas fa-save"></i> Simpan & Unduh
          </button>
        </form>
        <button class="cancel-btn" onclick="cancelPreview()">
          <i class="fas fa-times"></i> Batal
        </button>
        <?php else: ?>
        <button class="download-btn" onclick="downloadPDF()">
          <i class="fas fa-download"></i> Unduh PDF
        </button>
        <?php endif; ?>
      </div>
    </div>
    <?php elseif ($report_generated && count($report_data) == 0): ?>
    <div class="report-results active">
      <div class="alert alert-warning">
        <span class="alert-icon"><i class="fas fa-exclamation-triangle"></i></span>
        <span class="alert-message">Tidak ada data yang ditemukan untuk periode yang dipilih.</span>
      </div>
    </div>
    <?php endif; ?>
    
    <!-- Previous Reports -->
    <div class="previous-reports">
      <h2>Laporan Sebelumnya</h2>
      
      <?php if (count($previous_reports) > 0): ?>
      <ul class="reports-list">
        <?php foreach ($previous_reports as $report): ?>
        <li>
          <div class="report-info">
            <div class="report-icon">
              <?php if ($report['type'] == 'appointments'): ?>
                <i class="fas fa-calendar-check"></i>
              <?php elseif ($report['type'] == 'reminders'): ?>
                <i class="fas fa-bell"></i>
              <?php elseif ($report['type'] == 'todos'): ?>
                <i class="fas fa-tasks"></i>
              <?php elseif ($report['type'] == 'comprehensive'): ?>
                <i class="fas fa-chart-bar"></i>
              <?php endif; ?>
            </div>
            <div class="report-details">
              <span class="report-type">
                <?php
                if ($report['type'] == 'appointments') echo "Laporan Janji Temu";
                elseif ($report['type'] == 'reminders') echo "Laporan Pengingat";
                elseif ($report['type'] == 'todos') echo "Laporan To-Do List";
                elseif ($report['type'] == 'comprehensive') echo "Laporan Komprehensif";
                ?>
                <span class="date-filter-badge">
                  <?php
                  $filter_text = [
                    '1_day' => '1 Hari',
                    '3_days' => '3 Hari',
                    '1_week' => '1 Minggu',
                    '1_month' => '1 Bulan',
                    '3_months' => '3 Bulan',
                    '1_year' => '1 Tahun',
                    'all' => 'Semua'
                  ];
                  echo isset($filter_text[$report['date_filter']]) ? $filter_text[$report['date_filter']] : 'Semua';
                  ?>
                </span>
              </span>
              <span class="report-time"><?php echo date('d M Y, H:i', strtotime($report['generated_at'])); ?></span>
            </div>
          </div>
          <div class="report-actions-list">
            <a href="?view_report=<?php echo $report['report_id']; ?>" class="view-report">
              <i class="fas fa-eye"></i> Lihat
            </a>
            <a href="download_pdf.php?report_id=<?php echo $report['report_id']; ?>" class="download-report" target="_blank">
              <i class="fas fa-download"></i> Unduh
            </a>
            <form method="POST" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus laporan ini?')">
              <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
              <button type="submit" name="delete_report" class="delete-report">
                <i class="fas fa-trash"></i> Hapus
              </button>
            </form>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
      <div class="no-reports">
        <p>Belum ada laporan yang dibuat. Silahkan buat laporan baru.</p>
      </div>
      <?php endif; ?>
    </div>
  </main>

  <script>
    // Toggle sidebar
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
    
    // Cancel preview function
    function cancelPreview() {
      window.location.href = 'reports.php';
    }
    
    // Download PDF function
    function downloadPDF() {
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF();
      
      // Add title
      doc.setFontSize(18);
      doc.text('HealthReminder', 105, 20, { align: 'center' });
      
      // Add report title
      const reportTitle = document.querySelector('.report-results h2').textContent.split('\n')[0];
      doc.setFontSize(14);
      doc.text(reportTitle, 105, 30, { align: 'center' });
      
      // Add date
      const reportDate = new Date().toLocaleDateString('id-ID');
      doc.setFontSize(10);
      doc.text('Tanggal: ' + reportDate, 105, 38, { align: 'center' });
      
      // Get table data
      const table = document.getElementById('reportTable');
      const headers = [];
      const data = [];
      
      // Get headers
      table.querySelectorAll('thead th').forEach(th => {
        headers.push(th.textContent);
      });
      
      // Get data
      table.querySelectorAll('tbody tr').forEach(row => {
        const rowData = [];
        row.querySelectorAll('td').forEach(td => {
          rowData.push(td.textContent);
        });
        data.push(rowData);
      });
      
      // Add table
      doc.autoTable({
        head: [headers],
        body: data,
        startY: 45,
        styles: {
          fontSize: 9,
          cellPadding: 3
        },
        headStyles: {
          fillColor: [57, 155, 200],
          textColor: 255
        }
      });
      
      // Download
      const filename = '<?php echo isset($_SESSION["current_report"]["filename"]) ? $_SESSION["current_report"]["filename"] : "laporan.pdf"; ?>';
      doc.save(filename);
    }
    
    // Animation for stats cards
    function animateStats() {
      const statsCards = document.querySelectorAll('.stat-card');
      statsCards.forEach((card, index) => {
        setTimeout(() => {
          card.style.opacity = '1';
          card.style.transform = 'translateY(0)';
        }, 100 * index);
      });
    }
    
    // Initialize animations
    document.addEventListener('DOMContentLoaded', function() {
      // Add initial styles for animation
      const statsCards = document.querySelectorAll('.stat-card');
      statsCards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.5s ease';
      });
      
      // Start animations
      setTimeout(animateStats, 300);
    });
  </script>
</body>

</html>