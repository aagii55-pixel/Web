<?php
session_start();
require 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if user has VenueStaff role
$userRolesQuery = "SELECT DISTINCT vsa.Role 
                  FROM venuestaffassignment vsa 
                  WHERE vsa.UserID = ?";
$stmt = $conn->prepare($userRolesQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rolesResult = $stmt->get_result();

$userRoles = [];
while ($role = $rolesResult->fetch_assoc()) {
    $userRoles[] = $role['Role'];
}

// If user does not have 'VenueStaff' role, redirect
if (!in_array('VenueStaff', $userRoles)) {
    header("Location: index.php?error=not_authorized");
    exit;
}

// Get venue assignments for Venue Staff
$venueStaffAssignmentsQuery = "SELECT vsa.*, v.Name as VenueName, v.ManagerID
                    FROM venuestaffassignment vsa 
                    JOIN venue v ON vsa.VenueID = v.VenueID 
                    WHERE vsa.UserID = ? AND vsa.Role = 'VenueStaff'";
$stmt = $conn->prepare($venueStaffAssignmentsQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$venueStaffAssignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (count($venueStaffAssignments) == 0) {
    header("Location: index.php?error=no_venue_assigned");
    exit;
}

// Get venue ID from URL or default to first assigned venue
$venueID = isset($_GET['venue_id']) ? intval($_GET['venue_id']) : $venueStaffAssignments[0]['VenueID'];

// Check if user has access to this venue
$venueAccess = false;
foreach ($venueStaffAssignments as $assignment) {
    if ($assignment['VenueID'] == $venueID) {
        $venueAccess = true;
        $currentVenue = $assignment;
        break;
    }
}

if (!$venueAccess) {
    header("Location: venueStaff_edit_venue.php?venue_id=" . $venueStaffAssignments[0]['VenueID']);
    exit;
}

// Define valid sport types
$validSportTypes = [
    'Хөлбөмбөг', 'Сагсанбөмбөг', 'Волейбол', 
    'Ширээний теннис', 'Бадминтон', 'Талбайн теннис', 
    'Гольф', 'Бүжиг', 'Иога', 'Билльярд'
];

// Fetch venue details
$venueSql = "SELECT * FROM Venue WHERE VenueID = ?";
$stmt = $conn->prepare($venueSql);
$stmt->bind_param("i", $venueID);
$stmt->execute();
$venue = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$venue) {
    echo "<script>alert('Заал олдсонгүй.'); window.location.href='venueStaff_edit_venue.php';</script>";
    exit();
}

// Current venue sport types (comma-separated string to array)
$currentSportTypes = !empty($venue['SportType']) ? explode(',', $venue['SportType']) : [];
$currentSportTypes = array_map('trim', $currentSportTypes);

// Fetch existing time slots for this venue
$timeSlotsSql = "SELECT * FROM VenueTimeSlot WHERE VenueID = ?";
$stmt = $conn->prepare($timeSlotsSql);
$stmt->bind_param("i", $venueID);
$stmt->execute();
$timeSlots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Organize time slots by week and day for easy display
$organizedSlots = [];
$weekNumbers = [];
foreach ($timeSlots as $slot) {
    $organizedSlots[$slot['Week']][$slot['DayOfWeek']][] = $slot;
    if (!in_array($slot['Week'], $weekNumbers)) {
        $weekNumbers[] = $slot['Week'];
    }
}
sort($weekNumbers);

// Handle form submission for updating venue and time slots
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();
    try {
        // Process selected sport types
        $selectedSportTypes = isset($_POST['sport_types']) ? $_POST['sport_types'] : [];
        $sportTypeString = implode(', ', $selectedSportTypes);

        // Update venue details
        $stmt = $conn->prepare("UPDATE Venue SET Name = ?, Location = ?, SportType = ?, HourlyPrice = ?, Description = ?, MapLocation = ? WHERE VenueID = ?");
        $stmt->bind_param("sssdssi", $_POST['name'], $_POST['location'], $sportTypeString, $_POST['hourly_price'], $_POST['description'], $_POST['map_location'], $venueID);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating venue: " . $stmt->error);
        }
        $stmt->close();

        // Update each time slot
        if (!empty($_POST['time_slots'])) {
            foreach ($_POST['time_slots'] as $slotID => $slotData) {
                $stmtSlot = $conn->prepare("UPDATE VenueTimeSlot SET Price = ? WHERE SlotID = ? AND VenueID = ?");
                $stmtSlot->bind_param("dii", $slotData['price'], $slotID, $venueID);

                if (!$stmtSlot->execute()) {
                    throw new Exception("Error updating time slot: " . $stmtSlot->error);
                }
                $stmtSlot->close();
            }
        }

        $conn->commit();
        echo "<script>alert('Заалны мэдээлэл амжилттай шинэчлэгдлээ!'); window.location.href='venueStaff_edit_venue.php?venue_id=".$venueID."';</script>";
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Алдаа гарлаа: " . $e->getMessage() . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заалны мэдээлэл засах</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tab-container {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            margin-bottom: 20px;
            background-color: #f1f1f1;
            border-radius: 8px 8px 0 0;
            box-shadow: 0 -2px 5px rgba(0,0,0,0.05);
            padding: 5px 5px 0 5px;
        }
        .tab {
            padding: 12px 20px;
            background-color: #e9e9e9;
            border: 1px solid #ccc;
            border-bottom: none;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            margin-right: 5px;
            font-weight: bold;
            color: #555;
            transition: all 0.3s ease;
        }
        .tab:hover {
            background-color: #f5f5f5;
        }
        .tab.active-tab {
            background-color: #fff;
            color: #007bff;
            border-bottom: 2px solid #fff;
            position: relative;
            top: 1px;
        }
        .week-container {
            margin-top: 0;
            background-color: #fff;
            padding: 25px;
            border-radius: 0 0 8px 8px;
            border: 1px solid #ccc;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
            display: none;
        }
        .week-container.active-week {
            display: block;
        }
        .time-slots-table {
            display: grid;
            grid-template-columns: repeat(7, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 20px;
            overflow-x: auto;
        }
        .time-slot-column {
            border: 1px solid #ccc;
            padding: 20px;
            text-align: center;
            background-color: #fff;
            border-radius: 6px;
            min-width: 280px;
        }
        .time-slot-column h5 {
            font-size: 1.2rem;
            color: #007bff;
            margin-bottom: 10px;
        }
        .time-slot {
            margin: 15px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }
        .sport-types-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
            margin-bottom: 20px;
        }
        .sport-type-checkbox {
            display: flex;
            align-items: center;
            background-color: #f1f1f1;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s ease;
            width: auto;
        }
        .sport-type-checkbox:hover {
            background-color: #e6e6e6;
        }
        .sport-type-checkbox input {
            margin-right: 8px;
            cursor: pointer;
            width: auto;
        }
        .sport-type-checkbox.selected {
            background-color: #d4edff;
            border: 1px solid #007bff;
        }
    </style>
