<?php
session_start();
require_once 'config/db.php';

// Define the canPerformUserActions function
function canPerformUserActions() {
    // Regular user
    if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 4) {
        return true;
    }
    
    // Manager in user mode
    if (isset($_SESSION['temp_is_manager']) && $_SESSION['temp_is_manager'] === true) {
        return true;
    }
    
    // Default user (no role check for backward compatibility)
    if (isset($_SESSION['user_id']) && !isset($_SESSION['user_role'])) {
        return true;
    }
    
    return false;
}

// Enhanced error and input handling
if ($conn->connect_error) {
    die("Системийн алдаа: Өгөгдлийн сан холбогдоогүй байна.");
}

// Set timezone and sanitize inputs
date_default_timezone_set('Asia/Ulaanbaatar');
$user_id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
$selected_venue = filter_var($_GET['venue_id'] ?? null, FILTER_VALIDATE_INT);
$userRoleID = $_SESSION['user_role'] ?? null;

// Check user access - store result for later use
$canPerformActions = canPerformUserActions();

// Sport emojis and translations
$sport_details = [
    'Хөлбөмбөг' => ['emoji' => '⚽', 'color' => 'bg-green-100'],
    'Сагсанбөмбөг' => ['emoji' => '🏀', 'color' => 'bg-blue-100'],
    'Волейбол' => ['emoji' => '🏐', 'color' => 'bg-red-100'],
    'Ширээний теннис' => ['emoji' => '🏓', 'color' => 'bg-purple-100'],
    'Бадминтон' => ['emoji' => '🏸', 'color' => 'bg-yellow-100'],
    'Талбайн теннис' => ['emoji' => '🎾', 'color' => 'bg-pink-100'],
    'Гольф' => ['emoji' => '⛳', 'color' => 'bg-green-200'],
    'Бүжиг' => ['emoji' => '💃', 'color' => 'bg-indigo-100'],
    'Иога' => ['emoji' => '🧘', 'color' => 'bg-teal-100'],
    'Билльярд' => ['emoji' => '🎱', 'color' => 'bg-gray-100']
];

