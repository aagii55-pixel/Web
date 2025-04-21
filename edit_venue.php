<?php
session_start();
require 'config/db.php';

// Зөвхөн менежер эрхтэй хэрэглэгчид нэвтрэх боломжтой
function ensureManagerAccess() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'Manager') {
        header('Location: ../login.php');
        exit();
    }
}

// Эрхийн шалгалт хийх
ensureManagerAccess();

$managerID = $_SESSION['user_id'];
$venueID = $_GET['venue_id'] ?? null;

if (!$venueID) {
    $_SESSION['error_message'] = "Заалны ID дутуу байна.";
    header('Location: manager_venues.php');
    exit();
}

// Заалны мэдээлэл авах
$venueSql = "SELECT * FROM Venue WHERE VenueID = ? AND ManagerID = ?";
$stmt = $conn->prepare($venueSql);
$stmt->bind_param("ii", $venueID, $managerID);
$stmt->execute();
$venue = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$venue) {
    $_SESSION['error_message'] = "Заал олдсонгүй эсвэл танд энэ заалыг засах эрх байхгүй байна.";
    header('Location: manager_venues.php');
    exit();
}

// Спортын төрлүүдийг VenueSports хүснэгтээс авах
$sportTypesSql = "SELECT SportType FROM VenueSports WHERE VenueID = ?";
$stmt = $conn->prepare($sportTypesSql);
$stmt->bind_param("i", $venueID);
$stmt->execute();
$sportTypesResult = $stmt->get_result();
$currentSportTypes = [];
while ($row = $sportTypesResult->fetch_assoc()) {
    $currentSportTypes[] = $row['SportType'];
}
$stmt->close();

// Бүх боломжит спортын төрлүүд
$sportTypes = [
    'Хөлбөмбөг', 'Сагсанбөмбөг', 'Волейбол', 
    'Ширээний теннис', 'Бадминтон', 'Талбайн теннис', 
    'Гольф', 'Бүжиг', 'Иога', 'Билльярд'
];

// Заалны цагийн хуваарь авах
$timeSlotsSql = "SELECT * FROM VenueTimeSlot WHERE VenueID = ? ORDER BY Week, DayOfWeek, StartTime";
$stmt = $conn->prepare($timeSlotsSql);
$stmt->bind_param("i", $venueID);
$stmt->execute();
$timeSlots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Цагийн хуваарийг долоо хоног, өдрөөр ангилах
$organizedSlots = [];
$weekNumbers = [];
foreach ($timeSlots as $slot) {
    $organizedSlots[$slot['Week']][$slot['DayOfWeek']][] = $slot;
    if (!in_array($slot['Week'], $weekNumbers)) {
        $weekNumbers[] = $slot['Week'];
    }
}
sort($weekNumbers);

// Өдрүүдийн дараалал
$daysOrder = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$daysMongoilian = ['Ням', 'Даваа', 'Мягмар', 'Лхагва', 'Пүрэв', 'Баасан', 'Бямба'];

