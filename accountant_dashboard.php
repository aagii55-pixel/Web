<?php
session_start();
require_once 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Check for all roles the user has (both accountant and venue staff)
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

// Get all venue assignments for the user (both as accountant and venue staff)
$assignmentsQuery = "SELECT vsa.*, v.Name as VenueName, u.Name as ManagerName, vsa.Role
                    FROM venuestaffassignment vsa 
                    JOIN venue v ON vsa.VenueID = v.VenueID 
                    JOIN user u ON vsa.ManagerID = u.UserID 
                    WHERE vsa.UserID = ?
                    ORDER BY vsa.Role, v.Name";
$stmt = $conn->prepare($assignmentsQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$allAssignments = $stmt->get_result();

if ($allAssignments->num_rows == 0) {
    // No assignments
    header("Location: index.php?error=no_assignments");
    exit;
}

// Organize assignments by role
$assignmentsByRole = [];
while ($row = $allAssignments->fetch_assoc()) {
    $assignmentsByRole[$row['Role']][] = $row;
}

// If user has no accountant role, but has venuestaff role, redirect to venuestaff page
if (!isset($assignmentsByRole['Accountant']) && isset($assignmentsByRole['VenueStaff'])) {
    header("Location: venue_staff_dashboard.php");
    exit;
}

// Get accountant assignments
$accountantAssignments = isset($assignmentsByRole['Accountant']) ? $assignmentsByRole['Accountant'] : [];

// Get venue information for the accountant
$currentVenueId = isset($_GET['venue_id']) ? intval($_GET['venue_id']) : 
                 (count($accountantAssignments) > 0 ? $accountantAssignments[0]['VenueID'] : 0);
$venueValid = false;
$currentVenue = null;

foreach ($accountantAssignments as $assignment) {
    if ($assignment['VenueID'] == $currentVenueId) {
        $venueValid = true;
        $currentVenue = $assignment;
        break;
    }
}

if (!$venueValid && count($accountantAssignments) > 0) {
    header("Location: accountant.php");
    exit;
}

// Get date range for report
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date("Y-m-01"); // First day of current month
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date("Y-m-t"); // Last day of current month

// Get payment information for the venue within the date range
$paymentQuery = "SELECT p.*, b.VenueID, b.UserID, b.BookingDate, b.SlotID, u.Name as UserName
                 FROM payment p
                 JOIN booking b ON p.BookingID = b.BookingID
                 JOIN user u ON b.UserID = u.UserID
                 WHERE b.VenueID = ? AND p.PaymentDate BETWEEN ? AND ?
                 ORDER BY p.PaymentDate DESC";
$stmt = $conn->prepare($paymentQuery);
$stmt->bind_param("iss", $currentVenueId, $startDate, $endDate);
$stmt->execute();
$payments = $stmt->get_result();

// Calculate summary statistics
$totalPaid = 0;
$totalPending = 0;
$paymentCount = 0;

if ($payments->num_rows > 0) {
    // Save the result set
    $paymentsData = [];
    while ($payment = $payments->fetch_assoc()) {
        $paymentsData[] = $payment;
        if ($payment['Status'] == 'Paid') {
            $totalPaid += $payment['Amount'];
        } else {
            $totalPending += $payment['Amount'];
        }
        $paymentCount++;
    }
    // Reset the result pointer
    $payments->data_seek(0);
} else {
    $paymentsData = [];
}

// Get bookings without payments
$unpaidQuery = "SELECT b.*, u.Name as UserName, u.Phone as UserPhone, ts.StartTime, ts.EndTime
                FROM booking b
                JOIN user u ON b.UserID = u.UserID
                JOIN venuetimeslot ts ON b.SlotID = ts.SlotID
                LEFT JOIN payment p ON b.BookingID = p.BookingID
                WHERE b.VenueID = ? AND p.PaymentID IS NULL AND b.Status = 'Confirmed'
                ORDER BY b.BookingDate DESC";
$stmt = $conn->prepare($unpaidQuery);
$stmt->bind_param("i", $currentVenueId);
$stmt->execute();
$unpaidBookings = $stmt->get_result();

// Get daily revenue data for chart
$dailyRevenueQuery = "SELECT p.PaymentDate, SUM(p.Amount) as DailyTotal
                      FROM payment p
                      JOIN booking b ON p.BookingID = b.BookingID
                      WHERE b.VenueID = ? AND p.Status = 'Paid' AND p.PaymentDate BETWEEN ? AND ?
                      GROUP BY p.PaymentDate
                      ORDER BY p.PaymentDate";
$stmt = $conn->prepare($dailyRevenueQuery);
$stmt->bind_param("iss", $currentVenueId, $startDate, $endDate);
$stmt->execute();
$dailyRevenue = $stmt->get_result();

$chartLabels = [];
$chartData = [];

while ($day = $dailyRevenue->fetch_assoc()) {
    $chartLabels[] = date("M d", strtotime($day['PaymentDate']));
    $chartData[] = $day['DailyTotal'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accountant Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-container {
            padding: 20px;
        }
        .venue-selector {
            margin-bottom: 20px;
        }
        .stats-card {
            margin-bottom: 15px;
            text-align: center;
        }
        .date-filter {
            margin-bottom: 20px;
        }
        .nav-tabs {
            margin-bottom: 20px;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 30px;
        }
        .role-badge {
            font-size: 0.8rem;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <a class="navbar-brand" href="index.php">Venue Booking System</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home</a>
                </li>
                <?php if (in_array('Accountant', $userRoles)): ?>
                <li class="nav-item active">
                    <a class="nav-link" href="accountant.php">Accounting <span class="sr-only">(current)</span></a>
                </li>
                <?php endif; ?>
                <?php if (in_array('VenueStaff', $userRoles)): ?>
                <li class="nav-item">
                    <a class="nav-link" href="venue_staff_dashboard.php">Venue Staff</a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="venues.php">Venues</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">Bookings</a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-user"></i> <?= isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Account' ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="profile.php">Profile</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="logout.php">Logout</a>
                    </div>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container dashboard-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Accountant Dashboard</h1>
            
            <?php if (in_array('VenueStaff', $userRoles)): ?>
            <a href="venue_staff_dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-exchange-alt"></i> Switch to Venue Staff
            </a>
            <?php endif; ?>
        </div>
      
        <?php if (count($accountantAssignments) > 1): ?>
        <div class="venue-selector">
            <form action="" method="get">
                <div class="form-group">
                    <label for="venue_id">Select Venue:</label>
                    <select class="form-control" id="venue_id" name="venue_id" onchange="this.form.submit()">
                        <?php foreach ($accountantAssignments as $assignment): ?>
                            <option value="<?= $assignment['VenueID'] ?>" <?= ($assignment['VenueID'] == $currentVenueId) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($assignment['VenueName']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if (isset($_GET['start_date']) && isset($_GET['end_date'])): ?>
                    <input type="hidden" name="start_date" value="<?= htmlspecialchars($_GET['start_date']) ?>">
                    <input type="hidden" name="end_date" value="<?= htmlspecialchars($_GET['end_date']) ?>">
                <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>

        <div class="alert alert-info">
            <p><strong>Current Venue:</strong> <?= htmlspecialchars($currentVenue['VenueName']) ?></p>
            <p><strong>Manager:</strong> <?= htmlspecialchars($currentVenue['ManagerName']) ?></p>
            <p><strong>Your Role:</strong> 
                <span class="badge badge-primary">Accountant</span>
                <?php if (in_array('VenueStaff', $userRoles)): ?>
                <span class="badge badge-secondary">Venue Staff</span>
                <?php endif; ?>
            </p>
        </div>

        <div class="date-filter">
            <form action="" method="get" class="form-inline">
                <?php if (isset($_GET['venue_id'])): ?>
                    <input type="hidden" name="venue_id" value="<?= intval($_GET['venue_id']) ?>">
                <?php endif; ?>
                
                <div class="form-group mr-2">
                    <label for="start_date" class="mr-2">Start Date:</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                
                <div class="form-group mr-2">
                    <label for="end_date" class="mr-2">End Date:</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                
                <button type="submit" class="btn btn-primary">Apply Filter</button>
            </form>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Revenue (Paid)</h5>
                        <h2 class="card-text"><?= number_format($totalPaid, 2) ?></h2>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card stats-card bg-warning text-dark">
                    <div class="card-body">
                        <h5 class="card-title">Pending Payments</h5>
                        <h2 class="card-text"><?= number_format($totalPending, 2) ?></h2>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card stats-card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Bookings</h5>
                        <h2 class="card-text"><?= $paymentCount ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="chart-container">
            <canvas id="revenueChart"></canvas>
        </div>

        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="payments-tab" data-toggle="tab" href="#payments" role="tab" aria-controls="payments" aria-selected="true">Payments</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="unpaid-tab" data-toggle="tab" href="#unpaid" role="tab" aria-controls="unpaid" aria-selected="false">Unpaid Bookings</a>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            <!-- Payments Tab -->
            <div class="tab-pane fade show active" id="payments" role="tabpanel" aria-labelledby="payments-tab">
                <h3>Payment Records (<?= date("M d, Y", strtotime($startDate)) ?> - <?= date("M d, Y", strtotime($endDate)) ?>)</h3>
                
                <?php if (count($paymentsData) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Payment ID</th>
                                    <th>Customer</th>
                                    <th>Booking Date</th>
                                    <th>Payment Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paymentsData as $payment): ?>
                                    <tr>
                                        <td><?= $payment['PaymentID'] ?></td>
                                        <td><?= htmlspecialchars($payment['UserName']) ?></td>
                                        <td><?= date("M d, Y", strtotime($payment['BookingDate'])) ?></td>
                                        <td><?= date("M d, Y", strtotime($payment['PaymentDate'])) ?></td>
                                        <td><?= number_format($payment['Amount'], 2) ?></td>
                                        <td>
                                            <span class="badge <?= ($payment['Status'] == 'Paid') ? 'badge-success' : 'badge-warning' ?>">
                                                <?= $payment['Status'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($payment['Status'] == 'Pending'): ?>
                                                <a href="mark_payment_paid.php?id=<?= $payment['PaymentID'] ?>&redirect=accountant" class="btn btn-sm btn-success">Mark as Paid</a>
                                            <?php else: ?>
                                                <a href="view_payment.php?id=<?= $payment['PaymentID'] ?>" class="btn btn-sm btn-info">View Details</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No payment records found for the selected period.</div>
                <?php endif; ?>
            </div>

            <!-- Unpaid Bookings Tab -->
            <div class="tab-pane fade" id="unpaid" role="tabpanel" aria-labelledby="unpaid-tab">
                <h3>Unpaid Confirmed Bookings</h3>
                
                <?php if ($unpaidBookings->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Customer</th>
                                    <th>Phone</th>
                                    <th>Booking Date</th>
                                    <th>Time Slot</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($booking = $unpaidBookings->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $booking['BookingID'] ?></td>
                                        <td><?= htmlspecialchars($booking['UserName']) ?></td>
                                        <td><?= htmlspecialchars($booking['UserPhone']) ?></td>
                                        <td><?= date("M d, Y", strtotime($booking['BookingDate'])) ?></td>
                                        <td><?= date("h:i A", strtotime($booking['StartTime'])) ?> - <?= date("h:i A", strtotime($booking['EndTime'])) ?></td>
                                        <td>
                                            <span class="badge badge-warning">Unpaid</span>
                                        </td>
                                        <td>
                                            <a href="create_payment.php?booking_id=<?= $booking['BookingID'] ?>" class="btn btn-sm btn-primary">Record Payment</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No unpaid bookings found.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-4">
            <a href="accounting_report.php?venue_id=<?= $currentVenueId ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="btn btn-primary">
                <i class="fas fa-file-export"></i> Export Report
            </a>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Revenue Chart
        var ctx = document.getElementById('revenueChart').getContext('2d');
        var revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Daily Revenue',
                    data: <?= json_encode($chartData) ?>,
                    backgroundColor: 'rgba(40, 167, 69, 0.2)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    pointBackgroundColor: 'rgba(40, 167, 69, 1)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Daily Revenue (<?= date("M d", strtotime($startDate)) ?> - <?= date("M d", strtotime($endDate)) ?>)',
                        font: {
                            size: 16
                        }
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>