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

// Спортын төрлүүдийн жагсаалт
$validSportTypes = [
    'Хөлбөмбөг', 'Сагсанбөмбөг', 'Волейбол', 
    'Ширээний теннис', 'Бадминтон', 'Талбайн теннис', 
    'Гольф', 'Бүжиг', 'Иога', 'Билльярд'
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Өгөгдлийн бүрэн бүтэн байдлыг хангахын тулд транзакц эхлүүлэх
    $conn->begin_transaction();
    try {
        // Спортын төрлүүдийг боловсруулах - олон төрөл
        $sportTypes = [];
        if (isset($_POST['sport_type']) && is_array($_POST['sport_type'])) {
            // Сонгосон спортын төрлүүдийг шүүх ба баталгаажуулах
            $sportTypes = array_intersect($_POST['sport_type'], $validSportTypes);
            
            // Хэрэв хүчинтэй спортын төрөл байхгүй бол алдаа гаргах
            if (empty($sportTypes)) {
                throw new Exception("Хүчинтэй спортын төрөл сонгогдоогүй байна");
            }
        } else {
            throw new Exception("Дор хаяж нэг спортын төрөл сонгох ёстой");
        }

        // Спортын төрлүүдийг таслалаар тусгаарласан тэмдэгт мөр болгох
        $sportTypeString = implode(',', $sportTypes);

        // Утгуудыг шалгах
        $name = trim($_POST['name']);
        $location = trim($_POST['location']);
        $hourlyPrice = floatval($_POST['hourly_price']);
        $description = trim($_POST['description'] ?? '');
        $mapLocation = trim($_POST['map_location'] ?? '');

        // Venue хүснэгт дээр мэдээлэл оруулах
        $stmtVenue = $conn->prepare("INSERT INTO Venue (ManagerID, Name, Location, SportType, HourlyPrice, Description, MapLocation) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmtVenue) {
            throw new Exception("Бэлтгэл алдаатай: " . $conn->error);
        }
        
        $bindResult = $stmtVenue->bind_param("isssdss", 
            $managerID, 
            $name, 
            $location, 
            $sportTypeString, 
            $hourlyPrice, 
            $description, 
            $mapLocation
        );
        
        if (!$bindResult) {
            throw new Exception("Параметр холбоход алдаа гарлаа: " . $stmtVenue->error);
        }
        
        $executeResult = $stmtVenue->execute();
        if (!$executeResult) {
            throw new Exception("Гүйцэтгэх үед алдаа гарлаа: " . $stmtVenue->error);
        }
        
        // Сүүлд оруулсан VenueID-г авах
        $venueID = $conn->insert_id;
        $stmtVenue->close();

        // Олон спортын төрлүүдийг салангид хүснэгтэд оруулах
        $stmtSports = $conn->prepare("INSERT INTO VenueSports (VenueID, SportType) VALUES (?, ?)");
        
        foreach ($sportTypes as $sport) {
            $stmtSports->bind_param("is", $venueID, $sport);
            if (!$stmtSports->execute()) {
                throw new Exception("Спортын төрөл оруулах үед алдаа гарлаа: " . $stmtSports->error);
            }
        }
        $stmtSports->close();

        // Цагийн хуваарь оруулах (хялбаршуулсан)
        if (!empty($_POST['time_slots'])) {
            $stmtTimeSlot = $conn->prepare("INSERT INTO VenueTimeSlot (VenueID, Week, DayOfWeek, StartTime, EndTime, Status, Price) VALUES (?, ?, ?, ?, ?, 'Available', ?)");
            
            foreach ($_POST['time_slots'] as $week => $days) {
                foreach ($days as $dayOfWeek => $hours) {
                    foreach ($hours as $hour => $slot) {
                        $startTime = sprintf('%02d:00:00', $hour);
                        $endTime = sprintf('%02d:00:00', ($hour + 1) % 24);
                        $price = floatval($slot['price']);
                        
                        $stmtTimeSlot->bind_param("iisssd", $venueID, $week, $dayOfWeek, $startTime, $endTime, $price);
                        
                        if (!$stmtTimeSlot->execute()) {
                            throw new Exception("Цагийн хуваарь нэмэх үед алдаа гарлаа: " . $stmtTimeSlot->error);
                        }
                    }
                }
            }
            $stmtTimeSlot->close();
        }

        // Транзакцийг баталгаажуулах
        $conn->commit();
        
        // Амжилттай мессежтэйгээр шилжүүлэх
        $_SESSION['success_message'] = "Заал амжилттай нэмэгдлээ!";
        header('Location: manager_venues.php');
        exit();

    } catch (Exception $e) {
        // Алдаа гарвал транзакцийг буцаах
        $conn->rollback();
        
        // Алдааны мессежийг хадгалах
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: add_venue.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Шинэ заал нэмэх</title>
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
            <h1 class="text-3xl font-bold text-gray-900">Шинэ заал нэмэх</h1>
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
            <form action="add_venue.php" method="post" enctype="multipart/form-data" class="p-6">
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">Заалны үндсэн мэдээлэл</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Заалны нэр:</label>
                            <input type="text" name="name" id="name" required class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md p-2 border">
                        </div>

                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700 mb-1">Байршил:</label>
                            <input type="text" name="location" id="location" required class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md p-2 border">
                        </div>

                        <div>
                            <label for="hourly_price" class="block text-sm font-medium text-gray-700 mb-1">Цагийн үнэ:</label>
                            <input type="number" step="0.01" name="hourly_price" id="default-hourly-price" required class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md p-2 border">
                        </div>

                        <div>
                            <label for="map_location" class="block text-sm font-medium text-gray-700 mb-1">Газрын зураг холбоос (URL):</label>
                            <input type="text" name="map_location" id="map_location" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md p-2 border">
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Дэлгэрэнгүй мэдээлэл:</label>
                        <textarea name="description" id="description" rows="4" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md p-2 border"></textarea>
                    </div>
                    
                    <div class="mt-6">
                        <span class="block text-sm font-medium text-gray-700 mb-3">Спортын төрлүүд:</span>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                            <?php foreach ($validSportTypes as $sport): ?>
                                <label class="inline-flex items-center p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="checkbox" name="sport_type[]" value="<?php echo $sport; ?>" class="h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
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
                            <button type="button" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors active-tab" 
                                    onclick="switchWeek(1)" 
                                    data-week="1">
                                1-р долоо хоног
                            </button>
                        </div>
                        <button type="button" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition-colors" 
                                onclick="addWeek()">
                            <i class="fas fa-plus mr-1"></i> Долоо хоног нэмэх
                        </button>
                    </div>
                    
                    <!-- Долоо хоногийн хуваарь -->
                    <div id="weeks-container" class="space-y-4">
                        <div class="week bg-gray-50 p-6 rounded-lg block" data-week="1">
                            <div class="flex items-center justify-between mb-4">
                                <h4 id="week-1-label" class="text-lg font-semibold"></h4>
                                <button type="button" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors" onclick="removeWeek(1)">
                                    <i class="fas fa-trash-alt mr-1"></i> Долоо хоног устгах
                                </button>
                            </div>
                            <div class="relative">
                                <div class="time-slots-table flex overflow-x-auto pb-4" id="week-1-slots">
                                    <!-- JavaScript-с цагийн хуваарь нэмнэ -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-5 border-t border-gray-200">
                    <div class="flex justify-end">
                        <a href="manager_venues.php" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-3">
                            Цуцлах
                        </a>
                        <button type="submit" class="py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-save mr-2"></i> Заал нэмэх
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        const daysOfWeek = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
        const daysOfWeekMongolian = ["Ням", "Даваа", "Мягмар", "Лхагва", "Пүрэв", "Баасан", "Бямба"];
        let weekIndex = 2;

        function initializeWeek(weekNumber, startDate) {
            const weekContainer = document.querySelector(`#week-${weekNumber}-slots`);
            const weekLabelContainer = document.querySelector(`#week-${weekNumber}-label`);

            const weekStartDate = new Date(startDate);
            const weekEndDate = new Date(startDate);
            weekEndDate.setDate(weekStartDate.getDate() + 6);
            const weekLabel = `${weekStartDate.toLocaleDateString('mn-MN')} - ${weekEndDate.toLocaleDateString('mn-MN')}`;
            weekLabelContainer.textContent = `${weekNumber}-р долоо хоног: ${weekLabel}`;

            const startDayIndex = weekStartDate.getDay();
            const orderedDaysOfWeek = [...daysOfWeek.slice(startDayIndex), ...daysOfWeek.slice(0, startDayIndex)];
            const orderedDaysOfWeekMongolian = [...daysOfWeekMongolian.slice(startDayIndex), ...daysOfWeekMongolian.slice(0, startDayIndex)];

            weekContainer.innerHTML = orderedDaysOfWeek.map((day, index) => `
                <div class='flex-none w-60 bg-white p-4 rounded-lg shadow-sm mr-4'>
                    <h4 class='font-semibold text-center mb-4 text-gray-800'>${orderedDaysOfWeekMongolian[index]}</h4>
                    <div class='space-y-2'>
                        ${Array.from({ length: 24 }, (_, hour) => {
                            if (hour >= 8 && hour < 24) {
                                return `
                                    <div class='p-2 border border-gray-200 rounded-md' id='time-slot-${weekNumber}-${index}-${hour}'>
                                        <div class='text-sm text-gray-600 mb-2'>${String(hour).padStart(2, '0')}:00 - ${String((hour + 1) % 24).padStart(2, '0')}:00</div>
                                        <div class='flex items-center gap-2'>
                                            <input type="number" step="0.01" class="w-full px-2 py-1 border rounded text-sm" name="time_slots[${weekNumber}][${day}][${hour}][price]" placeholder="Үнэ" required>
                                            <button type='button' class='flex-none p-1 bg-red-500 text-white text-sm rounded hover:bg-red-600 transition-colors' onclick='removePriceInput(${weekNumber}, "${day}", ${index}, ${hour})'>
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                `;
                            } else {
                                return `
                                    <div class='p-2 border border-gray-200 rounded-md' id='time-slot-${weekNumber}-${index}-${hour}'>
                                        <div class='text-sm text-gray-600 mb-2'>${String(hour).padStart(2, '0')}:00 - ${String((hour + 1) % 24).padStart(2, '0')}:00</div>
                                        <button type='button' class='w-full px-2 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600 transition-colors' onclick='addPriceInput(${weekNumber}, "${day}", ${index}, ${hour})'>
                                            <i class="fas fa-plus mr-1"></i> Цаг нэмэх
                                        </button>
                                    </div>
                                `;
                            }
                        }).join('')}
                    </div>
                </div>
            `).join('');
        }

        function setDefaultHourlyPrice() {
            const defaultPrice = document.getElementById("default-hourly-price").value;
            if (defaultPrice === "" || isNaN(defaultPrice)) {
                alert("Эхлээд цагийн үндсэн үнийг оруулна уу!");
                return;
            }
            document.querySelectorAll('input[type="number"][name*="time_slots"]').forEach(input => input.value = defaultPrice);
            
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

        function addWeek() {
            const weeksContainer = document.getElementById('weeks-container');
            const weekTabs = document.getElementById('week-tabs');
            
            // Шинэ долоо хоногийн таб үүсгэх
            const newTab = document.createElement('button');
            newTab.setAttribute('type', 'button');
            newTab.classList.add('px-4', 'py-2', 'bg-gray-300', 'text-gray-700', 'rounded-md', 'hover:bg-gray-400', 'transition-colors');
            newTab.setAttribute('onclick', `switchWeek(${weekIndex})`);
            newTab.setAttribute('data-week', weekIndex);
            newTab.textContent = `${weekIndex}-р долоо хоног`;
            weekTabs.appendChild(newTab);
            
            // Шинэ долоо хоногийн агуулга үүсгэх
            const newWeek = document.createElement('div');
            newWeek.classList.add('week', 'bg-gray-50', 'p-6', 'rounded-lg', 'hidden');
            newWeek.setAttribute('data-week', weekIndex);
            newWeek.innerHTML = `
                <div class="flex items-center justify-between mb-4">
                    <h4 id="week-${weekIndex}-label" class="text-lg font-semibold"></h4>
                    <button type="button" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors" onclick="removeWeek(${weekIndex})">
                        <i class="fas fa-trash-alt mr-1"></i> Долоо хоног устгах
                    </button>
                </div>
                <div class="relative">
                    <div class="time-slots-table flex overflow-x-auto pb-4" id="week-${weekIndex}-slots"></div>
                </div>
            `;

            weeksContainer.appendChild(newWeek);

            const lastWeekEndDate = new Date();
            lastWeekEndDate.setDate(lastWeekEndDate.getDate() + (weekIndex - 1) * 7);
            initializeWeek(weekIndex, lastWeekEndDate);
            
            // Шинээр үүсгэсэн долоо хоног руу шилжих
            switchWeek(weekIndex);
            
            weekIndex++;
        }

        function switchWeek(weekNumber) {
            // Бүх долоо хоногийг нуух
            document.querySelectorAll('.week').forEach(week => {
                week.classList.add('hidden');
            });
            
            // Сонгосон долоо хоногийг харуулах
            const selectedWeek = document.querySelector(`.week[data-week="${weekNumber}"]`);
            if (selectedWeek) {
                selectedWeek.classList.remove('hidden');
            }
            
            // Табуудын загварыг шинэчлэх
            document.querySelectorAll('#week-tabs button').forEach(tab => {
                tab.classList.remove('bg-blue-500', 'text-white', 'active-tab');
                tab.classList.add('bg-gray-300', 'text-gray-700');
            });
            
            const activeTab = document.querySelector(`#week-tabs button[data-week="${weekNumber}"]`);
            if (activeTab) {
                activeTab.classList.remove('bg-gray-300', 'text-gray-700');
                activeTab.classList.add('bg-blue-500', 'text-white', 'active-tab');
            }
        }

        function addPriceInput(week, day, columnIndex, hour) {
            const slotContainer = document.getElementById(`time-slot-${week}-${columnIndex}-${hour}`);
            if (!slotContainer) return;

            slotContainer.innerHTML = `
                <div class='text-sm text-gray-600 mb-2'>${String(hour).padStart(2, '0')}:00 - ${String((hour + 1) % 24).padStart(2, '0')}:00</div>
                <div class='flex items-center gap-2'>
                    <input type="number" step="0.01" class="w-full px-2 py-1 border rounded text-sm" name="time_slots[${week}][${day}][${hour}][price]" placeholder="Үнэ" required>
                    <button type='button' class='flex-none p-1 bg-red-500 text-white text-sm rounded hover:bg-red-600 transition-colors' onclick='removePriceInput(${week}, "${day}", ${columnIndex}, ${hour})'>
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;

            // Шинээр нэмсэн утга оруулах хэсэгт үндсэн үнийг тохируулах
            const defaultPrice = document.getElementById("default-hourly-price").value;
            if (defaultPrice && !isNaN(defaultPrice)) {
                const newInput = slotContainer.querySelector('input[type="number"]');
                if (newInput) newInput.value = defaultPrice;
            }
        }

        function removePriceInput(week, day, columnIndex, hour) {
            const slotContainer = document.getElementById(`time-slot-${week}-${columnIndex}-${hour}`);
            if (!slotContainer) return;

            slotContainer.innerHTML = `
                <div class='text-sm text-gray-600 mb-2'>${String(hour).padStart(2, '0')}:00 - ${String((hour + 1) % 24).padStart(2, '0')}:00</div>
                <button type='button' class='w-full px-2 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600 transition-colors' onclick='addPriceInput(${week}, "${day}", ${columnIndex}, ${hour})'>
                    <i class="fas fa-plus mr-1"></i> Цаг нэмэх
                </button>
            `;
        }

        function removeWeek(weekNumber) {
            // Нэг долоо хоног байх ёстой учир устгахыг зөвшөөрөхгүй
            const weekCount = document.querySelectorAll('.week').length;
            if (weekCount <= 1) {
                alert("Дор хаяж нэг долоо хоног байх ёстой.");
                return;
            }
            
            // Долоо хоногийн агуулга устгах
            const weekElement = document.querySelector(`.week[data-week='${weekNumber}']`);
            if (weekElement) {
                weekElement.remove();
            }
            
            // Долоо хоногийн табыг устгах
            const tabElement = document.querySelector(`#week-tabs button[data-week='${weekNumber}']`);
            if (tabElement) {
                tabElement.remove();
            }
            
            // Хэрэв идэвхтэй долоо хоногийг устгасан бол эхний долоо хоног руу шилжих
            const activeTab = document.querySelector('#week-tabs button.active-tab');
            if (!activeTab) {
                const firstTab = document.querySelector('#week-tabs button');
                if (firstTab) {
                    const firstWeekNumber = firstTab.getAttribute('data-week');
                    switchWeek(firstWeekNumber);
                }
            }
        }

       // Эхний долоо хоногийг ачаалах
       document.addEventListener('DOMContentLoaded', () => {
            const today = new Date();
            initializeWeek(1, today);

            // Форм илгээхээс өмнө шалгах
            document.querySelector('form').addEventListener('submit', function(e) {
                // Заавал шаардлагатай талбаруудыг шалгах
                const name = document.getElementById('name').value.trim();
                const location = document.getElementById('location').value.trim();
                const hourlyPrice = document.getElementById('default-hourly-price').value.trim();
                
                if (!name || !location || !hourlyPrice) {
                    e.preventDefault();
                    alert('Заалны нэр, байршил, цагийн үнийг заавал оруулна уу!');
                    return;
                }
                
                // Дор хаяж нэг спортын төрөл сонгосон эсэхийг шалгах
                const sportTypes = document.querySelectorAll('input[name="sport_type[]"]:checked');
                if (sportTypes.length === 0) {
                    e.preventDefault();
                    alert('Дор хаяж нэг спортын төрөл сонгоно уу!');
                    return;
                }
                
                // Цагийн хуваарь байгаа эсэхийг шалгах
                const timeSlots = document.querySelectorAll('input[name^="time_slots"][name$="[price]"]');
                if (timeSlots.length === 0) {
                    e.preventDefault();
                    alert('Дор хаяж нэг цагийн хуваарь тохируулна уу!');
                    return;
                }
            });
        });
    </script>
</body>
</html>