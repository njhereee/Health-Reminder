<?php
session_start();
if (!isset($_SESSION['username'])) {
  header("Location: login.html");
  exit();
}

// Database connection
include '../database/db.php';

// Get current user ID
$username = $_SESSION['username'];
$sql = "SELECT user_id FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$user_id = $row['user_id'];

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Add new task
  if (isset($_POST['add_task'])) {
    $task = $_POST['task'];
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : NULL;

    $sql = "INSERT INTO todos (user_id, task, due_date) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $task, $due_date);
    
    if ($stmt->execute()) {
      $success_message = "Tugas berhasil ditambahkan!";
    } else {
      $error_message = "Error: " . $conn->error;
    }
  }
  
  // Mark task as done/undone
  if (isset($_POST['toggle_task'])) {
    $todo_id = $_POST['todo_id'];
    $is_done = $_POST['is_done'] == 0 ? 1 : 0; // Toggle status
    
    $sql = "UPDATE todos SET is_done = ? WHERE todo_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $is_done, $todo_id, $user_id);
    $stmt->execute();
    
    // Return JSON for AJAX calls
    if (isset($_POST['ajax'])) {
      echo json_encode(['success' => true, 'is_done' => $is_done]);
      exit();
    }
  }
  
  // Delete task
  if (isset($_POST['delete_task'])) {
    $todo_id = $_POST['todo_id'];
    
    $sql = "DELETE FROM todos WHERE todo_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $todo_id, $user_id);
    $stmt->execute();
    
    // Return JSON for AJAX calls
    if (isset($_POST['ajax'])) {
      echo json_encode(['success' => true]);
      exit();
    }
  }
}

// Get user's todo list
$sql = "SELECT * FROM todos WHERE user_id = ? ORDER BY is_done ASC, due_date ASC, created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$todos = $stmt->get_result();

