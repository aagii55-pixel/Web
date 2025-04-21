<?php
session_start();
require 'config/db.php';

function ensureAdminAccess() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'Admin') {
        header('Location: ../a/login.php');
        exit();
    }
}

ensureAdminAccess();

// Initialize variables
$search_type = $_GET['search_type'] ?? 'users';
$search_query = $_GET['query'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sport_filter = $_GET['sport'] ?? '';
$location_filter = $_GET['location'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'date_desc';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get pending promotion requests count for nav
$pendingCountQuery = "SELECT COUNT(*) as pendingCount FROM PromotionRequest WHERE RequestStatus = 'Pending'";
$pendingCountResult = $conn->query($pendingCountQuery);
$pendingCountRow = $pendingCountResult->fetch_assoc();
$pendingCount = $pendingCountRow['pendingCount'];

// Get available filter options
$available_statuses = [];
$available_sports = [];
$available_locations = [];

// Handle search and filtering
$where_clauses = [];
$params = [];
$param_types = '';
$total_results = 0;
$results = [];

// Get sport types for filter
$sport_query = "SELECT DISTINCT SportType FROM VenueSports ORDER BY SportType";
$sport_result = $conn->query($sport_query);
while ($row = $sport_result->fetch_assoc()) {
    $available_sports[] = $row['SportType'];
}

// Get locations for filter
$location_query = "SELECT DISTINCT Location FROM Venue ORDER BY Location";
$location_result = $conn->query($location_query);
while ($row = $location_result->fetch_assoc()) {
    $available_locations[] = $row['Location'];
}

// Build search query based on type
switch ($search_type) {
    case 'users':
        $available_statuses = ['Active', 'Inactive', 'Suspended'];
        $base_query = "SELECT u.*, r.RoleName 
                       FROM User u 
                       JOIN Role r ON u.RoleID = r.RoleID";
        
        if (!empty($search_query)) {
            $where_clauses[] = "(u.Name LIKE ? OR u.Email LIKE ? OR u.Phone LIKE ?)";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
            $param_types .= 'sss';
        }
        
        if (!empty($status_filter)) {
            $where_clauses[] = "u.Status = ?";
            $params[] = $status_filter;
            $param_types .= 's';
        }
        
        if (!empty($date_from)) {
            $where_clauses[] = "u.CreatedAt >= ?";
            $params[] = $date_from;
            $param_types .= 's';
        }
        
        if (!empty($date_to)) {
            $where_clauses[] = "u.CreatedAt <= ?";
            $params[] = $date_to . ' 23:59:59';
            $param_types .= 's';
        }
        
        // Sort options
        $sort_options = [
            'name_asc' => 'u.Name ASC',
            'name_desc' => 'u.Name DESC',
            'date_asc' => 'u.CreatedAt ASC',
            'date_desc' => 'u.CreatedAt DESC',
            'role_asc' => 'r.RoleName ASC',
            'role_desc' => 'r.RoleName DESC'
        ];
        $order_by = $sort_options[$sort_by] ?? 'u.CreatedAt DESC';
        break;
        
    case 'venues':
        $available_statuses = ['Active', 'Maintenance', 'Closed'];
        $base_query = "SELECT v.*, u.Name as ManagerName, 
                      (SELECT GROUP_CONCAT(vs.SportType SEPARATOR ', ') FROM VenueSports vs WHERE vs.VenueID = v.VenueID) as Sports
                      FROM Venue v
                      LEFT JOIN User u ON v.ManagerID = u.UserID";
        
        if (!empty($search_query)) {
            $where_clauses[] = "(v.Name LIKE ? OR v.Location LIKE ? OR v.Description LIKE ?)";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
            $param_types .= 'sss';
        }
        
        if (!empty($status_filter)) {
            $where_clauses[] = "v.Status = ?";
            $params[] = $status_filter;
            $param_types .= 's';
        }
        
        if (!empty($sport_filter)) {
            $where_clauses[] = "EXISTS (SELECT 1 FROM VenueSports vs WHERE vs.VenueID = v.VenueID AND vs.SportType = ?)";
            $params[] = $sport_filter;
            $param_types .= 's';
        }
        
        if (!empty($location_filter)) {
            $where_clauses[] = "v.Location LIKE ?";
            $params[] = "%$location_filter%";
            $param_types .= 's';
        }
        
        $sort_options = [
            'name_asc' => 'v.Name ASC',
            'name_desc' => 'v.Name DESC',
            'price_asc' => 'v.HourlyPrice ASC',
            'price_desc' => 'v.HourlyPrice DESC',
            'location_asc' => 'v.Location ASC',
            'location_desc' => 'v.Location DESC'
        ];
        $order_by = $sort_options[$sort_by] ?? 'v.Name ASC';
        break;
        
    case 'bookings':
        $available_statuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
        $base_query = "SELECT b.*, v.Name as VenueName, u.Name as UserName,
                       vts.StartTime, vts.EndTime, vts.DayOfWeek,
                       (SELECT Status FROM Payment WHERE BookingID = b.BookingID LIMIT 1) as PaymentStatus
                       FROM Booking b
                       JOIN Venue v ON b.VenueID = v.VenueID
                       JOIN User u ON b.UserID = u.UserID
                       JOIN VenueTimeSlot vts ON b.SlotID = vts.SlotID";
        
        if (!empty($search_query)) {
            $where_clauses[] = "(b.BookingID LIKE ? OR v.Name LIKE ? OR u.Name LIKE ?)";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
            $param_types .= 'sss';
        }
        
        if (!empty($status_filter)) {
            $where_clauses[] = "b.Status = ?";
            $params[] = $status_filter;
            $param_types .= 's';
        }
        
        if (!empty($date_from)) {
            $where_clauses[] = "b.BookingDate >= ?";
            $params[] = $date_from;
            $param_types .= 's';
        }
        
        if (!empty($date_to)) {
            $where_clauses[] = "b.BookingDate <= ?";
            $params[] = $date_to;
            $param_types .= 's';
        }
        
        if (!empty($sport_filter)) {
            $where_clauses[] = "EXISTS (SELECT 1 FROM VenueSports vs WHERE vs.VenueID = b.VenueID AND vs.SportType = ?)";
            $params[] = $sport_filter;
            $param_types .= 's';
        }
        
        if (!empty($location_filter)) {
            $where_clauses[] = "v.Location LIKE ?";
            $params[] = "%$location_filter%";
            $param_types .= 's';
        }
        
        $sort_options = [
            'date_asc' => 'b.BookingDate ASC, vts.StartTime ASC',
            'date_desc' => 'b.BookingDate DESC, vts.StartTime DESC',
            'venue_asc' => 'v.Name ASC, b.BookingDate DESC',
            'venue_desc' => 'v.Name DESC, b.BookingDate DESC',
            'user_asc' => 'u.Name ASC, b.BookingDate DESC',
            'user_desc' => 'u.Name DESC, b.BookingDate DESC',
            'status_asc' => 'b.Status ASC, b.BookingDate DESC',
            'status_desc' => 'b.Status DESC, b.BookingDate DESC'
        ];
        $order_by = $sort_options[$sort_by] ?? 'b.BookingDate DESC, vts.StartTime ASC';
        break;
        
    case 'payments':
        $available_statuses = ['Pending', 'Completed', 'Refunded', 'Failed'];
        $base_query = "SELECT p.*, b.BookingID, u.Name as UserName, v.Name as VenueName
                       FROM Payment p
                       JOIN Booking b ON p.BookingID = b.BookingID
                       JOIN User u ON b.UserID = u.UserID
                       JOIN Venue v ON b.VenueID = v.VenueID";
        
        if (!empty($search_query)) {
            $where_clauses[] = "(p.PaymentID LIKE ? OR b.BookingID LIKE ? OR u.Name LIKE ? OR v.Name LIKE ?)";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
            $param_types .= 'ssss';
        }
        
        if (!empty($status_filter)) {
            $where_clauses[] = "p.Status = ?";
            $params[] = $status_filter;
            $param_types .= 's';
        }
        
        if (!empty($date_from)) {
            $where_clauses[] = "p.PaymentDate >= ?";
            $params[] = $date_from;
            $param_types .= 's';
        }
        
        if (!empty($date_to)) {
            $where_clauses[] = "p.PaymentDate <= ?";
            $params[] = $date_to . ' 23:59:59';
            $param_types .= 's';
        }
        
        $sort_options = [
            'date_asc' => 'p.PaymentDate ASC',
            'date_desc' => 'p.PaymentDate DESC',
            'amount_asc' => 'p.Amount ASC',
            'amount_desc' => 'p.Amount DESC',
            'status_asc' => 'p.Status ASC',
            'status_desc' => 'p.Status DESC'
        ];
        $order_by = $sort_options[$sort_by] ?? 'p.PaymentDate DESC';
        break;
        
    case 'reports':
        if (!empty($_GET['report_type'])) {
            switch ($_GET['report_type']) {
                case 'daily_bookings':
                    $base_query = "SELECT DATE(b.BookingDate) as Date, 
                                  COUNT(*) as TotalBookings, 
                                  SUM(vts.Price) as TotalRevenue,
                                  COUNT(DISTINCT b.UserID) as UniqueUsers
                                  FROM Booking b
                                  JOIN VenueTimeSlot vts ON b.SlotID = vts.SlotID";
                    
                    if (!empty($date_from)) {
                        $where_clauses[] = "b.BookingDate >= ?";
                        $params[] = $date_from;
                        $param_types .= 's';
                    }
                    
                    if (!empty($date_to)) {
                        $where_clauses[] = "b.BookingDate <= ?";
                        $params[] = $date_to;
                        $param_types .= 's';
                    }
                    
                    $group_by = "GROUP BY DATE(b.BookingDate)";
                    $order_by = "ORDER BY DATE(b.BookingDate) DESC";
                    break;
                    
                case 'venue_performance':
                    $base_query = "SELECT v.VenueID, v.Name, v.Location,
                                  COUNT(b.BookingID) as TotalBookings,
                                  COUNT(DISTINCT b.UserID) as UniqueUsers,
                                  SUM(vts.Price) as TotalRevenue,
                                  ROUND(AVG(vts.Price), 2) as AvgBookingValue
                                  FROM Venue v
                                  LEFT JOIN Booking b ON v.VenueID = b.VenueID
                                  LEFT JOIN VenueTimeSlot vts ON b.SlotID = vts.SlotID";
                    
                    if (!empty($date_from)) {
                        $where_clauses[] = "b.BookingDate >= ? OR b.BookingDate IS NULL";
                        $params[] = $date_from;
                        $param_types .= 's';
                    }
                    
                    if (!empty($date_to)) {
                        $where_clauses[] = "b.BookingDate <= ? OR b.BookingDate IS NULL";
                        $params[] = $date_to;
                        $param_types .= 's';
                    }
                    
                    if (!empty($sport_filter)) {
                        $where_clauses[] = "EXISTS (SELECT 1 FROM VenueSports vs WHERE vs.VenueID = v.VenueID AND vs.SportType = ?)";
                        $params[] = $sport_filter;
                        $param_types .= 's';
                    }
                    
                    if (!empty($location_filter)) {
                        $where_clauses[] = "v.Location LIKE ?";
                        $params[] = "%$location_filter%";
                        $param_types .= 's';
                    }
                    
                    $group_by = "GROUP BY v.VenueID";
                    $order_by = "ORDER BY TotalRevenue DESC";
                    break;
                    
                case 'user_activity':
                    $base_query = "SELECT u.UserID, u.Name, u.Email, r.RoleName,
                                  COUNT(b.BookingID) as TotalBookings,
                                  SUM(p.Amount) as TotalSpent,
                                  COUNT(DISTINCT b.VenueID) as UniqueVenues,
                                  MAX(b.BookingDate) as LastBooking
                                  FROM User u
                                  JOIN Role r ON u.RoleID = r.RoleID
                                  LEFT JOIN Booking b ON u.UserID = b.UserID
                                  LEFT JOIN Payment p ON b.BookingID = p.BookingID";
                    
                    if (!empty($search_query)) {
                        $where_clauses[] = "(u.Name LIKE ? OR u.Email LIKE ?)";
                        $params[] = "%$search_query%";
                        $params[] = "%$search_query%";
                        $param_types .= 'ss';
                    }
                    
                    if (!empty($date_from)) {
                        $where_clauses[] = "(b.BookingDate >= ? OR b.BookingDate IS NULL)";
                        $params[] = $date_from;
                        $param_types .= 's';
                    }
                    
                    if (!empty($date_to)) {
                        $where_clauses[] = "(b.BookingDate <= ? OR b.BookingDate IS NULL)";
                        $params[] = $date_to;
                        $param_types .= 's';
                    }
                    
                    $group_by = "GROUP BY u.UserID";
                    $order_by = "ORDER BY TotalBookings DESC";
                    break;
                
                case 'sport_popularity':
                    $base_query = "SELECT vs.SportType,
                                  COUNT(DISTINCT v.VenueID) as VenueCount,
                                  COUNT(b.BookingID) as BookingCount,
                                  SUM(vts.Price) as TotalRevenue,
                                  COUNT(DISTINCT b.UserID) as UniqueUsers
                                  FROM VenueSports vs
                                  LEFT JOIN Venue v ON vs.VenueID = v.VenueID
                                  LEFT JOIN Booking b ON v.VenueID = b.VenueID
                                  LEFT JOIN VenueTimeSlot vts ON b.SlotID = vts.SlotID";
                    
                    if (!empty($date_from)) {
                        $where_clauses[] = "(b.BookingDate >= ? OR b.BookingDate IS NULL)";
                        $params[] = $date_from;
                        $param_types .= 's';
                    }
                    
                    if (!empty($date_to)) {
                        $where_clauses[] = "(b.BookingDate <= ? OR b.BookingDate IS NULL)";
                        $params[] = $date_to;
                        $param_types .= 's';
                    }
                    
                    $group_by = "GROUP BY vs.SportType";
                    $order_by = "ORDER BY BookingCount DESC";
                    break;
            }
        } else {
            // Default to daily bookings if no report type is specified
            $_GET['report_type'] = 'daily_bookings';
            $base_query = "SELECT DATE(b.BookingDate) as Date, 
                          COUNT(*) as TotalBookings, 
                          SUM(vts.Price) as TotalRevenue,
                          COUNT(DISTINCT b.UserID) as UniqueUsers
                          FROM Booking b
                          JOIN VenueTimeSlot vts ON b.SlotID = vts.SlotID";
            
            if (!empty($date_from)) {
                $where_clauses[] = "b.BookingDate >= ?";
                $params[] = $date_from;
                $param_types .= 's';
            }
            
            if (!empty($date_to)) {
                $where_clauses[] = "b.BookingDate <= ?";
                $params[] = $date_to;
                $param_types .= 's';
            }
            
            $group_by = "GROUP BY DATE(b.BookingDate)";
            $order_by = "ORDER BY DATE(b.BookingDate) DESC";
        }
        break;
}

// Execute the search query
if (isset($base_query)) {
    // Build the WHERE clause
    $where = '';
    if (!empty($where_clauses)) {
        $where = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    // For reports
    if ($search_type === 'reports') {
        // Count query
        $count_query = "SELECT COUNT(*) FROM ($base_query $where $group_by) as report_data";
        $stmt = $conn->prepare($count_query);
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $total_results = $stmt->get_result()->fetch_row()[0];
        $stmt->close();
        
        // Data query
        $data_query = "$base_query $where $group_by $order_by LIMIT $per_page OFFSET $offset";
        $stmt = $conn->prepare($data_query);
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        // Count query for non-report types
        $count_query = "SELECT COUNT(*) FROM ($base_query $where) as count_query";
        $stmt = $conn->prepare($count_query);
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $total_results = $stmt->get_result()->fetch_row()[0];
        $stmt->close();
        
        // Data query
        $data_query = "$base_query $where ORDER BY $order_by LIMIT $per_page OFFSET $offset";
        $stmt = $conn->prepare($data_query);
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Calculate pagination
$total_pages = ceil($total_results / $per_page);
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Хайлт ба Тайлан - Админ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <nav class="bg-gray-800 text-white px-6 py-4 shadow-lg flex justify-between items-center">
        <div class="flex space-x-6">
            <!-- Dashboard Link -->
            <a href="admin_dashboard.php" class="relative <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'font-semibold text-yellow-300' : 'hover:text-yellow-400'; ?> transition-colors duration-200">Дашбоард</a>
            
            <!-- Search & Reports Link -->
            <a href="admin_search_reports.php" class="relative font-semibold text-yellow-300 transition-colors duration-200">
                Хайлт & Тайлан
            </a>
            
            <!-- Promotion Requests Link with Pending Count -->
            <a href="admin_promotion_requests.php" class="relative flex items-center <?php echo basename($_SERVER['PHP_SELF']) == 'admin_promotion_requests.php' ? 'font-semibold text-yellow-300' : 'hover:text-yellow-400'; ?> transition-colors duration-200">
                Хамтрах Хүсэлтүүд
                <?php if ($pendingCount > 0): ?>
                    <span class="absolute -top-2 -right-3 bg-red-600 text-white text-xs font-semibold rounded-full px-2 py-1"><?php echo $pendingCount; ?></span>
                <?php endif; ?>
            </a>
            
            <!-- Manage Users Link -->
            <a href="manage_users.php" class="relative <?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'font-semibold text-yellow-300' : 'hover:text-yellow-400'; ?> transition-colors duration-200">Хэрэглэгчийг удирдах</a>
        </div>
        
        <!-- Logout Button -->
        <a href="?logout=true" class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-full transition-all duration-300 ease-in-out hover:scale-105">Гарах</a>
        </nav>

    <div class="container mx-auto my-8 px-4">
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-2xl font-bold mb-6">Хайлт & Тайлан</h2>
            
            <!-- Search Type Tabs -->
            <div class="flex flex-wrap border-b mb-6">
                <a href="?search_type=users<?= !empty($search_query) ? '&query=' . urlencode($search_query) : '' ?>" 
                   class="px-4 py-2 font-medium text-sm rounded-t-lg mr-2 <?= $search_type === 'users' ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">
                    <i class="fas fa-users mr-2"></i>Хэрэглэгчид
                </a>
                <a href="?search_type=venues<?= !empty($search_query) ? '&query=' . urlencode($search_query) : '' ?>" 
                   class="px-4 py-2 font-medium text-sm rounded-t-lg mr-2 <?= $search_type === 'venues' ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">
                    <i class="fas fa-map-marker-alt mr-2"></i>Танхимууд
                </a>
                <a href="?search_type=bookings<?= !empty($search_query) ? '&query=' . urlencode($search_query) : '' ?>" 
                   class="px-4 py-2 font-medium text-sm rounded-t-lg mr-2 <?= $search_type === 'bookings' ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">
                    <i class="fas fa-calendar-check mr-2"></i>Захиалгууд
                </a>
                <a href="?search_type=payments<?= !empty($search_query) ? '&query=' . urlencode($search_query) : '' ?>" 
                   class="px-4 py-2 font-medium text-sm rounded-t-lg mr-2 <?= $search_type === 'payments' ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">
                    <i class="fas fa-credit-card mr-2"></i>Төлбөрүүд
                </a>
                <a href="?search_type=reports<?= !empty($search_query) ? '&query=' . urlencode($search_query) : '' ?>" 
                   class="px-4 py-2 font-medium text-sm rounded-t-lg <?= $search_type === 'reports' ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">
                    <i class="fas fa-chart-line mr-2"></i>Тайлангууд
                </a>
            </div>
            
            <!-- Report Type Selection (only for reports) -->
            <?php if ($search_type === 'reports'): ?>
            <div class="mb-6">
                <h3 class="font-semibold mb-2">Тайлангийн төрөл:</h3>
                <div class="flex flex-wrap gap-2">
                    <a href="?search_type=reports&report_type=daily_bookings<?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>" 
                       class="px-3 py-1 text-sm rounded <?= $_GET['report_type'] === 'daily_bookings' ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">
                        Өдөр тутмын захиалгууд
                    </a>
                    <a href="?search_type=reports&report_type=venue_performance<?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>" 
                       class="px-3 py-1 text-sm rounded <?= $_GET['report_type'] === 'venue_performance' ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">
                        Танхим бүрийн гүйцэтгэл
                    </a>
                    <a href="?search_type=reports&report_type=user_activity<?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>" 
                       class="px-3 py-1 text-sm rounded <?= $_GET['report_type'] === 'user_activity' ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">
                        Хэрэглэгчийн идэвх
                    </a>
                    <a href="?search_type=reports&report_type=sport_popularity<?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>" 
                       class="px-3 py-1 text-sm rounded <?= $_GET['report_type'] === 'sport_popularity' ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">
                        Спортын эрэлт
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
           <!-- Search & Filter Form -->
<form action="" method="GET" class="mb-6">
    <input type="hidden" name="search_type" value="<?= htmlspecialchars($search_type) ?>">
    <?php if ($search_type === 'reports'): ?>
    <input type="hidden" name="report_type" value="<?= htmlspecialchars($_GET['report_type'] ?? 'daily_bookings') ?>">
    <?php endif; ?>
    
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Search Query - not shown for some report types -->
        <?php if ($search_type !== 'reports' || in_array($_GET['report_type'] ?? '', ['user_activity'])): ?>
        <div class="col-span-1 md:col-span-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Хайх:</label>
            <input type="text" name="query" value="<?= htmlspecialchars($search_query) ?>" 
                   class="w-full px-4 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500"
                   placeholder="Хайлтын түлхүүр үг">
        </div>
        <?php endif; ?>

        <!-- Date Filters -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Огноо (эхлэх):</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" 
                   class="w-full px-4 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Огноо (дуусах):</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" 
                   class="w-full px-4 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
        </div>
        
        <!-- Status Filter (not for reports) -->
        <?php if ($search_type !== 'reports' && !empty($available_statuses)): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Төлөв:</label>
            <select name="status" class="w-full px-4 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                <option value="">Бүгд</option>
                <?php foreach ($available_statuses as $status): ?>
                <option value="<?= htmlspecialchars($status) ?>" <?= $status_filter === $status ? 'selected' : '' ?>>
                    <?= htmlspecialchars($status) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        
        <!-- Sport Filter -->
        <?php if (in_array($search_type, ['venues', 'bookings']) || ($search_type === 'reports' && in_array($_GET['report_type'] ?? '', ['venue_performance', 'sport_popularity']))): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Спортын төрөл:</label>
            <select name="sport" class="w-full px-4 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                <option value="">Бүгд</option>
                <?php foreach ($available_sports as $sport): ?>
                <option value="<?= htmlspecialchars($sport) ?>" <?= $sport_filter === $sport ? 'selected' : '' ?>>
                    <?= htmlspecialchars($sport) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        
        <!-- Location Filter -->
        <?php if (in_array($search_type, ['venues', 'bookings']) || ($search_type === 'reports' && in_array($_GET['report_type'] ?? '', ['venue_performance']))): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Байршил:</label>
            <select name="location" class="w-full px-4 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                <option value="">Бүгд</option>
                <?php foreach ($available_locations as $location): ?>
                <option value="<?= htmlspecialchars($location) ?>" <?= $location_filter === $location ? 'selected' : '' ?>>
                    <?= htmlspecialchars($location) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        
        <!-- Sort options -->
        <?php if ($search_type !== 'reports'): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Эрэмбэлэх:</label>
            <select name="sort_by" class="w-full px-4 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                <?php
                $sort_labels = [];
                switch ($search_type) {
                    case 'users':
                        $sort_labels = [
                            'name_asc' => 'Нэрээр (А-Я)',
                            'name_desc' => 'Нэрээр (Я-А)',
                            'date_asc' => 'Огноогоор (Эхний)',
                            'date_desc' => 'Огноогоор (Сүүлийн)',
                            'role_asc' => 'Эрхээр (А-Я)',
                            'role_desc' => 'Эрхээр (Я-А)'
                        ];
                        break;
                    case 'venues':
                        $sort_labels = [
                            'name_asc' => 'Нэрээр (А-Я)',
                            'name_desc' => 'Нэрээр (Я-А)',
                            'price_asc' => 'Үнээр (Бага→Их)',
                            'price_desc' => 'Үнээр (Их→Бага)',
                            'location_asc' => 'Байршлаар (А-Я)',
                            'location_desc' => 'Байршлаар (Я-А)'
                        ];
                        break;
                    case 'bookings':
                        $sort_labels = [
                            'date_asc' => 'Огноогоор (Эхний)',
                            'date_desc' => 'Огноогоор (Сүүлийн)',
                            'venue_asc' => 'Танхимаар (А-Я)',
                            'venue_desc' => 'Танхимаар (Я-А)',
                            'user_asc' => 'Хэрэглэгчээр (А-Я)',
                            'user_desc' => 'Хэрэглэгчээр (Я-А)',
                            'status_asc' => 'Төлөвөөр (А-Я)',
                            'status_desc' => 'Төлөвөөр (Я-А)'
                        ];
                        break;
                    case 'payments':
                        $sort_labels = [
                            'date_asc' => 'Огноогоор (Эхний)',
                            'date_desc' => 'Огноогоор (Сүүлийн)',
                            'amount_asc' => 'Дүнгээр (Бага→Их)',
                            'amount_desc' => 'Дүнгээр (Их→Бага)',
                            'status_asc' => 'Төлөвөөр (А-Я)',
                            'status_desc' => 'Төлөвөөр (Я-А)'
                        ];
                        break;
                }
                
                foreach ($sort_labels as $value => $label):
                ?>
                <option value="<?= $value ?>" <?= $sort_by === $value ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="mt-4 flex space-x-2">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-search mr-2"></i>Хайх
        </button>
        <a href="?search_type=<?= htmlspecialchars($search_type) ?><?= $search_type === 'reports' ? '&report_type=' . htmlspecialchars($_GET['report_type'] ?? 'daily_bookings') : '' ?>" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition-colors">
            <i class="fas fa-sync-alt mr-2"></i>Дахин тохируулах
        </a>
        
        <?php if (!empty($results)): ?>
        <button type="button" onclick="exportToCSV()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors ml-auto">
            <i class="fas fa-file-csv mr-2"></i>CSV татах
        </button>
        <button type="button" onclick="window.print()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
            <i class="fas fa-print mr-2"></i>Хэвлэх
        </button>
        <?php endif; ?>
    </div>
</form>

<!-- Results Section -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <?php if ($search_type === 'reports' && !empty($results)): ?>
        <!-- Charts for Reports -->
        <div class="p-4">
            <canvas id="reportChart" class="w-full h-80"></canvas>
        </div>
    <?php endif; ?>

    <?php if (!empty($results)): ?>
        <!-- Search/Report Results -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <?php
                        // Generate table headers based on search type
                        $headers = [];
                        switch ($search_type) {
                            case 'users':
                                $headers = ['ID', 'Нэр', 'И-мэйл', 'Утас', 'Эрх', 'Төлөв', 'Бүртгүүлсэн огноо', 'Үйлдэл'];
                                break;
                            case 'venues':
                                $headers = ['ID', 'Нэр', 'Байршил', 'Үнэ/цаг', 'Спортын төрөл', 'Менежер', 'Төлөв', 'Үйлдэл'];
                                break;
                            case 'bookings':
                                $headers = ['ID', 'Танхим', 'Хэрэглэгч', 'Огноо', 'Цаг', 'Төлөв', 'Төлбөрийн төлөв', 'Үйлдэл'];
                                break;
                            case 'payments':
                                $headers = ['ID', 'Захиалга ID', 'Дүн', 'Огноо', 'Төлөв', 'Хэрэглэгч', 'Танхим', 'Үйлдэл'];
                                break;
                            case 'reports':
                                // Headers based on report type
                                switch ($_GET['report_type'] ?? 'daily_bookings') {
                                    case 'daily_bookings':
                                        $headers = ['Огноо', 'Захиалгын тоо', 'Орлого', 'Идэвхтэй хэрэглэгчид'];
                                        break;
                                    case 'venue_performance':
                                        $headers = ['ID', 'Танхим', 'Байршил', 'Захиалгын тоо', 'Хэрэглэгчдийн тоо', 'Нийт орлого', 'Дундаж захиалга'];
                                        break;
                                    case 'user_activity':
                                        $headers = ['ID', 'Хэрэглэгч', 'И-мэйл', 'Эрх', 'Захиалгын тоо', 'Нийт зарцуулалт', 'Ашигласан танхим', 'Сүүлийн захиалга'];
                                        break;
                                    case 'sport_popularity':
                                        $headers = ['Спортын төрөл', 'Танхимын тоо', 'Захиалгын тоо', 'Нийт орлого', 'Хэрэглэгчдийн тоо'];
                                        break;
                                }
                                break;
                        }
                        
                        foreach ($headers as $header):
                        ?>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <?= htmlspecialchars($header) ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($results as $row): ?>
                        <?php switch ($search_type): 
            case 'users': ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($row['UserID']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($row['Name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($row['Email']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($row['Phone'] ?? 'N/A') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($row['RoleName']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?= $row['Status'] === 'Active' ? 'bg-green-100 text-green-800' : 
                                           ($row['Status'] === 'Inactive' ? 'bg-gray-100 text-gray-800' : 'bg-red-100 text-red-800') ?>">
                                        <?= htmlspecialchars($row['Status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('Y-m-d', strtotime($row['CreatedAt'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="edit_user.php?id=<?= $row['UserID'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="view_user.php?id=<?= $row['UserID'] ?>" class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                                <?php break;
            
            case 'venues': ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($row['VenueID']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($row['Name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($row['Location']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= number_format($row['HourlyPrice']) ?> ₮
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($row['Sports'] ?? 'N/A') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($row['ManagerName'] ?? 'N/A') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?= $row['Status'] === 'Active' ? 'bg-green-100 text-green-800' : 
                                           ($row['Status'] === 'Maintenance' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                        <?= htmlspecialchars($row['Status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="edit_venue.php?id=<?= $row['VenueID'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="view_venue.php?id=<?= $row['VenueID'] ?>" class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            <?php break; ?>
                            
                            <?php case 'bookings': ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($row['BookingID']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($row['VenueName']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($row['UserName']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('Y-m-d', strtotime($row['BookingDate'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($row['DayOfWeek']) ?>, 
                                    <?= date('H:i', strtotime($row['StartTime'])) ?>-<?= date('H:i', strtotime($row['EndTime'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?= $row['Status'] === 'Confirmed' ? 'bg-green-100 text-green-800' : 
                                           ($row['Status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800' : 
                                           ($row['Status'] === 'Completed' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800')) ?>">
                                        <?= htmlspecialchars($row['Status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?= $row['PaymentStatus'] === 'Completed' ? 'bg-green-100 text-green-800' : 
                                           ($row['PaymentStatus'] === 'Pending' ? 'bg-yellow-100 text-yellow-800' : 
                                           ($row['PaymentStatus'] === 'Refunded' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800')) ?>">
                                        <?= htmlspecialchars($row['PaymentStatus'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="edit_booking.php?id=<?= $row['BookingID'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="view_booking.php?id=<?= $row['BookingID'] ?>" class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            <?php break; ?>
                            
                            <?php case 'payments': ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($row['PaymentID']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($row['BookingID']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= number_format($row['Amount']) ?> ₮
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('Y-m-d', strtotime($row['PaymentDate'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?= $row['Status'] === 'Completed' ? 'bg-green-100 text-green-800' : 
                                           ($row['Status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800' : 
                                           ($row['Status'] === 'Refunded' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800')) ?>">
                                        <?= htmlspecialchars($row['Status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($row['UserName']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($row['VenueName']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="edit_payment.php?id=<?= $row['PaymentID'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="view_payment.php?id=<?= $row['PaymentID'] ?>" class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                                <?php break;
            
            case 'reports': ?>
                <?php switch ($_GET['report_type'] ?? 'daily_bookings'):
                    case 'daily_bookings': ?>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= date('Y-m-d', strtotime($row['Date'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= htmlspecialchars($row['TotalBookings']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= number_format($row['TotalRevenue']) ?> ₮
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($row['UniqueUsers']) ?>
                                        </td>
                                    <?php break; ?>
                                    
                                    <?php case 'venue_performance': ?>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($row['VenueID']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= htmlspecialchars($row['Name']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($row['Location']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($row['TotalBookings']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($row['UniqueUsers']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= number_format($row['TotalRevenue']) ?> ₮
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= number_format($row['AvgBookingValue']) ?> ₮
                                        </td>
                                    <?php break; ?>
                                    
                                    <?php case 'user_activity': ?>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($row['UserID']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= htmlspecialchars($row['Name']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($row['Email']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($row['RoleName']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($row['TotalBookings']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= number_format($row['TotalSpent']) ?> ₮
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($row['UniqueVenues']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= $row['LastBooking'] ? date('Y-m-d', strtotime($row['LastBooking'])) : 'N/A' ?>
                                        </td>
                                    <?php break; ?>
                                    
                                    <?php case 'sport_popularity': ?>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($row['SportType']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($row['VenueCount']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($row['BookingCount']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= number_format($row['TotalRevenue']) ?> ₮
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($row['UniqueUsers']) ?>
                                        </td>
                                    <?php break; ?>
                                <?php endswitch; ?>
                            <?php break; ?>
                        <?php endswitch; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="px-6 py-4 bg-gray-50 border-t flex items-center justify-between">
            <div class="text-sm text-gray-700">
                <span class="font-medium"><?= $total_results ?></span> үр дүн, 
                <span class="font-medium"><?= $page ?></span> / <?= $total_pages ?> хуудас
            </div>
            <div class="flex space-x-2">
                <?php if ($page > 1): ?>
                <a href="<?= '?' . http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                   class="px-3 py-1 rounded-md bg-white border hover:bg-gray-50">
                    Өмнөх
                </a>
                <?php endif; ?>
                
                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                if ($start > 1): ?>
                    <a href="<?= '?' . http_build_query(array_merge($_GET, ['page' => 1])) ?>" 
                       class="px-3 py-1 rounded-md <?= $page == 1 ? 'bg-blue-600 text-white' : 'bg-white border hover:bg-gray-50' ?>">
                        1
                    </a>
                    <?php if ($start > 2): ?>
                        <span class="px-2 py-1">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i != 1 && $i != $total_pages): ?>
                    <a href="<?= '?' . http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                       class="px-3 py-1 rounded-md <?= $page == $i ? 'bg-blue-600 text-white' : 'bg-white border hover:bg-gray-50' ?>">
                        <?= $i ?>
                    </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($end < $total_pages): ?>
                    <?php if ($end < $total_pages - 1): ?>
                        <span class="px-2 py-1">...</span>
                    <?php endif; ?>
                    <a href="<?= '?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" 
                       class="px-3 py-1 rounded-md <?= $page == $total_pages ? 'bg-blue-600 text-white' : 'bg-white border hover:bg-gray-50' ?>">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="<?= '?' . http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                   class="px-3 py-1 rounded-md bg-white border hover:bg-gray-50">
                    Дараах
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- No Results -->
        <div class="p-6 text-center">
            <?php if (!empty($search_query) || !empty($date_from) || !empty($date_to) || !empty($status_filter) || !empty($sport_filter) || !empty($location_filter)): ?>
                <i class="fas fa-search text-gray-400 text-4xl mb-4"></i>
                <p class="text-gray-500 text-lg">Хайлтанд тохирох үр дүн олдсонгүй</p>
                <p class="text-gray-400 mt-2">Өөр хайлтын утга ашиглана уу</p>
            <?php else: ?>
                <i class="fas fa-filter text-gray-400 text-4xl mb-4"></i>
                <p class="text-gray-500 text-lg">Хайлтын утга оруулж үр дүнг харна уу</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- JavaScript for Charts and Export -->
<script>
    // CSV Export Function
    function exportToCSV() {
        let csv = [];
        const rows = document.querySelectorAll('table tr');
        
        for (let i = 0; i < rows.length; i++) {
            let row = [], cols = rows[i].querySelectorAll('td, th');
            
            for (let j = 0; j < cols.length; j++) {
                // Replace HTML entities and commas
                let text = cols[j].innerText.replace(/"/g, '""');
                row.push('"' + text + '"');
            }
            
            csv.push(row.join(','));
        }
        
        // Creating the CSV file
        const csvString = csv.join('\n');
        const fileName = '<?= $search_type ?>_export_<?= date('Y-m-d') ?>.csv';
        
        // Creating download link
        const link = document.createElement('a');
        link.style.display = 'none';
        link.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvString));
        link.setAttribute('download', fileName);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    <?php if ($search_type === 'reports' && !empty($results)): ?>
    // Initialize Chart for Reports
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('reportChart').getContext('2d');
        
        <?php
        // Setup chart data based on report type
        switch ($_GET['report_type'] ?? 'daily_bookings'):
            case 'daily_bookings':
                $labels = array_map(function($row) { return date('m/d', strtotime($row['Date'])); }, $results);
                $bookings = array_map(function($row) { return $row['TotalBookings']; }, $results);
                $revenue = array_map(function($row) { return $row['TotalRevenue']; }, $results);
        ?>
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_reverse($labels)) ?>,
                datasets: [
                    {
                        label: 'Захиалгын тоо',
                        data: <?= json_encode(array_reverse($bookings)) ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Орлого (₮)',
                        data: <?= json_encode(array_reverse($revenue)) ?>,
                        type: 'line',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 2,
                        pointRadius: 4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        type: 'linear',
                        position: 'left',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Захиалгын тоо'
                        }
                    },
                    y1: {
                        type: 'linear',
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Орлого (₮)'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
        <?php 
            break;
            case 'venue_performance':
                $venues = array_map(function($row) { return $row['Name']; }, $results);
                $bookings = array_map(function($row) { return $row['TotalBookings']; }, $results);
                $revenue = array_map(function($row) { return $row['TotalRevenue']; }, $results);
        ?>
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($venues) ?>,
                datasets: [
                    {
                        label: 'Захиалгын тоо',
                        data: <?= json_encode($bookings) ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Орлого (₮)',
                        data: <?= json_encode($revenue) ?>,
                        backgroundColor: 'rgba(255, 99, 132, 0.6)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true
                    }
                }
            }
        });
        <?php 
            break;
            case 'sport_popularity':
                $sports = array_map(function($row) { return $row['SportType']; }, $results);
                $bookings = array_map(function($row) { return $row['BookingCount']; }, $results);
                $venues = array_map(function($row) { return $row['VenueCount']; }, $results);
                $users = array_map(function($row) { return $row['UniqueUsers']; }, $results);
        ?>
        new Chart(ctx, {
            type: 'radar',
            data: {
                labels: <?= json_encode($sports) ?>,
                datasets: [
                    {
                        label: 'Захиалгын тоо',
                        data: <?= json_encode($bookings) ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.3)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(54, 162, 235, 1)'
                    },
                    {
                        label: 'Танхимын тоо',
                        data: <?= json_encode($venues) ?>,
                        backgroundColor: 'rgba(255, 99, 132, 0.3)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(255, 99, 132, 1)'
                    },
                    {
                        label: 'Хэрэглэгчийн тоо',
                        data: <?= json_encode($users) ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.3)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(75, 192, 192, 1)'
                    }
                ]
            },
            options: {
                responsive: true,
                elements: {
                    line: {
                        tension: 0.1
                    }
                }
            }
        });
        <?php 
            break;
            case 'user_activity':
                $topUsers = array_slice($results, 0, 10);
                $users = array_map(function($row) { return $row['Name']; }, $topUsers);
                $bookings = array_map(function($row) { return $row['TotalBookings']; }, $topUsers);
                $spent = array_map(function($row) { return $row['TotalSpent']; }, $topUsers);
        ?>
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: <?= json_encode($users) ?>,
                datasets: [{
                    data: <?= json_encode($bookings) ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 159, 64, 0.7)',
                        'rgba(199, 199, 199, 0.7)',
                        'rgba(83, 102, 255, 0.7)',
                        'rgba(40, 159, 64, 0.7)',
                        'rgba(210, 199, 199, 0.7)',
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(199, 199, 199, 1)',
                        'rgba(83, 102, 255, 1)',
                        'rgba(40, 159, 64, 1)',
                        'rgba(210, 199, 199, 1)',
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    title: {
                        display: true,
                        text: 'Топ 10 хэрэглэгч (захиалгын тоогоор)'
                    }
                }
            }
        });
        <?php break; endswitch; ?>
    });
    <?php endif; ?>
</script>
</div>
</body>
</html>