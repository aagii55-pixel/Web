<?php
session_start();
require 'config/db.php';

// Enhanced user role and authentication handling
$userRoleID = 0;
$user_name = '';

if (isset($_SESSION['user_id'])) {
    $userID = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT Name, RoleID FROM User WHERE UserID = ?");
    $stmt->bind_param('i', $userID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        $userRoleID = $user['RoleID'];
        $user_name = $user['Name'];
    }
    $stmt->close();
}

// Pagination and search parameters with more robust handling
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 8;
$offset = ($page - 1) * $perPage;

// Sport and location handling with multiselect support
$all_sports = ['Хөлбөмбөг', 'Сагсанбөмбөг', 'Волейбол', 'Ширээний теннис', 'Бадминтон', 'Талбайн теннис', 'Гольф', 'Бүжиг', 'Иога', 'Билльярд'];
$search_sport = array_filter($_GET['sport_type'] ?? [], fn($s) => in_array($s, $all_sports));
$search_location = array_filter($_GET['location'] ?? []);
$search_date = $_GET['date'] ?? '';
$search_time = $_GET['time'] ?? '';

// Security: Sanitize and validate inputs
$conn->set_charset("utf8mb4");

// Dynamic query builder with PDO-like prepared statement approach
$where_conditions = [];
$params = [];

if (!empty($search_sport)) {
    $sport_placeholders = implode(',', array_fill(0, count($search_sport), '?'));
    $where_conditions[] = "EXISTS (
        SELECT 1 FROM VenueSports vs 
        WHERE vs.VenueID = v.VenueID AND vs.SportType IN ($sport_placeholders)
    )";
    $params = array_merge($params, $search_sport);
}

if (!empty($search_location)) {
    $location_conditions = [];
    foreach ($search_location as $loc) {
        $location_conditions[] = "v.Location LIKE ?";
        $params[] = "%$loc%";
    }
    $where_conditions[] = "(" . implode(" OR ", $location_conditions) . ")";
}

if (!empty($search_date) && !empty($search_time)) {
    $where_conditions[] = "EXISTS (
        SELECT 1 FROM VenueTimeSlot vts 
        WHERE vts.VenueID = v.VenueID 
        AND vts.Status = 'Available' 
        AND vts.DayOfWeek = DAYNAME(?) 
        AND ? BETWEEN vts.StartTime AND vts.EndTime
    )";
    $params[] = $search_date;
    $params[] = $search_time;
}

// Construct base query
$venue_base_query = "FROM Venue v WHERE EXISTS (
    SELECT 1 FROM VenueTimeSlot vts 
    WHERE vts.VenueID = v.VenueID 
    AND vts.Status = 'Available'
)";

if (!empty($where_conditions)) {
    $venue_base_query .= " AND " . implode(" AND ", $where_conditions);
}

// Total venues count
$count_stmt = $conn->prepare("SELECT COUNT(DISTINCT v.VenueID) as total $venue_base_query");
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_venues = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_venues / $perPage);

// Venue fetch query
$venues_query = "SELECT v.VenueID, v.Name, v.Description, v.Location,
    (SELECT MIN(Price) FROM VenueTimeSlot vts WHERE vts.VenueID = v.VenueID AND vts.Status = 'Available') AS MinPrice,
    (SELECT ImagePath FROM VenueImages vi WHERE vi.VenueID = v.VenueID LIMIT 1) AS FirstImage
    $venue_base_query
    GROUP BY v.VenueID
    LIMIT $perPage OFFSET $offset";

$stmt = $conn->prepare($venues_query);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$venues_result = $stmt->get_result();

// Fetch sport types and locations for filters
$sport_types_result = $conn->query("SELECT DISTINCT SportType FROM VenueSports ORDER BY SportType");
$locations_result = $conn->query("SELECT DISTINCT Location FROM Venue ORDER BY Location");

// Sport emojis mapping
$sport_emojis = [
    'Хөлбөмбөг' => '⚽',
    'Сагсанбөмбөг' => '🏀',
    'Волейбол' => '🏐',
    'Ширээний теннис' => '🏓',
    'Бадминтон' => '🏸',
    'Талбайн теннис' => '🎾',
    'Гольф' => '⛳',
    'Бүжиг' => '💃',
    'Иога' => '🧘',
    'Билльярд' => '🎱'
];
$isManagerViewingAsUser = isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'Manager' && $_SESSION['user_role'] === 'User';

