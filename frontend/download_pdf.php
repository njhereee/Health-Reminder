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

// Get report data
if (isset($_GET['report_id'])) {
    $report_id = $_GET['report_id'];
    
    $query = "SELECT * FROM reports WHERE report_id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $report_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $report = $result->fetch_assoc();
    $stmt->close();
    
    if ($report) {
        $report_data = json_decode($report['data'], true);
        $report_type = $report['type'];
        $date_filter = $report['date_filter'];
        $generated_at = $report['generated_at'];
        
        // Determine report title
        $report_titles = [
            'appointments' => 'Laporan Janji Temu',
            'reminders' => 'Laporan Pengingat',
            'todos' => 'Laporan To-Do List',
            'comprehensive' => 'Laporan Komprehensif'
        ];
        
        $report_title = $report_titles[$report_type] ?? 'Laporan';
        
        // Determine date filter text
        $filter_texts = [
            '1_day' => '1 Hari Terakhir',
            '3_days' => '3 Hari Terakhir',
            '1_week' => '1 Minggu Terakhir',
            '1_month' => '1 Bulan Terakhir',
            '3_months' => '3 Bulan Terakhir',
            '1_year' => '1 Tahun Terakhir',
            'all' => 'Keseluruhan Data'
        ];
        
        $filter_text = $filter_texts[$date_filter] ?? 'Semua Data';
        
        // Store in session for PDF generation
        $_SESSION['pdf_report'] = [
            'data' => $report_data,
            'title' => $report_title,
            'type' => $report_type,
            'filter_text' => $filter_text,
            'generated_at' => $generated_at,
            'filename' => $report['pdf_filename']
        ];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download PDF - HealthReminder</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #f7fafc;
        }
        .download-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-radius: 50%;
            border-top: 4px solid #399bc8;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .download-btn {
            background: #48bb78;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
        }
        .download-btn:hover {
            background: #38a169;
        }
    </style>
</head>
<body>
    <div class="download-container">
        <h2>Mengunduh Laporan</h2>
        <div class="spinner"></div>
        <p>Sedang mempersiapkan file PDF...</p>
        <button class="download-btn" onclick="generateAndDownloadPDF()" style="display: none;" id="downloadBtn">
            Unduh PDF
        </button>
    </div>

    <script>
        <?php if (isset($_SESSION['pdf_report'])): ?>
        const reportData = <?php echo json_encode($_SESSION['pdf_report']); ?>;
        
        function generateAndDownloadPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // Add header
            doc.setFontSize(20);
            doc.setTextColor(57, 155, 200);
            doc.text('HealthReminder', 105, 20, { align: 'center' });
            
            // Add report title
            doc.setFontSize(16);
            doc.setTextColor(45, 55, 72);
            doc.text(reportData.title, 105, 35, { align: 'center' });
            
            // Add filter info
            doc.setFontSize(12);
            doc.setTextColor(113, 128, 150);
            doc.text('Rentang Waktu: ' + reportData.filter_text, 105, 45, { align: 'center' });
            
            // Add generation date
            const generatedDate = new Date(reportData.generated_at).toLocaleDateString('id-ID', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            doc.text('Dibuat pada: ' + generatedDate, 105, 52, { align: 'center' });
            
            if (reportData.data && reportData.data.length > 0) {
                // Get headers and data
                const headers = Object.keys(reportData.data[0]);
                const tableData = reportData.data.map(row => Object.values(row));
                
                // Add table
                doc.autoTable({
                    head: [headers],
                    body: tableData,
                    startY: 60,
                    styles: {
                        fontSize: 9,
                        cellPadding: 4,
                        overflow: 'linebreak'
                    },
                    headStyles: {
                        fillColor: [57, 155, 200],
                        textColor: 255,
                        fontStyle: 'bold'
                    },
                    alternateRowStyles: {
                        fillColor: [248, 250, 252]
                    },
                    margin: { left: 14, right: 14 }
                });
            } else {
                doc.setFontSize(12);
                doc.text('Tidak ada data untuk ditampilkan', 105, 70, { align: 'center' });
            }
            
            // Add footer
            const pageCount = doc.internal.getNumberOfPages();
            for (let i = 1; i <= pageCount; i++) {
                doc.setPage(i);
                doc.setFontSize(8);
                doc.setTextColor(113, 128, 150);
                doc.text(
                    'Halaman ' + i + ' dari ' + pageCount + ' | Generated by HealthReminder',
                    105,
                    doc.internal.pageSize.height - 10,
                    { align: 'center' }
                );
            }
            
            // Download
            doc.save(reportData.filename);
            
            // Close window after download
            setTimeout(() => {
                window.close();
            }, 1000);
        }
        
        // Auto-generate PDF after page loads
        setTimeout(() => {
            document.querySelector('.spinner').style.display = 'none';
            document.querySelector('p').textContent = 'File PDF siap diunduh!';
            document.getElementById('downloadBtn').style.display = 'inline-block';
            generateAndDownloadPDF();
        }, 1500);
        
        <?php else: ?>
        setTimeout(() => {
            document.querySelector('.spinner').style.display = 'none';
            document.querySelector('p').textContent = 'Laporan tidak ditemukan!';
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>

<?php
// Clear the session data
if (isset($_SESSION['pdf_report'])) {
    unset($_SESSION['pdf_report']);
}
?>