// Count tasks
$total_tasks = $todos->num_rows;
$sql = "SELECT COUNT(*) as completed FROM todos WHERE user_id = ? AND is_done = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$completed_tasks = $row['completed'];
$progress_percentage = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>To-Do List - HealthReminder</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/todolist.css">
  <script src="https://kit.fontawesome.com/6a3d0e9851.js" crossorigin="anonymous"></script>
  <style>
    /* Modal styles - inline styles for the modal functionality */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0, 0, 0, 0.5);
      transition: all 0.3s ease;
    }
    
    .modal-content {
      background-color: #fff;
      margin: 10% auto;
      padding: 25px;
      border-radius: 12px;
      box-shadow: 0 4px 25px rgba(0, 0, 0, 0.2);
      width: 80%;
      max-width: 600px;
      position: relative;
      transform: translateY(-20px);
      opacity: 0;
      transition: all 0.3s ease;
      border-left: 4px solid #399bc8;
    }
    
    .modal.show .modal-content {
      transform: translateY(0);
      opacity: 1;
    }
    
    .close-modal {
      position: absolute;
      top: 15px;
      right: 15px;
      font-size: 24px;
      color: #718096;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .close-modal:hover {
      color: #e53e3e;
    }
    
    .modal-title {
      margin-top: 0;
      margin-bottom: 20px;
      color: #2d3748;
      font-weight: 600;
      padding-bottom: 10px;
      border-bottom: 2px solid #e2e8f0;
    }
    
    .form-row {
      display: flex;
      gap: 15px;
      margin-bottom: 15px;
    }
    
    .form-row > div {
      flex: 1;
    }
    
    .form-row label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
      color: #4a5568;
    }
    
    .notification {
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      animation: fadeIn 0.5s ease-out;
    }
    
    .notification i {
      margin-right: 10px;
      font-size: 18px;
    }
    
    .notification.success {
      background-color: #d1fae5;
      color: #065f46;
      border-left: 4px solid #10b981;
    }
    
    .notification.error {
      background-color: #fee2e2;
      color: #b91c1c;
      border-left: 4px solid #ef4444;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    /* Modal form styles */
    #todo-form input[type="text"],
    #todo-form input[type="date"],
    #todo-form textarea {
      border: 1px solid #e2e8f0;
      padding: 12px 15px;
      border-radius: 8px;
      margin-bottom: 15px;
      width: 100%;
      background: #f8fafc;
      transition: all 0.3s;
    }
    
    #todo-form input:focus,
    #todo-form textarea:focus {
      outline: none;
      border-color: #399bc8;
      background: #fff;
      box-shadow: 0 0 0 3px rgba(78, 84, 200, 0.1);
    }
    
    #todo-form button {
      background: linear-gradient(to right, #399bc8, #6cc4d8);
      color: white;
      border: none;
      padding: 12px 20px;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      width: 100%;
      transition: all 0.3s;
    }
    
    #todo-form button:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(78, 84, 200, 0.2);
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
  <main id="todolist-content">
    <!-- Header Section -->
    <div class="page-header">
      <h1>To-Do List</h1>
      <p class="subtitle">Kelola daftar tugas kesehatan harian Anda</p>
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

    <div class="todo-container">
      <!-- Todo List -->
      <div class="todo-list-card">
        <div class="todo-list-header">
          <h2>Daftar Tugas</h2>
          <div>
            <span id="taskCount"><?php echo $completed_tasks; ?> dari <?php echo $total_tasks; ?> selesai</span>
            <button id="open-modal-btn" class="add-btn">
              <i class="fas fa-plus-circle"></i> Tambah Tugas
            </button>
          </div>
        </div>

        <!-- Progress Bar -->
        <div class="progress-container">
          <div class="progress-bar-bg">
            <div class="progress-bar" style="width: <?php echo $progress_percentage; ?>%"></div>
          </div>
          <div class="progress-text">
            <span>Progress</span>
            <span><?php echo $progress_percentage; ?>%</span>
          </div>
        </div>

        <?php if ($todos->num_rows > 0): ?>
          <ul class="todo-list">
            <?php while($todo = $todos->fetch_assoc()): ?>
              <li class="todo-item <?php echo $todo['is_done'] ? 'todo-done' : ''; ?>" id="todo-<?php echo $todo['todo_id']; ?>">
                <form method="POST" action="" class="toggle-form">
                  <input type="hidden" name="todo_id" value="<?php echo $todo['todo_id']; ?>">
                  <input type="hidden" name="is_done" value="<?php echo $todo['is_done']; ?>">
                  <input type="checkbox" name="status" class="todo-checkbox" <?php echo $todo['is_done'] ? 'checked' : ''; ?> onchange="this.form.submit()">
                  <input type="hidden" name="toggle_task" value="1">
                </form>
                
                <div class="todo-content">
                  <div class="todo-text"><?php echo htmlspecialchars($todo['task']); ?></div>
                  <div class="todo-details">
                    <?php if($todo['due_date']): ?>
                      <div class="todo-date">
                        <i class="far fa-calendar-alt"></i>
                        <?php 
                        $due_date = new DateTime($todo['due_date']);
                        $today = new DateTime();
                        $interval = $today->diff($due_date);
                        $date_class = '';
                        
                        // Format due date in Indonesian format
                        setlocale(LC_TIME, 'id_ID');
                        echo strftime('%d %B %Y', strtotime($todo['due_date']));
                        
                        // Show days left
                        if ($due_date < $today && !$todo['is_done']) {
                          echo ' <span style="color: #f56565;">(Terlambat)</span>';
                        } elseif ($interval->days == 0 && !$todo['is_done']) {
                          echo ' <span style="color: #ed8936;">(Hari ini)</span>';
                        } elseif ($interval->days == 1 && !$todo['is_done']) {
                          echo ' <span style="color: #ed8936;">(Besok)</span>';
                        } elseif (!$todo['is_done']) {
                          echo ' <span style="color: #718096;">(' . $interval->days . ' hari lagi)</span>';
                        }
                        ?>
                      </div>
                    <?php endif; ?>
                    
                    <div class="todo-date">
                      <i class="far fa-clock"></i>
                      <?php 
                        $created_date = new DateTime($todo['created_at']);
                        echo $created_date->format('H:i, d M Y'); 
                      ?>
                    </div>
                  </div>
                </div>
                
                <div class="todo-actions">
                  <form method="POST" action="" class="toggle-form">
                    <input type="hidden" name="todo_id" value="<?php echo $todo['todo_id']; ?>">
                    <input type="hidden" name="is_done" value="<?php echo $todo['is_done']; ?>">
                    <button type="submit" name="toggle_task" class="todo-btn btn-toggle tooltip" data-tooltip="<?php echo $todo['is_done'] ? 'Tandai belum selesai' : 'Tandai selesai'; ?>">
                      <i class="fas <?php echo $todo['is_done'] ? 'fa-times' : 'fa-check'; ?>"></i>
                    </button>
                  </form>
                  
                  <form method="POST" action="" class="delete-form">
                    <input type="hidden" name="todo_id" value="<?php echo $todo['todo_id']; ?>">
                    <button type="submit" name="delete_task" class="todo-btn btn-delete tooltip" data-tooltip="Hapus">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </form>
                </div>
              </li>
            <?php endwhile; ?>
          </ul>
        <?php else: ?>
          <div class="no-tasks">
            <i class="fas fa-clipboard-list"></i>
            <h3>Belum ada tugas</h3>
            <p>Tambahkan tugas baru untuk memulai kebiasaan hidup sehat Anda</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <!-- Modal Form Tambah Tugas -->
  <div id="todo-modal" class="modal">
    <div class="modal-content">
      <span class="close-modal">&times;</span>
      <h3 class="modal-title"><i class="fas fa-plus-circle"></i> Tambah Tugas Baru</h3>
      
      <form id="todo-form" method="POST" action="todolist.php">
        <div class="form-row">
          <div>
            <label for="task"><i class="fas fa-pen"></i> Tugas</label>
            <input type="text" id="task" name="task" placeholder="Contoh: Minum obat diabetes" required>
          </div>
        </div>
        <div class="form-row">
          <div>
            <label for="due_date"><i class="fas fa-calendar"></i> Tenggat Waktu (Opsional)</label>
            <input type="date" id="due_date" name="due_date" min="<?php echo date('Y-m-d'); ?>">
          </div>
        </div>
        <button type="submit" name="add_task"><i class="fas fa-plus-circle"></i> Tambah Tugas</button>
      </form>
    </div>
  </div>

  <script>
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const toggleBtn = document.getElementById('toggle-btn');
    const modal = document.getElementById('todo-modal');
    const openModalBtn = document.getElementById('open-modal-btn');
    const closeModalBtn = document.querySelector('.close-modal');
    
    // Toggle sidebar & overlay
    function toggleSidebar() {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('active');
      toggleBtn.classList.toggle('rotate');
    }
    
    toggleBtn.addEventListener('click', toggleSidebar);
    overlay.addEventListener('click', toggleSidebar);
    
    // Modal functions
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
    
    // Event listeners for modal
    openModalBtn.addEventListener('click', openModal);
    closeModalBtn.addEventListener('click', closeModal);
    
    // Close modal when clicking outside of it
    window.addEventListener('click', (event) => {
      if (event.target === modal) {
        closeModal();
      }
    });
    
    // Optional: Add AJAX functionality for smoother interactions
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
      
      // Add the current date to the date input by default for new tasks
      const dateInput = document.getElementById('due_date');
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
      
      // If there's a success message, auto-close the modal
      <?php if (isset($success_message)): ?>
        closeModal();
      <?php endif; ?>
      
      // Change checkbox status with AJAX
      document.querySelectorAll('.todo-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
          const form = this.closest('form');
          const todoId = form.querySelector('input[name="todo_id"]').value;
          const isDone = form.querySelector('input[name="is_done"]').value;
          const todoItem = document.getElementById('todo-' + todoId);
          
          // Create form data
          const formData = new FormData();
          formData.append('todo_id', todoId);
          formData.append('is_done', isDone);
          formData.append('toggle_task', '1');
          formData.append('ajax', '1');
          
          // Send AJAX request
          fetch('todolist.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Update UI
              if (data.is_done) {
                todoItem.classList.add('todo-done');
              } else {
                todoItem.classList.remove('todo-done');
              }
              
              // Update the hidden is_done value
              form.querySelector('input[name="is_done"]').value = data.is_done;
              
              // Update task count and progress
              updateTaskProgress();
            }
          })
          .catch(error => {
            console.error('Error:', error);
          });
        });
      });
      
      // Delete task with AJAX
      document.querySelectorAll('.delete-form').forEach(form => {
        form.addEventListener('submit', function(event) {
          event.preventDefault();
          
          if (confirm('Apakah Anda yakin ingin menghapus tugas ini?')) {
            const todoId = this.querySelector('input[name="todo_id"]').value;
            const todoItem = document.getElementById('todo-' + todoId);
            
            // Create form data
            const formData = new FormData();
            formData.append('todo_id', todoId);
            formData.append('delete_task', '1');
            formData.append('ajax', '1');
            
            // Send AJAX request
            fetch('todolist.php', {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                // Remove the todo item from DOM
                todoItem.style.opacity = '0';
                todoItem.style.height = '0';
                todoItem.style.marginBottom = '0';
                todoItem.style.padding = '0';
                todoItem.style.overflow = 'hidden';
                
                setTimeout(() => {
                  todoItem.remove();
                  updateTaskProgress();
                  
                  // If no tasks left, show "no tasks" message
                  const todoList = document.querySelector('.todo-list');
                  if (todoList && todoList.children.length === 0) {
                    const noTasksDiv = document.createElement('div');
                    noTasksDiv.className = 'no-tasks';
                    noTasksDiv.innerHTML = `
                      <i class="fas fa-clipboard-list"></i>
                      <h3>Belum ada tugas</h3>
                      <p>Tambahkan tugas baru untuk memulai kebiasaan hidup sehat Anda</p>
                    `;
                    todoList.parentNode.replaceChild(noTasksDiv, todoList);
                  }
                }, 300);
              }
            })
            .catch(error => {
              console.error('Error:', error);
            });
          }
        });
      });
      
      // Toggle task status with AJAX
      document.querySelectorAll('.btn-toggle').forEach(button => {
        button.addEventListener('click', function(event) {
          event.preventDefault();
          const form = this.closest('form');
          form.submit();
        });
      });
      
      // Function to update task progress
      function updateTaskProgress() {
        let total = document.querySelectorAll('.todo-item').length;
        let completed = document.querySelectorAll('.todo-done').length;
        let percentage = total > 0 ? Math.round((completed / total) * 100) : 0;
        
        document.getElementById('taskCount').textContent = completed + ' dari ' + total + ' selesai';
        document.querySelector('.progress-bar').style.width = percentage + '%';
        document.querySelector('.progress-text span:last-child').textContent = percentage + '%';
      }
      
      // Add transition for list items
      document.querySelectorAll('.todo-item').forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        item.style.transition = 'all 0.3s ease';
        
        setTimeout(() => {
          item.style.opacity = '1';
          item.style.transform = 'translateY(0)';
        }, 100 * index);
      });
    });
  </script>
</body>

</html>