if ($isManagerViewingAsUser):
    ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 container mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <i class="fas fa-user-secret mr-2"></i>
                <span>Та одоогоор хэрэглэгчийн горимд ажиллаж байна. Бүх хэрэглэгчийн үйлдлүүдийг хийх боломжтой.</span>
            </div>
            <a href="switch_role.php?role=manager" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-md">
                <i class="fas fa-exchange-alt mr-1"></i> Менежер горимд буцах
            </a>
        </div>
    </div>
    <?php endif; ?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <title>Танхимын жагсаалт</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 font-sans max-w-screen-xl mx-auto p-6">
    <?php include 'header.php'; ?>

    <div class="container mx-auto">
        <!-- User Dashboard Navigation -->
        <div class="flex items-center gap-4 mb-6">
    <?php if ($userRoleID): ?>
        <?php 
        // Check if manager is in user mode
        $isManagerViewingAsUser = isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'Manager' && $_SESSION['user_role'] === 'User';
        
        // If manager is in user mode, always show user dashboard link
        if ($isManagerViewingAsUser) {
            ?>
            <a href="user_dashboard.php" 
               class="text-blue-600 hover:underline flex items-center">
                <i class="fas fa-tachometer-alt mr-2"></i> 
                Хэрэглэгчийн самбар
            </a>
            <?php
        } else {
            // Normal dashboard links based on actual role
            $dashboard_links = [
                4 => ['url' => 'user_dashboard.php', 'name' => 'Хэрэглэгч самбар'],
                3 => ['url' => 'manager_dashboard.php', 'name' => 'Менежер самбар'],
                5 => ['url' => 'venue_staff_dashboard.php', 'name' => 'Заал ажилтны самбар'],
                6 => ['url' => 'accountant_dashboard.php', 'name' => 'Нягтлан самбар']
            ];
            
            if (isset($dashboard_links[$userRoleID])): 
            ?>
                <a href="<?= $dashboard_links[$userRoleID]['url'] ?>" 
                   class="text-blue-600 hover:underline flex items-center">
                    <i class="fas fa-tachometer-alt mr-2"></i> 
                    <?= $dashboard_links[$userRoleID]['name'] ?>
                </a>
            <?php endif;
        } ?>
    <?php endif; ?>
