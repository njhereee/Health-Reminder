<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}

// News API configuration
$newsApiKey = '1cd9779a27c94f17b18a7bb83887c61e';
$newsApiUrl = 'https://newsapi.org/v2/everything';

// Function to fetch health articles from News API
function fetchHealthArticles($apiKey, $page = 1, $query = 'kesehatan OR health OR medical OR obat OR dokter') {
    $url = "https://newsapi.org/v2/everything?" . http_build_query([
        'q' => $query,
        'language' => 'id',
        'sortBy' => 'publishedAt',
        'pageSize' => 12,
        'page' => $page,
        'apiKey' => $apiKey
    ]);
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'HealthReminder/1.0'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return ['articles' => [], 'totalResults' => 0];
    }
    
    $data = json_decode($response, true);
    return $data ?: ['articles' => [], 'totalResults' => 0];
}

// Get current page
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch articles
if (!empty($searchQuery)) {
    $query = $searchQuery . ' AND (kesehatan OR health OR medical)';
} else {
    $query = 'kesehatan OR health OR medical OR obat OR dokter OR rumah sakit';
}

$newsData = fetchHealthArticles($newsApiKey, $currentPage, $query);
$articles = $newsData['articles'] ?? [];
$totalResults = $newsData['totalResults'] ?? 0;
$totalPages = ceil($totalResults / 12);

// Function to format date in Indonesian
function formatIndonesianDate($dateString) {
    $months = [
        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
        'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
        'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
        'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
    ];
    
    $date = new DateTime($dateString);
    $formatted = $date->format('d F Y');
    
    foreach ($months as $eng => $ind) {
        $formatted = str_replace($eng, $ind, $formatted);
    }
    
    return $formatted;
}