// Booking process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_slots'])) {
    // Only allow booking if user can perform actions
    if (!$canPerformActions) {
        header('Location: home.php');
        exit();
    }
    
    try {
        // Validate user authentication
        if (!$user_id) {
            throw new Exception("Захиалга хийхын тулд нэвтэрнэ үү.");
        }

        $selected_slots = array_map('intval', $_POST['selected_slots']);
        $selected_venue = filter_var($_POST['venue_id'], FILTER_VALIDATE_INT);

        if (empty($selected_slots)) {
            throw new Exception("Таны слот сонгоогүй байна.");
        }

        // Start transaction
        $conn->begin_transaction();

        // Determine booking status
        $booking_status = 'Pending';
        $is_manager_of_venue = false;

        // Check if user is venue manager
        if ($userRoleID == 3) {
            $manager_check = $conn->prepare("SELECT 1 FROM venue WHERE VenueID = ? AND ManagerID = ?");
            $manager_check->bind_param("ii", $selected_venue, $user_id);
            $manager_check->execute();
            if ($manager_check->get_result()->num_rows > 0) {
                $booking_status = 'Confirmed';
                $is_manager_of_venue = true;
            }
        }

        $total_booking_cost = 0;

        foreach ($selected_slots as $slot_id) {
            // Validate and lock slot
            $check_stmt = $conn->prepare("
                SELECT vts.Status, vts.VenueID, vts.StartTime, vts.EndTime, 
                       vts.Price, vts.DayOfWeek, v.Name as VenueName
                FROM VenueTimeSlot vts
                JOIN Venue v ON vts.VenueID = v.VenueID
                WHERE vts.SlotID = ? AND vts.Status = 'Available' 
                FOR UPDATE
            ");
            $check_stmt->bind_param("i", $slot_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows === 0) {
                throw new Exception("Слот аль хэдийн захиалагдсан байна.");
            }

            $slot_data = $check_result->fetch_assoc();

            // Update slot status
            $update_stmt = $conn->prepare("UPDATE VenueTimeSlot SET Status = 'Booked' WHERE SlotID = ?");
            $update_stmt->bind_param("i", $slot_id);
            $update_stmt->execute();

            // Create booking
            $booking_stmt = $conn->prepare("
                INSERT INTO Booking (UserID, VenueID, SlotID, BookingDate, Duration, Status) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $booking_date = date('Y-m-d');
            $duration = 1;
            $booking_stmt->bind_param("iiisis", $user_id, $slot_data['VenueID'], $slot_id, $booking_date, $duration, $booking_status);
            $booking_stmt->execute();
            $booking_id = $conn->insert_id;

            // Create payment record if not a manager
            if (!$is_manager_of_venue) {
                $payment_stmt = $conn->prepare("
                    INSERT INTO Payment (BookingID, Amount, PaymentDate, Status) 
                    VALUES (?, ?, ?, 'Pending')
                ");
                $payment_stmt->bind_param("ids", $booking_id, $slot_data['Price'], $booking_date);
                $payment_stmt->execute();
            }

            $total_booking_cost += $slot_data['Price'];

            // Create notification
            $notification_stmt = $conn->prepare("
                INSERT INTO Notifications (UserID, Title, Message, NotificationTime) 
                VALUES (?, ?, ?, NOW())
            ");
            $notification_title = "Захиалга #{$booking_id}";
            $notification_message = "Таны {$slot_data['VenueName']} танхимын захиалга {$booking_status} төлөвтэй. 
            Дэлгэрэнгүй: {$slot_data['DayOfWeek']}, {$slot_data['StartTime']}-{$slot_data['EndTime']}, 
            Үнэ: " . number_format($slot_data['Price']) . " ₮";
            
            $notification_stmt->bind_param("iss", $user_id, $notification_title, $notification_message);
            $notification_stmt->execute();
        }

        $conn->commit();

        // Success message
        $_SESSION['booking_message'] = "Таны захиалга амжилттай. Нийт дүн: " . number_format($total_booking_cost) . " ₮";
        $_SESSION['message_type'] = 'success';

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['booking_message'] = $e->getMessage();
        $_SESSION['message_type'] = 'error';
    } finally {
        header('Location: ' . $_SERVER['PHP_SELF'] . (isset($_GET['venue_id']) ? '?venue_id=' . $selected_venue : ''));
        exit();
    }
}

// Fetch venue details
$venue_details = null;
$venue_sports = [];
$venue_images = [];
$slots = [];

if ($selected_venue) {
    // Fetch venue details
    $venue_stmt = $conn->prepare("
        SELECT v.*, COUNT(vs.SportType) as SportCount
        FROM Venue v
        LEFT JOIN VenueSports vs ON v.VenueID = vs.VenueID
        WHERE v.VenueID = ?
        GROUP BY v.VenueID
    ");
    $venue_stmt->bind_param("i", $selected_venue);
    $venue_stmt->execute();
    $venue_details = $venue_stmt->get_result()->fetch_assoc();
    $venue_stmt->close();

    // Get venue sports
    $sports_stmt = $conn->prepare("
        SELECT SportType FROM VenueSports 
        WHERE VenueID = ?
    ");
    $sports_stmt->bind_param("i", $selected_venue);
    $sports_stmt->execute();
    $sports_result = $sports_stmt->get_result();
    while ($sport = $sports_result->fetch_assoc()) {
        $venue_sports[] = $sport['SportType'];
    }
    $sports_stmt->close();

    // FIXED: Image path handling
    $venue_images = [];
    $images_stmt = $conn->prepare("SELECT ImagePath FROM VenueImages WHERE VenueID = ?");
    $images_stmt->bind_param("i", $selected_venue);
    $images_stmt->execute();
    $images_result = $images_stmt->get_result();

    while ($image = $images_result->fetch_assoc()) {
        $image_path = $image['ImagePath'];
        
        // Clean the path - remove any potential problematic parts
        $image_path = ltrim($image_path, './');
        
        // Check if it's an absolute path or relative path
        if (strpos($image_path, 'http://') !== 0 && strpos($image_path, 'https://') !== 0) {
            // It's a relative path, ensure it works properly
            if (file_exists($image_path)) {
                // File exists directly with the path as stored
                $venue_images[] = $image_path;
            } else {
                // Try with additional prefixes
                $alt_paths = [
                    $image_path,
                    '/' . $image_path,
                    'uploads/' . $image_path,
                    '/uploads/' . $image_path
                ];
                
                foreach ($alt_paths as $path) {
                    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($path, '/'))) {
                        $venue_images[] = $path;
                        break;
                    }
                }
            }
        } else {
            // It's an absolute URL, use as is
            $venue_images[] = $image_path;
        }
    }
    $images_stmt->close();
    
    // If no images were found, use a default
    if (empty($venue_images)) {
        $venue_images[] = 'uploads/venue_images/default.jpg';
    }

    // FIXED: Fetch ALL slots, not just available ones
    $slots_stmt = $conn->prepare("
        SELECT SlotID, DayOfWeek, StartTime, EndTime, Price, Status 
        FROM VenueTimeSlot 
        WHERE VenueID = ? 
        ORDER BY FIELD(DayOfWeek, 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), StartTime
    ");
    $slots_stmt->bind_param("i", $selected_venue);
    $slots_stmt->execute();
    $slots = $slots_stmt->get_result();
    $slots_stmt->close();
}

// Prepare week dates
$current_date = date('Y-m-d');
$current_day = date('l');
$days_of_week = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$current_day_index = array_search($current_day, $days_of_week);

$week_dates = [];
for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime("+$i day"));
    $week_dates[] = [
        'day' => $days_of_week[($current_day_index + $i) % 7],
        'date' => $date
    ];
}

// Check if this is a manager viewing as a user
$isManagerViewingAsUser = isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'Manager' && $_SESSION['user_role'] === 'User';
?>
<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $venue_details ? htmlspecialchars($venue_details['Name']) . ' - Захиалга' : 'Заал Захиалах' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'header.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Breadcrumb Navigation -->
        <nav class="flex items-center space-x-2 mb-6 text-sm text-gray-600">
            <a href="home.php" class="hover:text-blue-600 flex items-center">
                <i class="fas fa-home mr-2"></i>Нүүр хуудас
            </a>
            <span>/</span>
            <?php if ($selected_venue): ?>
                <span class="text-gray-900"><?= htmlspecialchars($venue_details['Name']) ?></span>
            <?php else: ?>
                <span class="text-gray-900">Заал сонгох</span>
            <?php endif; ?>
        </nav>

        <!-- Booking Message -->
        <?php if (isset($_SESSION['booking_message'])): ?>
            <div class="mb-6 <?= $_SESSION['message_type'] == 'success' ? 'bg-green-100 border-green-500 text-green-800' : 'bg-red-100 border-red-500 text-red-800' ?> border-l-4 p-4 rounded">
                <?= htmlspecialchars($_SESSION['booking_message']) ?>
                <?php 
                unset($_SESSION['booking_message']);
                unset($_SESSION['message_type']); 
                ?>
            </div>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="bg-white shadow-lg rounded-xl overflow-hidden">
            <?php if (!$selected_venue): ?>
                <!-- Venue Selection Section -->
                <div class="p-8">
                    <h1 class="text-3xl font-bold text-center mb-8 text-gray-800">Танхим сонгох</h1>
                    
                    <?php 
                    // Fetch venues for selection
                    $venues_query = "SELECT v.*, vi.ImagePath as VenueImage 
                                     FROM Venue v 
                                     LEFT JOIN VenueImages vi ON v.VenueID = vi.VenueID 
                                     GROUP BY v.VenueID";
                    $venues_result = $conn->query($venues_query);
                    
                    if ($venues_result->num_rows > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php while ($venue = $venues_result->fetch_assoc()): ?>
                                <div class="border rounded-lg shadow-md hover:shadow-xl transition-all duration-300 group">
                                    <?php 
                                    // FIXED: Image display in venue selection
                                    $venue_image = $venue['VenueImage'];
                                    if ($venue_image) {
                                        $venue_image = ltrim($venue_image, './');
                                    } else {
                                        $venue_image = 'uploads/venue_images/default.jpg';
                                    }
                                    ?>
                                    <div class="relative overflow-hidden rounded-t-lg">
                                        <img src="<?= htmlspecialchars($venue_image) ?>" 
                                             alt="<?= htmlspecialchars($venue['Name']) ?>" 
                                             class="w-full h-24 object-cover group-hover:scale-110 transition-transform duration-300"
                                             onerror="this.src='uploads/venue_images/default.jpg'">
                                    </div>
                                    
                                    <div class="p-5">
                                        <h2 class="text-xl font-bold mb-2 text-gray-800"><?= htmlspecialchars($venue['Name']) ?></h2>
                                        <div class="flex items-center mb-2 text-gray-600">
                                            <i class="fas fa-map-marker-alt mr-2 text-blue-300"></i>
                                            <?= htmlspecialchars($venue['Location']) ?>
                                        </div>
                                        <div class="flex items-center text-green-600 font-semibold">
                                            <i class="fas fa-tag mr-2"></i>
                                            <?= number_format($venue['HourlyPrice']) ?> ₮/цаг
                                        </div>
                                        <a href="?venue_id=<?= $venue['VenueID'] ?>" 
                                           class="mt-4 block text-center bg-blue-600 text-white py-2 rounded hover:bg-blue-700 transition-colors">
                                            Дэлгэрэнгүй
                                        </a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12 bg-yellow-50 rounded-lg">
                            <i class="fas fa-exclamation-triangle text-4xl text-yellow-300 mb-4"></i>
                            <p class="text-lg text-yellow-700">Одоогоор захиалах боломжтой танхим байхгүй байна.</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- Venue Details Section -->
                <?php if ($venue_details): ?>
                    <div class="grid md:grid-cols-2 gap-8 p-8">
                        <!-- Image Gallery -->
                        <div>
                            <div class="relative group">
                                <?php if (!empty($venue_images)): ?>
                                    <div id="venue-image-gallery" class="relative overflow-hidden rounded-lg shadow-lg h-[300px]">
                                        <?php foreach ($venue_images as $index => $image): ?>
                                            <div class="gallery-image absolute inset-0 transition-opacity duration-500 
                                                <?= $index === 0 ? 'opacity-100' : 'opacity-0' ?>"
                                                 data-index="<?= $index ?>">
                                                <img src="<?= htmlspecialchars($image) ?>" 
                                                     alt="Танхимын зураг <?= $index + 1 ?>"
                                                      
                                                     class="w-full h-full object-cover"
                                                     onerror="this.src='uploads/venue_images/default.jpg'">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <?php if (count($venue_images) > 1): ?>
                                        <!-- Image Counter -->
                                        <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 
                                            bg-black/50 text-white px-4 py-1 rounded-full z-10">
                                            <span id="current-image-index">1</span> / <?= count($venue_images) ?>
                                        </div>

                                        <!-- Previous Button -->
                                        <button id="prev-image" class="absolute top-1/2 left-4 transform -translate-y-1/2 
                                            bg-white/30 hover:bg-white/50 rounded-full p-3 z-10">
                                            <i class="fas fa-chevron-left text-white"></i>
                                        </button>

                                        <!-- Next Button -->
                                        <button id="next-image" class="absolute top-1/2 right-4 transform -translate-y-1/2 
                                            bg-white/30 hover:bg-white/50 rounded-full p-3 z-10">
                                            <i class="fas fa-chevron-right text-white"></i>
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="bg-gray-200 h-[500px] flex items-center justify-center">
                                        <span class="text-gray-500">Зураг байхгүй</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Venue Information -->
                        <div>
                            <h1 class="text-3xl font-bold mb-4 text-gray-800"><?= htmlspecialchars($venue_details['Name']) ?></h1>
                            
                            <div class="mb-6">
                                <div class="flex items-center mb-2 text-gray-600">
                                    <i class="fas fa-map-marker-alt mr-2 text-blue-500"></i>
                                    <?= htmlspecialchars($venue_details['Location']) ?>
                                </div>
                                <div class="flex items-center text-green-600 font-semibold mb-2">
                                    <i class="fas fa-tag mr-2"></i>
                                    <?= number_format($venue_details['HourlyPrice']) ?> ₮/цаг
                                </div>
                            </div>

                            <!-- Sports Types -->
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold mb-3 text-gray-700">Спортын төрлүүд</h3>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($venue_sports as $sport): 
                                        $sport_info = $sport_details[$sport] ?? ['emoji' => '🏆', 'color' => 'bg-gray-100'];
                                    ?>
                                        <span class="inline-flex items-center <?= $sport_info['color'] ?> 
                                            px-3 py-1 rounded-full text-sm font-medium">
                                            <?= $sport_info['emoji'] ?> 
                                            <?= htmlspecialchars($sport) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Description -->
                            <?php if (!empty($venue_details['Description'])): ?>
                                <div class="mb-6">
                                    <h3 class="text-lg font-semibold mb-2 text-gray-700">Танхимын тухай</h3>
                                    <p class="text-gray-600 leading-relaxed">
                                        <?= nl2br(htmlspecialchars($venue_details['Description'])) ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- FIXED: Image Gallery JavaScript -->
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        <?php if (!empty($venue_images) && count($venue_images) > 1): ?>
                        const gallery = document.getElementById('venue-image-gallery');
                        const prevButton = document.getElementById('prev-image');
                        const nextButton = document.getElementById('next-image');
                        const currentIndexDisplay = document.getElementById('current-image-index');
                        const galleryImages = document.querySelectorAll('.gallery-image');
                        let currentIndex = 0;

                        function showImage(index) {
                            // Ensure index is within bounds
                            index = (index + galleryImages.length) % galleryImages.length;

                            // Hide all images
                            galleryImages.forEach(img => {
                                img.classList.add('opacity-0');
                                img.classList.remove('opacity-100');
                            });

                            // Show current image
                            galleryImages[index].classList.remove('opacity-0');
                            galleryImages[index].classList.add('opacity-100');

                            // Update counter
                            currentIndexDisplay.textContent = index + 1;
                            
                            // Update current index
                            currentIndex = index;
                        }

                        // Next Image
                        nextButton.addEventListener('click', function() {
                            showImage(currentIndex + 1);
                        });

                        // Previous Image
                        prevButton.addEventListener('click', function() {
                            showImage(currentIndex - 1);
                        });

                        // Optional: Auto-slide every 5 seconds
                        let autoSlideInterval = setInterval(() => {
                            showImage(currentIndex + 1);
                        }, 5000);

                        // Pause auto-slide on hover
                        gallery.addEventListener('mouseenter', () => {
                            clearInterval(autoSlideInterval);
                        });

                        gallery.addEventListener('mouseleave', () => {
                            autoSlideInterval = setInterval(() => {
                                showImage(currentIndex + 1);
                            }, 5000);
                        });
                        <?php endif; ?>
                    });
                    </script>

                    <!-- Booking Slots Section -->
                    <div class="p-8 bg-gray-50 border-t">
                        <?php if ($slots && $slots->num_rows > 0): ?>
                            <form method="POST" id="booking-form">
                                <input type="hidden" name="venue_id" value="<?= $selected_venue ?>">
                                
                                <h2 class="text-2xl font-bold mb-6 text-gray-800">Захиалах боломжтой цагууд</h2>

                                <div class="overflow-x-auto">
                                    <table class="w-full bg-white border rounded-lg shadow-sm">
                                        <thead>
                                            <tr class="bg-gray-100 text-gray-600 uppercase text-sm">
                                                <th class="p-3 text-left">Цаг</th>
                                                <?php foreach ($week_dates as $week_date): ?>
                                                    <th class="p-3 text-center">
                                                        <?= date('D, d/m', strtotime($week_date['date'])) ?>
                                                    </th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $timeslots_by_time = [];
                                            $slots->data_seek(0);
                                            while ($slot = $slots->fetch_assoc()) {
                                                $timeslots_by_time[$slot['StartTime']][] = $slot;
                                            }

                                            // Get current date and time for comparison
                                            $current_date_time = new DateTime();

                                            foreach ($timeslots_by_time as $start_time => $slots_group) {
                                                echo "<tr>";
                                                echo "<td class='p-3 border-b font-medium'>" . date('H:i', strtotime($start_time)) . "</td>";

                                                foreach ($week_dates as $week_date) {
                                                    $slot_found = false;
                                                    foreach ($slots_group as $slot) {
                                                        if ($slot['DayOfWeek'] == $week_date['day']) {
                                                            $slot_found = true;
                                                            $slot_id = $slot['SlotID'];
                                                            $price = $slot['Price'];
                                                            $status = $slot['Status'];
                                                            
                                                            // Create DateTime for the slot
                                                            $slot_datetime = new DateTime($week_date['date'] . ' ' . $slot['StartTime']);
                                                            
                                                            // Check if slot is in the past
                                                            if ($slot_datetime <= $current_date_time) {
                                                                // Display as finished
                                                                echo "<td class='p-3 border-b text-center'>
                                                                        <span class='px-3 py-1 rounded bg-gray-200 text-gray-500 text-sm'>
                                                                            Дууссан
                                                                        </span>
                                                                    </td>";
                                                            } 
                                                            // FIXED: Check if the slot is already booked
                                                            elseif ($status == 'Booked') {
                                                                // Display as booked
                                                                echo "<td class='p-3 border-b text-center'>
                                                                        <span class='px-3 py-1 rounded bg-red-100 text-red-700 text-sm'>
                                                                            Захиалагдсан
                                                                        </span>
                                                                    </td>";
                                                            } 
                                                            // Available slot
                                                            else {
                                                                echo "<td class='p-3 border-b text-center'>
                                                                        <label class='inline-flex items-center cursor-pointer'>
                                                                            <input type='checkbox' 
                                                                                name='selected_slots[]' 
                                                                                value='$slot_id' 
                                                                                class='slot-checkbox hidden'
                                                                                onchange='updateSlotSelection(this, $price)'>
                                                                            <span class='slot-label px-3 py-1 rounded 
                                                                                        hover:bg-blue-100 
                                                                                        transition duration-200 
                                                                                        text-sm font-medium'>
                                                                                " . number_format($price) . " ₮
                                                                            </span>
                                                                        </label>
                                                                    </td>";
                                                            }
                                                            break;
                                                        }
                                                    }

                                                    if (!$slot_found) {
                                                        echo "<td class='p-3 border-b text-center text-gray-400'>-</td>";
                                                    }
                                                }
                                                echo "</tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Booking Summary -->
                                <div class="mt-6 bg-white p-6 rounded-lg shadow-sm">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="text-gray-700">
                                                <span class="font-semibold">Нийт цаг:</span> 
                                                <span id="total-hours">0</span> цаг
                                            </p>
                                            <p class="text-gray-700 mt-2">
                                                <span class="font-semibold">Нийт үнэ:</span> 
                                                <span id="total-cost">0</span> ₮
                                            </p>
                                        </div>
                                        
                                        <?php if (!isset($_SESSION['user_id'])): ?>
                                            <div class="bg-yellow-100 border-l-4 border-yellow-500 p-3 rounded">
                                                <p class="text-yellow-700">
                                                    Захиалга хийхын тулд 
                                                    <a href="login.php" class="text-blue-600 font-bold hover:underline">нэвтэрнэ үү</a>
                                                </p>
                                            </div>
                                        <?php endif; ?>

                                        <button type="submit" 
                                                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition duration-300 
                                                       <?= !isset($_SESSION['user_id']) ? 'opacity-50 cursor-not-allowed' : '' ?>"
                                                <?= !isset($_SESSION['user_id']) ? 'disabled' : '' ?>>
                                            Захиалга баталгаажуулах
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="bg-yellow-50 p-6 rounded-lg text-center">
                                <i class="fas fa-calendar-times text-yellow-600 text-4xl mb-4"></i>
                                <p class="text-yellow-700 text-lg">Энэ танхимд одоогоор нээлттэй цаг байхгүй байна.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function updateSlotSelection(checkbox, price) {
            const totalHoursEl = document.getElementById('total-hours');
            const totalCostEl = document.getElementById('total-cost');
            
            let totalHours = parseInt(totalHoursEl.textContent);
            let totalCost = parseFloat(totalCostEl.textContent.replace(/,/g, ''));
            
            const label = checkbox.closest('td').querySelector('.slot-label');
            
            if (checkbox.checked) {
                // Add slot
                totalHours++;
                totalCost += price;
                label.classList.add('bg-blue-100', 'text-blue-800');
            } else {
                // Remove slot
                totalHours--;
                totalCost -= price;
                label.classList.remove('bg-blue-100', 'text-blue-800');
            }
            totalHoursEl.textContent = totalHours;
            totalCostEl.textContent = totalCost.toLocaleString();
        }
    </script>
</body>
</html>

<?php 
// Clean up database resources
if (isset($conn)) {
    $conn->close();
}
?>
