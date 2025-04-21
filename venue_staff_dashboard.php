<?php
session_start();
require_once 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Check for all roles the user has
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

// Get all venue assignments for the user
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
    header("Location: home.php?error=no_assignments");
    exit;
}

// Organize assignments by role
$assignmentsByRole = [];
while ($row = $allAssignments->fetch_assoc()) {
    $assignmentsByRole[$row['Role']][] = $row;
}

// If user does not have 'VenueStaff' role, redirect to home page
if (!isset($assignmentsByRole['VenueStaff'])) {
    header("Location: login.php?error=not_venue_staff");
    exit;
}

// Get venue assignments for Venue Staff
$venueStaffAssignments = $assignmentsByRole['VenueStaff'];

// Get venue information for venue staff
$currentVenueId = isset($_GET['venue_id']) ? intval($_GET['venue_id']) : 
                 (count($venueStaffAssignments) > 0 ? $venueStaffAssignments[0]['VenueID'] : 0);
$venueValid = false;
$currentVenue = null;

foreach ($venueStaffAssignments as $assignment) {
    if ($assignment['VenueID'] == $currentVenueId) {
        $venueValid = true;
        $currentVenue = $assignment;
        break;
    }
}

if (!$venueValid && count($venueStaffAssignments) > 0) {
    header("Location: venue_staff.php");
    exit;
}

// Get date range for report
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date("Y-m-01"); // First day of current month
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date("Y-m-t"); // Last day of current month

// Get booking information for the venue staff within the date range
$bookingQuery = "SELECT b.*, u.Name as UserName, ts.StartTime, ts.EndTime
                 FROM booking b
                 JOIN user u ON b.UserID = u.UserID
                 JOIN venuetimeslot ts ON b.SlotID = ts.SlotID
                 WHERE b.VenueID = ? AND b.Status = 'Confirmed' AND b.BookingDate BETWEEN ? AND ?
                 ORDER BY b.BookingDate DESC";
$stmt = $conn->prepare($bookingQuery);
$stmt->bind_param("iss", $currentVenueId, $startDate, $endDate);
$stmt->execute();
$bookings = $stmt->get_result();

// Get revenue information
$revenueQuery = "SELECT SUM(p.Amount) as TotalRevenue
                 FROM payment p
                 JOIN booking b ON p.BookingID = b.BookingID
                 WHERE b.VenueID = ? AND p.Status = 'Paid' AND p.PaymentDate BETWEEN ? AND ?";
$stmt = $conn->prepare($revenueQuery);
$stmt->bind_param("iss", $currentVenueId, $startDate, $endDate);
$stmt->execute();
$revenueResult = $stmt->get_result();
$totalRevenue = $revenueResult->num_rows > 0 ? $revenueResult->fetch_assoc()['TotalRevenue'] : 0;

// Calculate utilization rate (if needed)
$utilizationQuery = "SELECT COUNT(DISTINCT b.SlotID) as BookedSlots,
                     (SELECT COUNT(*) FROM venuetimeslot WHERE VenueID = ?) as TotalSlots
                     FROM booking b
                     WHERE b.VenueID = ? AND b.Status = 'Confirmed' AND b.BookingDate BETWEEN ? AND ?";
$stmt = $conn->prepare($utilizationQuery);
$stmt->bind_param("iiss", $currentVenueId, $currentVenueId, $startDate, $endDate);
$stmt->execute();
$utilizationResult = $stmt->get_result();
$utilizationData = $utilizationResult->fetch_assoc();
$utilizationRate = $utilizationData['TotalSlots'] > 0 ? 
                  ($utilizationData['BookedSlots'] / $utilizationData['TotalSlots']) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Venue Staff Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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
        .role-badge {
            font-size: 0.8rem;
            margin-left: 5px;
        }
        .stats-card .card-text {
            font-size: 1.8rem;
            font-weight: bold;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .user-roles {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <a class="navbar-brand" href="home.php">Venue Booking System</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item">
                    <a class="nav-link" href="home.php">Home</a>
                </li>
                <?php if (in_array('VenueStaff', $userRoles)): ?>
                <li class="nav-item active">
                    <a class="nav-link" href="venue_staff.php">Venue Staff <span class="sr-only">(current)</span></a>
                </li>
                <?php endif; ?>
                <?php if (in_array('Accountant', $userRoles)): ?>
                <li class="nav-item">
                    <a class="nav-link" href="accountant_dashboard.php">Accountant</a>
                </li>
                <?php endif; ?>
                <?php if (in_array('Manager', $userRoles)): ?>
                <li class="nav-item">
                    <a class="nav-link" href="manager.php">Manager</a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="venueStaff_edit_venue.php">Venues</a>
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
                        <a class="dropdown-item" href="edit_profile.php">Profile</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="logout.php">Logout</a>
                    </div>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container dashboard-container">
        <h1>Venue Staff Dashboard</h1>
        
       

        <?php if (count($venueStaffAssignments) > 1): ?>
        <div class="venue-selector">
            <form action="" method="get" id="venueForm">
                <div class="form-group">
                    <label for="venue_id">Select Venue:</label>
                    <select class="form-control" id="venue_id" name="venue_id" onchange="this.form.submit()">
                        <?php foreach ($venueStaffAssignments as $assignment): ?>
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

        <div class="card mb-4">
            <div class="card-header">
                <h5>Date Range Filter</h5>
            </div>
            <div class="card-body">
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
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-calendar-check mr-2"></i>Total Bookings</h5>
                        <p class="card-text"><?= $bookings->num_rows ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-money-bill-wave mr-2"></i>Total Revenue</h5>
                        <p class="card-text">₮<?= number_format($totalRevenue, 2) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-chart-pie mr-2"></i>Utilization Rate</h5>
                        <p class="card-text"><?= number_format($utilizationRate, 1) ?>%</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h2><i class="fas fa-list-alt mr-2"></i>Bookings</h2>
            </div>
            <div class="card-body">
                <?php if ($bookings->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Booking ID</th>
                                    <th>User Name</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Booking Date</th>
                                    <th>Duration (hrs)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($booking = $bookings->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $booking['BookingID'] ?></td>
                                        <td><?= htmlspecialchars($booking['UserName']) ?></td>
                                        <td><?= $booking['StartTime'] ?></td>
                                        <td><?= $booking['EndTime'] ?></td>
                                        <td><?= date('M d, Y', strtotime($booking['BookingDate'])) ?></td>
                                        <td><?= $booking['Duration'] ?></td>
                                        <td>
                                            <a href="view_booking.php?id=<?= $booking['BookingID'] ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        No bookings found for the selected date range.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validate date range
        document.addEventListener('DOMContentLoaded', function() {
            const dateForm = document.querySelector('.card form');
            dateForm.addEventListener('submit', function(e) {
                const startDate = new Date(document.getElementById('start_date').value);
                const endDate = new Date(document.getElementById('end_date').value);
                
                if (endDate < startDate) {
                    e.preventDefault();
                    alert('End date must be after start date');
                }
            });
        });
    </script>
</body>
</html>