// Function to truncate text
function truncateText($text, $length = 150) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Kesehatan - HealthReminder</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://kit.fontawesome.com/6a3d0e9851.js" crossorigin="anonymous"></script>
    <style>
        main {
            padding: 20px;
        }
        
        .blog-header {
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
        
        .blog-header::before {
            content: '';
            position: absolute;
            bottom: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .blog-header h1 {
            font-size: 36px;
            margin: 0;
            font-weight: 700;
            position: relative;
            z-index: 1;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .blog-header .subtitle {
            font-size: 16px;
            opacity: 0.9;
            margin: 10px 0 0;
            position: relative;
            z-index: 1;
            color: #f0f9f3;
        }
        
        .search-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        
        .search-form {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .search-input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #399bc8;
            box-shadow: 0 0 0 3px rgba(57, 155, 200, 0.1);
        }
        
        .search-btn {
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
        }
        
        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(78, 84, 200, 0.3);
        }
        
        .articles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .article-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .article-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(78, 84, 200, 0.15);
        }
        
        .article-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: linear-gradient(45deg, #f0f2f5, #e2e8f0);
        }
        
        .article-content {
            padding: 20px;
        }
        
        .article-source {
            font-size: 12px;
            color: #399bc8;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .article-title {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin: 0 0 10px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .article-description {
            color: #718096;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .article-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        
        .article-date {
            font-size: 12px;
            color: #a0aec0;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .read-more-btn {
            padding: 8px 16px;
            background: linear-gradient(to right, #399bc8, #6cc4d8);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .read-more-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(78, 84, 200, 0.3);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 40px;
        }
        
        .pagination a, .pagination span {
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            text-decoration: none;
            color: #4a5568;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .pagination a:hover {
            background: #399bc8;
            color: white;
            border-color: #399bc8;
        }
        
        .pagination .current {
            background: #399bc8;
            color: white;
            border-color: #399bc8;
        }
        
        .no-articles {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        
        .no-articles i {
            font-size: 64px;
            color: #cbd5e0;
            margin-bottom: 20px;
        }
        
        .no-articles h3 {
            font-size: 24px;
            color: #4a5568;
            margin-bottom: 10px;
        }
        
        .no-articles p {
            color: #718096;
            font-size: 16px;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #718096;
        }
        
        .loading i {
            font-size: 32px;
            animation: spin 2s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .stats-info {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        
        .stats-info p {
            margin: 0;
            color: #718096;
            font-size: 14px;
        }
        
        .stats-info strong {
            color: #399bc8;
            font-weight: 700;
        }
        
        @media (max-width: 768px) {
            .articles-grid {
                grid-template-columns: 1fr;
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .search-input {
                width: 100%;
            }
            
            .article-footer {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            
            .pagination {
                flex-wrap: wrap;
                gap: 5px;
            }
            
            .pagination a, .pagination span {
                padding: 8px 12px;
                font-size: 14px;
            }
        }
    </style>
</head>

<body>
    <!-- Tombol toggle -->
    <div class="sidebar-toggle" id="toggle-btn">
        <i class="fas fa-bars"></i>
    </div>

    <?php include 'sidebar.php' ?>
    
    <!-- Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Konten utama -->
    <main id="blog-content">

        <!-- Header Section -->
        <div class="blog-header">
            <h1>Blog Kesehatan</h1>
            <p class="subtitle">Artikel kesehatan terkini untuk hidup yang lebih sehat</p>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <form method="GET" class="search-form">
                <input 
                    type="text" 
                    name="search" 
                    class="search-input" 
                    placeholder="Cari artikel kesehatan..." 
                    value="<?php echo htmlspecialchars($searchQuery); ?>"
                >
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i>
                    Cari
                </button>
                <?php if (!empty($searchQuery)): ?>
                <a href="healthblog.php" class="search-btn" style="background: #6b7280;">
                    <i class="fas fa-times"></i>
                    Reset
                </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Stats Info -->
        <?php if (!empty($articles)): ?>
        <div class="stats-info">
            <p>
                Menampilkan <strong><?php echo count($articles); ?></strong> artikel 
                dari <strong><?php echo number_format($totalResults); ?></strong> artikel tersedia
                <?php if (!empty($searchQuery)): ?>
                untuk pencarian "<strong><?php echo htmlspecialchars($searchQuery); ?></strong>"
                <?php endif; ?>
                (Halaman <?php echo $currentPage; ?> dari <?php echo $totalPages; ?>)
            </p>
        </div>
        <?php endif; ?>

        <!-- Articles Grid -->
        <?php if (!empty($articles)): ?>
        <div class="articles-grid">
            <?php foreach ($articles as $article): ?>
            <div class="article-card">
                <?php if (!empty($article['urlToImage'])): ?>
                <img 
                    src="<?php echo htmlspecialchars($article['urlToImage']); ?>" 
                    alt="<?php echo htmlspecialchars($article['title']); ?>"
                    class="article-image"
                    onerror="this.style.display='none'"
                >
                <?php else: ?>
                <div class="article-image" style="display: flex; align-items: center; justify-content: center; background: linear-gradient(45deg, #f0f2f5, #e2e8f0);">
                    <i class="fas fa-newspaper" style="font-size: 48px; color: #cbd5e0;"></i>
                </div>
                <?php endif; ?>
                
                <div class="article-content">
                    <div class="article-source">
                        <i class="fas fa-globe"></i>
                        <?php echo htmlspecialchars($article['source']['name'] ?? 'Sumber Tidak Diketahui'); ?>
                    </div>
                    
                    <h3 class="article-title">
                        <?php echo htmlspecialchars($article['title']); ?>
                    </h3>
                    
                    <?php if (!empty($article['description'])): ?>
                    <p class="article-description">
                        <?php echo htmlspecialchars(truncateText($article['description'], 120)); ?>
                    </p>
                    <?php endif; ?>
                    
                    <div class="article-footer">
                        <div class="article-date">
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo formatIndonesianDate($article['publishedAt']); ?>
                        </div>
                        
                        <a 
                            href="<?php echo htmlspecialchars($article['url']); ?>" 
                            target="_blank" 
                            rel="noopener noreferrer"
                            class="read-more-btn"
                        >
                            Baca Selengkapnya
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($currentPage > 1): ?>
            <a href="?page=<?php echo $currentPage - 1; ?><?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>">
                <i class="fas fa-chevron-left"></i> Sebelumnya
            </a>
            <?php endif; ?>
            
            <?php
            $startPage = max(1, $currentPage - 2);
            $endPage = min($totalPages, $currentPage + 2);
            
            if ($startPage > 1): ?>
            <a href="?page=1<?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>">1</a>
            <?php if ($startPage > 2): ?>
            <span>...</span>
            <?php endif; ?>
            <?php endif; ?>
            
            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <?php if ($i == $currentPage): ?>
            <span class="current"><?php echo $i; ?></span>
            <?php else: ?>
            <a href="?page=<?php echo $i; ?><?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($endPage < $totalPages): ?>
            <?php if ($endPage < $totalPages - 1): ?>
            <span>...</span>
            <?php endif; ?>
            <a href="?page=<?php echo $totalPages; ?><?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>"><?php echo $totalPages; ?></a>
            <?php endif; ?>
            
            <?php if ($currentPage < $totalPages): ?>
            <a href="?page=<?php echo $currentPage + 1; ?><?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>">
                Selanjutnya <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <!-- No Articles Found -->
        <div class="no-articles">
            <i class="fas fa-search"></i>
            <h3>Tidak Ada Artikel Ditemukan</h3>
            <p>
                <?php if (!empty($searchQuery)): ?>
                Maaf, tidak ada artikel yang cocok dengan pencarian "<?php echo htmlspecialchars($searchQuery); ?>". 
                Coba gunakan kata kunci yang berbeda.
                <?php else: ?>
                Saat ini tidak ada artikel kesehatan yang tersedia. Silakan coba lagi nanti.
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>
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
        
        // Animate article cards on load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.article-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = 'all 0.6s ease';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 * index);
            });
        });
        
        // Handle image loading errors
        document.querySelectorAll('.article-image').forEach(img => {
            img.addEventListener('error', function() {
                this.style.display = 'none';
                // Create placeholder
                const placeholder = document.createElement('div');
                placeholder.className = 'article-image';
                placeholder.style.cssText = 'display: flex; align-items: center; justify-content: center; background: linear-gradient(45deg, #f0f2f5, #e2e8f0);';
                placeholder.innerHTML = '<i class="fas fa-newspaper" style="font-size: 48px; color: #cbd5e0;"></i>';
                this.parentNode.insertBefore(placeholder, this);
            });
        });
    </script>
</body>
</html>