<?php
// Helper function untuk membuat laporan komprehensif
function generateComprehensiveReport($conn, $user_id, $date_condition) {
    $comprehensive_data = [];
    
    try {
        // Get Appointments
        $query = "SELECT 
                    'Appointment' as 'Kategori',
                    title as 'Detail',
                    date as 'Tanggal',
                    time as 'Waktu',
                    'Terjadwal' as 'Status'
                  FROM appointments 
                  WHERE user_id = ? " . str_replace('COALESCE(date, remind_date, due_date)', 'date', $date_condition) . "
                  ORDER BY date ASC, time ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $comprehensive_data[] = $row;
        }
        $stmt->close();
        
        // Get Reminders
        $query = "SELECT 
                    'Pengingat' as 'Kategori',
                    title as 'Detail',
                    remind_date as 'Tanggal',
                    remind_time as 'Waktu',
                    CASE WHEN is_done = 1 THEN 'Selesai' ELSE 'Belum Selesai' END as 'Status'
                  FROM reminders 
                  WHERE user_id = ? " . str_replace('COALESCE(date, remind_date, due_date)', 'remind_date', $date_condition) . "
                  ORDER BY remind_date ASC, remind_time ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $comprehensive_data[] = $row;
        }
        $stmt->close();
        
        // Get Todos
        $query = "SELECT 
                    'To-Do' as 'Kategori',
                    task as 'Detail',
                    due_date as 'Tanggal',
                    '' as 'Waktu',
                    CASE WHEN is_done = 1 THEN 'Selesai' ELSE 'Belum Selesai' END as 'Status'
                  FROM todos 
                  WHERE user_id = ? " . str_replace('COALESCE(date, remind_date, due_date)', 'due_date', $date_condition) . "
                  ORDER BY due_date ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $comprehensive_data[] = $row;
        }
        $stmt->close();
        
        // Sort by date
        usort($comprehensive_data, function($a, $b) {
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
        
    } catch (Exception $e) {
        error_log("Error generating comprehensive report: " . $e->getMessage());
        return [];
    }
    
    return $comprehensive_data;
}

// Helper function untuk format tanggal Indonesia
function formatDateIndonesian($date) {
    if (empty($date) || $date == '0000-00-00') {
        return '-';
    }
    
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $timestamp = strtotime($date);
    $day = date('d', $timestamp);
    $month = $months[(int)date('n', $timestamp)];
    $year = date('Y', $timestamp);
    
    return $day . ' ' . $month . ' ' . $year;
}

// Helper function untuk format waktu
function formatTimeIndonesian($time) {
    if (empty($time) || $time == '00:00:00') {
        return '-';
    }
    
    return date('H:i', strtotime($time));
}
?>