// Заал болон цагийн хуваарь шинэчлэх
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();
    try {
        // Сонгосон спортын төрөл боловсруулах
        $selectedSportTypes = isset($_POST['sport_types']) ? $_POST['sport_types'] : [];
        
        if (empty($selectedSportTypes)) {
            throw new Exception("Дор хаяж нэг спортын төрөл сонгоно уу.");
        }
        
        $sportTypeString = implode(', ', $selectedSportTypes);

        // Заалны мэдээлэл шинэчлэх
        $stmt = $conn->prepare("UPDATE Venue SET Name = ?, Location = ?, SportType = ?, HourlyPrice = ?, Description = ?, MapLocation = ? WHERE VenueID = ? AND ManagerID = ?");
        $stmt->bind_param("sssdssii", $_POST['name'], $_POST['location'], $sportTypeString, $_POST['hourly_price'], $_POST['description'], $_POST['map_location'], $venueID, $managerID);
        
        if (!$stmt->execute()) {
            throw new Exception("Заал шинэчлэх үед алдаа гарлаа: " . $stmt->error);
        }
        $stmt->close();

        // VenueSports хүснэгтээс хуучин төрлүүдийг устгаж шинээр нэмэх
        $deleteStmt = $conn->prepare("DELETE FROM VenueSports WHERE VenueID = ?");
        $deleteStmt->bind_param("i", $venueID);
        $deleteStmt->execute();
        $deleteStmt->close();
        
        // Шинээр спортын төрөл оруулах
        $insertStmt = $conn->prepare("INSERT INTO VenueSports (VenueID, SportType) VALUES (?, ?)");
        foreach ($selectedSportTypes as $sport) {
            $insertStmt->bind_param("is", $venueID, $sport);
            $insertStmt->execute();
        }
        $insertStmt->close();

        // Цагийн хуваарь шинэчлэх - Одоогийн цагуудыг шинэчлэх
        if (!empty($_POST['time_slots'])) {
            foreach ($_POST['time_slots'] as $slotID => $slotData) {
                if ($slotID > 0) { // Одоогийн цагуудыг шинэчлэх
                    $stmtSlot = $conn->prepare("UPDATE VenueTimeSlot SET Price = ? WHERE SlotID = ? AND VenueID = ?");
                    $stmtSlot->bind_param("dii", $slotData['price'], $slotID, $venueID);

                    if (!$stmtSlot->execute()) {
                        throw new Exception("Цагийн хуваарь шинэчлэх үед алдаа гарлаа: " . $stmtSlot->error);
                    }
                    $stmtSlot->close();
                }
            }
        }
        
        // Шинээр нэмэгдсэн цагуудыг оруулах
        if (!empty($_POST['new_time_slots'])) {
            $stmtNewSlot = $conn->prepare("INSERT INTO VenueTimeSlot (VenueID, Week, DayOfWeek, StartTime, EndTime, Status, Price) VALUES (?, ?, ?, ?, ?, 'Available', ?)");
            
            foreach ($_POST['new_time_slots'] as $week => $days) {
                foreach ($days as $dayOfWeek => $hours) {
                    foreach ($hours as $hour => $slot) {
                        $startTime = sprintf('%02d:00:00', $hour);
                        $endTime = sprintf('%02d:00:00', ($hour + 1) % 24);
                        $price = floatval($slot['price']);
                        
                        $stmtNewSlot->bind_param("iisssd", $venueID, $week, $dayOfWeek, $startTime, $endTime, $price);
                        
                        if (!$stmtNewSlot->execute()) {
                            throw new Exception("Шинэ цагийн хуваарь нэмэх үед алдаа гарлаа: " . $stmtNewSlot->error);
                        }
                    }
                }
            }
            $stmtNewSlot->close();
        }
        
        // Устгах цагуудыг устгах
        if (!empty($_POST['delete_slots']) && is_array($_POST['delete_slots'])) {
            $deleteSlotStmt = $conn->prepare("DELETE FROM VenueTimeSlot WHERE SlotID = ? AND VenueID = ?");
            
            foreach ($_POST['delete_slots'] as $slotID) {
                $deleteSlotStmt->bind_param("ii", $slotID, $venueID);
                $deleteSlotStmt->execute();
            }
            $deleteSlotStmt->close();
        }

        $conn->commit();
        $_SESSION['success_message'] = "Заал болон цагийн хуваарь амжилттай шинэчлэгдлээ!";
        header('Location: manager_venues.php');
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заал засварлах - <?php echo htmlspecialchars($venue['Name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .time-slots-table::-webkit-scrollbar {
            height: 8px;
        }
        .time-slots-table::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 8px;
        }
        .time-slots-table::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 8px;
        }
        .time-slots-table::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php include 'headers/header_manager.php'; ?>
    
    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-3xl font-bold text-gray-900">Заал засварлах</h1>
            <a href="manager_venues.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                <i class="fas fa-arrow-left mr-2"></i> Буцах
            </a>
        </div>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
                <p class="font-bold">Алдаа!</p>
                <p><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <form action="" method="post" class="p-6">
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">Заалны үндсэн мэдээлэл</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Заалны нэр:</label>
                            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($venue['Name']); ?>" required class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md p-2 border">
                        </div>

                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700 mb-1">Байршил:</label>
                            <input type="text" name="location" id="location" value="<?php echo htmlspecialchars($venue['Location']); ?>" required class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md p-2 border">
                        </div>

                        <div>
                            <label for="hourly_price" class="block text-sm font-medium text-gray-700 mb-1">Цагийн үнэ:</label>
                            <input type="number" step="0.01" name="hourly_price" id="hourly-price" value="<?php echo htmlspecialchars($venue['HourlyPrice']); ?>" required class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md p-2 border">
                        </div>

                        <div>
                            <label for="map_location" class="block text-sm font-medium text-gray-700 mb-1">Газрын зураг холбоос (URL):</label>
                            <input type="text" name="map_location" id="map_location" value="<?php echo htmlspecialchars($venue['MapLocation']); ?>" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md p-2 border">
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Дэлгэрэнгүй мэдээлэл:</label>
                        <textarea name="description" id="description" rows="4" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md p-2 border"><?php echo htmlspecialchars($venue['Description']); ?></textarea>
                    </div>
                    
                    <div class="mt-6">
                        <span class="block text-sm font-medium text-gray-700 mb-3">Спортын төрлүүд:</span>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                            <?php foreach ($sportTypes as $sport): ?>
                                <label class="inline-flex items-center p-3 border <?php echo in_array($sport, $currentSportTypes) ? 'border-blue-500 bg-blue-50' : 'border-gray-200'; ?> rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="checkbox" name="sport_types[]" value="<?php echo $sport; ?>" <?php echo in_array($sport, $currentSportTypes) ? 'checked' : ''; ?> class="h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                                    <span class="ml-2 text-sm"><?php echo $sport; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <button type="button" onclick="setDefaultHourlyPrice()" class="w-full py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-sync-alt mr-2"></i> Бүх цагуудад үндсэн үнийг тохируулах
                    </button>
                </div>

                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">Цагийн хуваарь</h2>
                    
                    <!-- Долоо хоногийн таб -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex overflow-x-auto pb-2 space-x-2" id="week-tabs">
                            <?php foreach ($weekNumbers as $index => $week): ?>
                                <button type="button" class="px-4 py-2 <?php echo $index === 0 ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'; ?> rounded-md hover:bg-blue-600 hover:text-white transition-colors" 
                                     onclick="switchWeek(<?php echo $week; ?>)"
                                     data-week="<?php echo $week; ?>">
                                    <?php echo $week; ?>-р долоо хоног
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <div class="flex space-x-2">
                            <button type="button" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition-colors" 
                                    onclick="addWeek()">
                                <i class="fas fa-plus mr-1"></i> Долоо хоног нэмэх
                            </button>
                        </div>
                    </div>
                    
                    <!-- Долоо хоногийн хуваарь -->
                    <div id="weeks-container">
                        <?php foreach ($organizedSlots as $week => $days): ?>
                            <div id="week-<?php echo $week; ?>" class="week-container <?php echo $week === $weekNumbers[0] ? 'block' : 'hidden'; ?> bg-gray-50 p-6 rounded-lg mb-4">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-lg font-semibold"><?php echo $week; ?>-р долоо хоног</h4>
                                    <button type="button" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors" 
                                            onclick="removeWeek(<?php echo $week; ?>)"
                                            <?php echo count($weekNumbers) <= 1 ? 'disabled' : ''; ?>>
                                        <i class="fas fa-trash-alt mr-1"></i> Долоо хоног устгах
                                    </button>
                                </div>
                                <div class="time-slots-table flex overflow-x-auto pb-4 space-x-4">
                                    <?php foreach ($daysOrder as $dayIndex => $dayOfWeek): ?>
                                        <div class="flex-none w-60 bg-white rounded-lg shadow-sm p-4">
                                            <h4 class="font-semibold text-center mb-4 text-gray-800"><?php echo $daysMongoilian[$dayIndex]; ?></h4>
                                            <div class="space-y-2" id="day-slots-<?php echo $week; ?>-<?php echo $dayOfWeek; ?>">
                                                <?php if (isset($days[$dayOfWeek])): ?>
                                                    <?php foreach ($days[$dayOfWeek] as $slot): 
                                                        $hour = intval(date('H', strtotime($slot['StartTime'])));
                                                    ?>
                                                        <div class="p-2 border border-gray-200 rounded-md" id="slot-<?php echo $slot['SlotID']; ?>">
                                                            <div class="text-sm text-gray-600 mb-2">
                                                                <?php echo date('H:i', strtotime($slot['StartTime'])) . ' - ' . date('H:i', strtotime($slot['EndTime'])); ?>
                                                            </div>
                                                            <div class="flex items-center gap-2">
                                                                <input type="number" step="0.01" name="time_slots[<?php echo $slot['SlotID']; ?>][price]" value="<?php echo htmlspecialchars($slot['Price']); ?>" class="w-full px-2 py-1 border rounded text-sm">
                                                                <button type="button" class="flex-none p-1 bg-red-500 text-white text-sm rounded hover:bg-red-600 transition-colors" 
                                                                        <?php echo $slot['Status'] === 'Booked' ? 'disabled' : ''; ?>
                                                                        onclick="removeSlot(<?php echo $slot['SlotID']; ?>)">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            </div>
                                                            <?php if ($slot['Status'] === 'Booked'): ?>
                                                                <div class="mt-1 text-xs text-yellow-600">
                                                                    <i class="fas fa-exclamation-triangle mr-1"></i> Захиалгатай
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="mt-4 w-full px-2 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600 transition-colors" 
                                                    onclick="showAddTimeSlotModal(<?php echo $week; ?>, '<?php echo $dayOfWeek; ?>')">
                                                <i class="fas fa-plus mr-1"></i> Цаг нэмэх
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Устгах цагийн ID-г хадгалах нууц талбар -->
                <div id="delete-slots-container"></div>

                <div class="pt-5 border-t border-gray-200">
                    <div class="flex justify-end">
                        <a href="manager_venues.php" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-3">
                            Цуцлах
                        </a>
                        <button type="submit" class="py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-save mr-2"></i> Хадгалах
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Цаг нэмэх модал -->
    <div id="addTimeSlotModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Цаг нэмэх</h3>
                <button type="button" class="text-gray-400 hover:text-gray-500" onclick="hideAddTimeSlotModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="addTimeSlotForm" class="space-y-4">
                <input type="hidden" id="modal-week" value="">
                <input type="hidden" id="modal-day" value="">
                
                <div>
                    <label for="modal-hour" class="block text-sm font-medium text-gray-700 mb-1">Цаг</label>
                    <select id="modal-hour" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md p-2 border">
                        <?php for ($i = 0; $i < 24; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo sprintf('%02d:00', $i); ?> - <?php echo sprintf('%02d:00', ($i + 1) % 24); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div>
                    <label for="modal-price" class="block text-sm font-medium text-gray-700 mb-1">Үнэ</label>
                    <input type="number" step="0.01" id="modal-price" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md p-2 border">
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50" onclick="hideAddTimeSlotModal()">
                        Цуцлах
                    </button>
                    <button type="button" class="py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700" onclick="addTimeSlot()">
                        Нэмэх
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let nextWeekIndex = <?php echo !empty($weekNumbers) ? max($weekNumbers) + 1 : 1; ?>;
        let deletedSlots = [];
        
        function setDefaultHourlyPrice() {
            const defaultPrice = document.getElementById("hourly-price").value;
            if (defaultPrice === "" || isNaN(defaultPrice)) {
                alert("Эхлээд цагийн үндсэн үнийг оруулна уу!");
                return;
            }
            
            document.querySelectorAll('input[name^="time_slots"][type="number"], input[name^="new_time_slots"][type="number"]').forEach(input => {
                input.value = defaultPrice;
            });
            
            // Мэдэгдэл харуулах
            const notification = document.createElement('div');
            notification.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded-md shadow-lg transition-opacity duration-500 opacity-100';
            notification.innerHTML = '<i class="fas fa-check mr-2"></i> Бүх цагуудад үндсэн үнэ тохируулагдлаа';
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.replace('opacity-100', 'opacity-0');
                setTimeout(() => notification.remove(), 500);
            }, 3000);
        }

        function switchWeek(weekNumber) {
            // Бүх долоо хоногийг нуух
            document.querySelectorAll('.week-container').forEach(container => {
                container.classList.add('hidden');
            });
            
            // Сонгосон долоо хоногийг харуулах
            const selectedWeek = document.getElementById('week-' + weekNumber);
            if (selectedWeek) {
                selectedWeek.classList.remove('hidden');
            }
            
            // Табуудын загварыг шинэчлэх
            document.querySelectorAll('button[data-week]').forEach(tab => {
                tab.classList.remove('bg-blue-500', 'text-white');
                tab.classList.add('bg-gray-200', 'text-gray-700');
            });
            
            // Идэвхтэй табыг тохируулах
            const clickedTab = document.querySelector('button[data-week="' + weekNumber + '"]');
            if (clickedTab) {
                clickedTab.classList.remove('bg-gray-200', 'text-gray-700');
                clickedTab.classList.add('bg-blue-500', 'text-white');
            }
        }
        
        function addWeek() {
            const weekTabs = document.getElementById('week-tabs');
            const weeksContainer = document.getElementById('weeks-container');
            const weekNumber = nextWeekIndex++;
            
        // Шинэ долоо хоногийн таб үүсгэх
        const newTab = document.createElement('button');
            newTab.setAttribute('type', 'button');
            newTab.className = 'px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-blue-600 hover:text-white transition-colors';
            newTab.setAttribute('onclick', `switchWeek(${weekNumber})`);
            newTab.setAttribute('data-week', weekNumber);
            newTab.innerHTML = `${weekNumber}-р долоо хоног`;
            weekTabs.appendChild(newTab);
            
            // Шинэ долоо хоногийн агуулга үүсгэх
            const newWeek = document.createElement('div');
            newWeek.id = `week-${weekNumber}`;
            newWeek.className = 'week-container hidden bg-gray-50 p-6 rounded-lg mb-4';
            
            // Өдрүүдийн Монгол нэрс
            const daysMongoilian = ['Ням', 'Даваа', 'Мягмар', 'Лхагва', 'Пүрэв', 'Баасан', 'Бямба'];
            // Өдрүүдийн Англи нэрс
            const daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            
            let daysContent = '';
            
            // Долоо хоногийн гарчиг ба устгах товч
            daysContent += `
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold">${weekNumber}-р долоо хоног</h4>
                    <button type="button" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors" 
                            onclick="removeWeek(${weekNumber})">
                        <i class="fas fa-trash-alt mr-1"></i> Долоо хоног устгах
                    </button>
                </div>
                <div class="time-slots-table flex overflow-x-auto pb-4 space-x-4">
            `;
            
            // Өдөр тус бүрийн цагууд
            for (let i = 0; i < 7; i++) {
                daysContent += `
                    <div class="flex-none w-60 bg-white rounded-lg shadow-sm p-4">
                        <h4 class="font-semibold text-center mb-4 text-gray-800">${daysMongoilian[i]}</h4>
                        <div class="space-y-2" id="day-slots-${weekNumber}-${daysOfWeek[i]}">
                            <!-- Энд цагууд харагдана -->
                        </div>
                        <button type="button" class="mt-4 w-full px-2 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600 transition-colors" 
                                onclick="showAddTimeSlotModal(${weekNumber}, '${daysOfWeek[i]}')">
                            <i class="fas fa-plus mr-1"></i> Цаг нэмэх
                        </button>
                    </div>
                `;
            }
            
            daysContent += '</div>';
            newWeek.innerHTML = daysContent;
            weeksContainer.appendChild(newWeek);
            
            // Шинэ долоо хоногийг харуулах
            switchWeek(weekNumber);
        }
        
        function removeWeek(weekNumber) {
            // Тухайн долоо хоногийн бүх цагуудыг устгах цагуудын жагсаалтад нэмэх
            const weekContainer = document.getElementById(`week-${weekNumber}`);
            if (weekContainer) {
                const slotElements = weekContainer.querySelectorAll('[id^="slot-"]');
                slotElements.forEach(element => {
                    const slotId = element.id.replace('slot-', '');
                    if (!isNaN(slotId)) {
                        deletedSlots.push(parseInt(slotId));
                    }
                });
            }
            
            // Долоо хоногийн табыг устгах
            const tab = document.querySelector(`button[data-week="${weekNumber}"]`);
            if (tab) {
                tab.remove();
            }
            
            // Долоо хоногийн агуулгыг устгах
            if (weekContainer) {
                weekContainer.remove();
            }
            
            // Долоо хоногийн тоог шалгах - хоосон бол нэгийг шинээр үүсгэх
            const remainingWeeks = document.querySelectorAll('.week-container');
            if (remainingWeeks.length === 0) {
                addWeek();
            } else {
                // Өөр долоо хоног руу шилжих
                const firstTab = document.querySelector('button[data-week]');
                if (firstTab) {
                    const firstWeekNumber = firstTab.getAttribute('data-week');
                    switchWeek(parseInt(firstWeekNumber));
                }
            }
            
            // Устгасан цагуудын ID-г нууц талбарт хадгалах
            updateDeletedSlotsInput();
        }
        
        function showAddTimeSlotModal(week, day) {
            document.getElementById('modal-week').value = week;
            document.getElementById('modal-day').value = day;
            document.getElementById('modal-price').value = document.getElementById('hourly-price').value || '';
            
            const modal = document.getElementById('addTimeSlotModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
        
        function hideAddTimeSlotModal() {
            const modal = document.getElementById('addTimeSlotModal');
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }
        
        function addTimeSlot() {
            const week = document.getElementById('modal-week').value;
            const day = document.getElementById('modal-day').value;
            const hour = document.getElementById('modal-hour').value;
            const price = document.getElementById('modal-price').value;
            
            // Цаг тус бүрийн агуулгыг хариуцах контейнер
            const dayContainer = document.getElementById(`day-slots-${week}-${day}`);
            
            if (dayContainer) {
                // Тухайн цаг аль хэдийн бий эсэхийг шалгах
                const existingSlot = dayContainer.querySelector(`[data-hour="${hour}"]`);
                if (existingSlot) {
                    alert('Энэ цаг аль хэдийн байна!');
                    return;
                }
                
                // Шинэ цагийн элемент үүсгэх
                const newSlot = document.createElement('div');
                newSlot.className = 'p-2 border border-gray-200 rounded-md';
                newSlot.setAttribute('data-hour', hour);
                
                // Цагийн форматыг хийх
                const startHour = hour.padStart(2, '0');
                const endHour = (parseInt(hour) + 1) % 24;
                const endHourStr = endHour.toString().padStart(2, '0');
                
                newSlot.innerHTML = `
                    <div class="text-sm text-gray-600 mb-2">${startHour}:00 - ${endHourStr}:00</div>
                    <div class="flex items-center gap-2">
                        <input type="number" step="0.01" name="new_time_slots[${week}][${day}][${hour}][price]" value="${price}" class="w-full px-2 py-1 border rounded text-sm">
                        <button type="button" class="flex-none p-1 bg-red-500 text-white text-sm rounded hover:bg-red-600 transition-colors" onclick="removeNewSlot(this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                
                dayContainer.appendChild(newSlot);
                hideAddTimeSlotModal();
            }
        }
        
        function removeSlot(slotId) {
            // Цагийг DOM-оос устгах
            const slotElement = document.getElementById(`slot-${slotId}`);
            if (slotElement) {
                slotElement.remove();
            }
            
            // Устгасан цагийн ID-г массивт хадгалах
            deletedSlots.push(slotId);
            
            // Устгасан цагуудын ID-г нууц талбарт хадгалах
            updateDeletedSlotsInput();
        }
        
        function removeNewSlot(buttonElement) {
            // Шинээр нэмсэн цагийг DOM-оос устгах
            const slotElement = buttonElement.closest('div[data-hour]');
            if (slotElement) {
                slotElement.remove();
            }
        }
        
        function updateDeletedSlotsInput() {
            // Устгасан цагуудын ID-г нууц талбарт хадгалах
            const container = document.getElementById('delete-slots-container');
            container.innerHTML = '';
            
            deletedSlots.forEach(slotId => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_slots[]';
                input.value = slotId;
                container.appendChild(input);
            });
        }
        
        // Спортын төрлийн сонголт
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('input[name="sport_types[]"]').forEach(input => {
                input.addEventListener('change', function() {
                    const label = this.closest('label');
                    if (this.checked) {
                        label.classList.add('border-blue-500', 'bg-blue-50');
                        label.classList.remove('border-gray-200');
                    } else {
                        label.classList.remove('border-blue-500', 'bg-blue-50');
                        label.classList.add('border-gray-200');
                    }
                });
            });
            
            // Форм илгээхээс өмнө шалгах
            document.querySelector('form').addEventListener('submit', function(e) {
                // Заавал шаардлагатай талбаруудыг шалгах
                const name = document.getElementById('name').value.trim();
                const location = document.getElementById('location').value.trim();
                const hourlyPrice = document.getElementById('hourly-price').value.trim();
                
                if (!name || !location || !hourlyPrice) {
                    e.preventDefault();
                    alert('Заалны нэр, байршил, цагийн үнийг заавал оруулна уу!');
                    return;
                }
                
                // Дор хаяж нэг спортын төрөл сонгосон эсэхийг шалгах
                const sportTypes = document.querySelectorAll('input[name="sport_types[]"]:checked');
                if (sportTypes.length === 0) {
                    e.preventDefault();
                    alert('Дор хаяж нэг спортын төрөл сонгоно уу!');
                    return;
                }
                
                // Дор хаяж нэг долоо хоног байгаа эсэхийг шалгах
                const weekContainers = document.querySelectorAll('.week-container');
                if (weekContainers.length === 0) {
                    e.preventDefault();
                    alert('Дор хаяж нэг долоо хоног байх ёстой!');
                    return;
                }
            });
        });
    </script>
</body>
</html>