</head>

<body class="bg-gray-50">
    
    <div class="max-w-7xl mx-auto my-10 p-6 bg-white rounded-lg shadow-md">
    <div class="flex justify-start mb-4">
    <a href="venue_staff_dashboard.php" class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-400">
        Буцах
    </a>
</div>
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Заалны мэдээлэл засах</h2>
            
            <!-- Venue selector dropdown -->
            <div class="relative">
                <select id="venueSelector" class="block appearance-none bg-white border border-gray-400 hover:border-gray-500 px-4 py-2 pr-8 rounded shadow leading-tight focus:outline-none focus:shadow-outline" onchange="location = 'venueStaff_edit_venue.php?venue_id=' + this.value;">
                    <?php foreach ($venueStaffAssignments as $assignment): ?>
                        <option value="<?= $assignment['VenueID'] ?>" <?= ($assignment['VenueID'] == $venueID) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($assignment['VenueName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                </div>
            </div>
        </div>

        <form action="" method="post" class="space-y-6">
            <!-- Basic venue information inputs -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="name" class="block font-semibold text-gray-700 mb-2">Заалны нэр:</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($venue['Name']) ?>" required class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label for="location" class="block font-semibold text-gray-700 mb-2">Байршил:</label>
                    <input type="text" name="location" value="<?= htmlspecialchars($venue['Location']) ?>" required class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label for="sport_types" class="block font-semibold text-gray-700 mb-2">Спортын төрөл:</label>
                    <div class="sport-types-container">
                        <?php foreach ($validSportTypes as $sport): ?>
                            <label class="sport-type-checkbox <?= in_array($sport, $currentSportTypes) ? 'selected' : '' ?>">
                                <input type="checkbox" name="sport_types[]" value="<?= $sport ?>" 
                                    <?= in_array($sport, $currentSportTypes) ? 'checked' : '' ?>>
                                <?= $sport ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <label for="hourly_price" class="block font-semibold text-gray-700 mb-2">Цагийн үнэ:</label>
                    <input type="number" step="0.01" name="hourly_price" value="<?= htmlspecialchars($venue['HourlyPrice']) ?>" required class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <button type="button" onclick="setDefaultHourlyPrice()" class="w-full px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors">
                Бүх цагийн үнийг энэ үнээр тохируулах
            </button>

            <div>
                <label for="description" class="block font-semibold text-gray-700 mb-2">Тайлбар:</label>
                <textarea name="description" class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 h-32"><?= htmlspecialchars($venue['Description']) ?></textarea>
            </div>

            <div>
                <label for="map_location" class="block font-semibold text-gray-700 mb-2">Газрын зурагны холбоос (URL):</label>
                <input type="text" name="map_location" value="<?= htmlspecialchars($venue['MapLocation']) ?>" class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <h3 class="text-xl font-semibold text-gray-800">Цагийн хуваарь</h3>

            <!-- Horizontal Tabs for Weeks -->
            <div class="tab-container">
                <?php foreach ($weekNumbers as $index => $week): ?>
                    <div class="tab <?= $index === 0 ? 'active-tab' : '' ?>" 
                         onclick="switchWeek(<?= $week ?>)">
                        Долоо хоног <?= $week ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Week Containers -->
            <?php foreach ($organizedSlots as $week => $days): ?>
                <div id="week-<?= $week ?>" class="week-container <?= $week === $weekNumbers[0] ? 'active-week' : '' ?>">
                    <div class="time-slots-table">
                        <?php foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $dayOfWeek): ?>
                            <div class="time-slot-column">
                                <h5><?= $dayOfWeek ?></h5>
                                <?php if (isset($days[$dayOfWeek])): ?>
                                    <?php foreach ($days[$dayOfWeek] as $slot): ?>
                                        <div class='time-slot'>
                                            <input type='text' value='<?= htmlspecialchars($slot['StartTime']) ?>' disabled class="w-full px-2 py-1 border rounded text-sm">
                                            <input type='text' value='<?= htmlspecialchars($slot['EndTime']) ?>' disabled class="w-full px-2 py-1 border rounded text-sm">
                                            <input type='number' step='0.01' name='time_slots[<?= $slot['SlotID'] ?>][price]' value='<?= htmlspecialchars($slot['Price']) ?>' placeholder='Үнэ' class="w-full px-2 py-1 border rounded text-sm">
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-gray-500">Цагийн хуваарь байхгүй</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <input type="submit" value="Хадгалах" class="w-full px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition-colors cursor-pointer">
        </form>
    </div>

    <script>
        function setDefaultHourlyPrice() {
            const defaultPrice = document.querySelector('input[name="hourly_price"]').value;
            const priceInputs = document.querySelectorAll('input[name^="time_slots"][type="number"]');
            priceInputs.forEach(input => {
                input.value = defaultPrice;
            });
        }

        function switchWeek(weekNumber) {
            // Hide all week containers
            const weekContainers = document.querySelectorAll('.week-container');
            weekContainers.forEach(container => {
                container.classList.remove('active-week');
            });
            
            // Show the selected week container
            const selectedWeek = document.getElementById('week-' + weekNumber);
            if (selectedWeek) {
                selectedWeek.classList.add('active-week');
            }
            
            // Update tab styling
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.classList.remove('active-tab');
            });
            
            // Find and activate the clicked tab
            const clickedTab = event.currentTarget;
            clickedTab.classList.add('active-tab');
        }

        // Sport type checkbox styling
        document.querySelectorAll('.sport-type-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const input = this.querySelector('input[type="checkbox"]');
                if (input.checked) {
                    this.classList.add('selected');
                } else {
                    this.classList.remove('selected');
                }
            });
        });
    </script>
</body>
</html>