</div>

        <h1 class="text-3xl font-bold text-center mb-8">Танхимын жагсаалт</h1>

        <!-- Filter Section with Enhanced Design -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-10">
            <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Sport Type Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Спортын төрөл</label>
                    <div class="border rounded max-h-48 overflow-y-auto p-3 bg-gray-50">
                        <?php while ($sport = $sport_types_result->fetch_assoc()): ?>
                            <label class="flex items-center mb-2">
                                <input type="checkbox" 
                                       name="sport_type[]" 
                                       value="<?= htmlspecialchars($sport['SportType']) ?>" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500"
                                       <?= in_array($sport['SportType'], $search_sport) ? 'checked' : '' ?>>
                                <span class="text-sm">
                                    <?= $sport_emojis[$sport['SportType']] ?? '🏆' ?> 
                                    <?= htmlspecialchars($sport['SportType']) ?>
                                </span>
                            </label>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Location Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Байршил</label>
                    <div class="border rounded max-h-48 overflow-y-auto p-3 bg-gray-50">
                        <?php while ($loc = $locations_result->fetch_assoc()): ?>
                            <label class="flex items-center mb-2">
                                <input type="checkbox" 
                                       name="location[]" 
                                       value="<?= htmlspecialchars($loc['Location']) ?>" 
                                       class="mr-2 text-blue-600 focus:ring-blue-500"
                                       <?= in_array($loc['Location'], $search_location) ? 'checked' : '' ?>>
                                <span class="text-sm">
                                    <i class="fas fa-map-marker-alt mr-1 text-blue-400"></i>
                                    <?= htmlspecialchars($loc['Location']) ?>
                                </span>
                            </label>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Date Filter -->
                <div>
                    <label for="date" class="block text-sm font-medium text-gray-700 mb-2">Огноо</label>
                    <input type="date" 
                           name="date" 
                           id="date" 
                           value="<?= htmlspecialchars($search_date) ?>" 
                           class="w-full border rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <!-- Time Filter -->
                <div>
                    <label for="time" class="block text-sm font-medium text-gray-700 mb-2">Цаг</label>
                    <input type="time" 
                           name="time" 
                           id="time" 
                           value="<?= htmlspecialchars($search_time) ?>" 
                           class="w-full border rounded px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <!-- Filter Buttons -->
                <div class="flex items-end space-x-4 col-span-full">
                    <button type="submit" 
                            class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition duration-300 flex items-center">
                        <i class="fas fa-search mr-2"></i> Хайх
                    </button>
                    <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" 
                       class="text-gray-600 hover:text-blue-600 flex items-center">
                        <i class="fas fa-undo mr-2"></i> Цэвэрлэх
                    </a>
                </div>
            </form>
        </div>

        <!-- Venues Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php if ($venues_result && $venues_result->num_rows > 0): ?>
                <?php while ($venue = $venues_result->fetch_assoc()): ?>
                    <div class="bg-white border border-gray-200 rounded-lg shadow-md hover:shadow-xl transition duration-300 overflow-hidden">
                        <!-- Venue Image -->
                        <?php if ($venue['FirstImage']): ?>
                            <div class="h-48 overflow-hidden">
                                <img src="<?= htmlspecialchars($venue['FirstImage']) ?>" 
                                     alt="<?= htmlspecialchars($venue['Name']) ?>" 
                                     class="w-full h-full object-cover">
                            </div>
                        <?php endif; ?>

                        <!-- Venue Details -->
                        <div class="p-4">
                            <h2 class="text-xl font-bold mb-2 truncate"><?= htmlspecialchars($venue['Name']) ?></h2>
                            
                            <!-- Sport Types -->
                            <div class="mb-2">
                                <?php
                                $sport_q = "SELECT SportType FROM VenueSports WHERE VenueID = " . $venue['VenueID'];
                                $sport_r = $conn->query($sport_q);
                                while ($row = $sport_r->fetch_assoc()) {
                                    $emoji = $sport_emojis[$row['SportType']] ?? '❓';
                                    echo "<span class='inline-block mr-1 text-sm' title='" . htmlspecialchars($row['SportType']) . "'>$emoji</span>";
                                }
                                ?>
                            </div>

                            <p class="text-sm text-gray-600 mb-2">
                                <i class="fas fa-map-marker-alt mr-1 text-blue-400"></i>
                                <?= htmlspecialchars($venue['Location']) ?>
                            </p>

                            <p class="text-sm mb-4 text-gray-500 line-clamp-2">
                                <?= htmlspecialchars($venue['Description'] ?? 'Дэлгэрэнгүй мэдээлэл байхгүй') ?>
                            </p>

                            <?php if ($venue['MinPrice']): ?>
                                <div class="flex justify-between items-center">
                                    <span class="text-lg font-bold text-blue-600">
                                        <?= number_format($venue['MinPrice']) ?> ₮/цаг
                                    </span>
                                    <a href="book_venue.php?venue_id=<?= $venue['VenueID'] ?>" 
                                       class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition duration-300 text-sm">
                                        Захиалах
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full text-center py-10 bg-white rounded-lg shadow-md">
                    <i class="fas fa-search text-4xl text-gray-400 mb-4 block"></i>
                    <p class="text-xl text-gray-600">Хайлтад тохирох танхим олдсонгүй</p>
                    <p class="text-sm text-gray-500 mt-2">Та өөр хайлтын түлхүүр үг ашиглана уу</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="flex justify-center mt-10">
                <nav class="flex space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                           class="px-4 py-2 text-gray-700 bg-white rounded-md hover:bg-blue-600 hover:text-white">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    if ($start > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" 
                           class="px-4 py-2 text-gray-700 bg-white rounded-md hover:bg-blue-600 hover:text-white">1</a>
                        <?php if ($start > 2): ?>
                            <span class="px-4 py-2 text-gray-500">...</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                           class="px-4 py-2 <?= $i == $page ? 'bg-blue-600 text-white' : 'text-gray-700 bg-white hover:bg-blue-600 hover:text-white' ?> rounded-md">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($end < $total_pages): ?>
                        <?php if ($end < $total_pages - 1): ?>
                            <span class="px-4 py-2 text-gray-500">...</span>
                        <?php endif; ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" 
                           class="px-4 py-2 text-gray-700 bg-white rounded-md hover:bg-blue-600 hover:text-white">
                            <?= $total_pages ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                           class="px-4 py-2 text-gray-700 bg-white rounded-md hover:bg-blue-600 hover:text-white">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>

   

    <script>
        // Optional: Add interactivity or search filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Example: Highlight selected filters
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    this.closest('label').classList.toggle('bg-blue-50', this.checked);
                });
            });
        });
    </script>
</body>
</html>

<?php 
// Clean up database resources
$conn->close(); 
?>