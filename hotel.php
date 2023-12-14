<?php
if (!isset($_COOKIE['hotelcookie'])) {
    $defaultCookieValue = 'graph';
    setcookie('hotelcookie', $defaultCookieValue, time() + 3600, '/'); 
} else {
    $defaultCookieValue = $_COOKIE['hotelcookie'];
}
include('connect.php');
require_once 'autoload.php';
use MongoDB\Client;

// Connect to MongoDB
$client = new Client();
$bookings = $client->pdds->bookings;

$error = '';
$resultsFound = false;
$bookingsByTime = [];

if (isset($_POST['buttonsStatus'])) {
    $buttonStatus = $_POST["buttonsStatus"];

    if ($buttonStatus === 'graph') {
        $cookieValue = 'graph';
    } elseif ($buttonStatus == 'table') {
        $cookieValue = 'table';
    } else {
        $cookieValue = 'graph';
    }
    $results = [];

    setcookie('hotelcookie', $cookieValue, time() + 3600, '/');
    echo '<script type="text/javascript">window.location.href = window.location.href;</script>';
}


if (isset($_POST['hotel']) && $_POST['hotel'] !== "") {
    $hotel = $_POST['hotel'];
    if (!empty($hotel)) {
        // Assuming your table has a column named "negara" for the country
        $query = "SELECT id FROM hotels WHERE nama = ?";
        
        // Use prepared statements to prevent SQL injection
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $hotel);
        
        // Execute the query
        $stmt->execute();
        
        // Bind the result variable
        $stmt->bind_result($hotelId);
        
        // Fetch results into an array
        while ($stmt->fetch()) {
            $hotelIds[] = $hotelId;
        }
        
        // Close the statement
        $stmt->close();
    }
    // Fetch bookings from MongoDB
    $allBookingsFilter = ($hotel !== "all") ? ['id_hotel' => ['$in' => $hotelIds]] : [];
    $allBookings = $bookings->find($allBookingsFilter);


    foreach ($allBookings as $booking) {

        // Determine the time key based on the button pressed
        $timestamp = $booking['tanggal_checkout']->toDateTime()->getTimestamp(); // Convert UTCDateTime to timestamp in seconds
        $date = new DateTime();
        $date->setTimestamp($timestamp);
    
        switch ($_POST['buttonStatus']) {
            case 'month':
                $timeKey = $date->format('Y-m'); // Year and month
                break;
            case 'day':
                $timeKey = $date->format('Y-m-d'); // Year, month, and day
                break;
            default:
                $timeKey = $date->format('Y'); // Default to year
                break;
        }
    
        // Initialize the time key if it doesn't exist
        if (!isset($bookingsByTime[$timeKey])) {
            $bookingsByTime[$timeKey] = ['total_bookings' => 0, 'booking_ids' => []];
        }
    
        // Increment the total bookings and add the booking ID
        $bookingsByTime[$timeKey]['total_bookings']++;
        $bookingsByTime[$timeKey]['booking_ids'][] = $booking['_id'];
    }

    // Fetch sum of "total" from MySQL for each booking ID
    foreach ($bookingsByTime as $time => $data) {
        $bookingIds = implode(", ", $data['booking_ids']);
        $query = "SELECT SUM(total) AS total_sum FROM transactions WHERE id_bookings IN ($bookingIds)";
        $result = $conn->query($query);

        if ($result) {
            $row = $result->fetch_assoc();
            $bookingsByTime[$time]['total_sum'] = $row['total_sum'];
        } else {
            $error = $conn->error;
        }
    }
}

ksort($bookingsByTime);

// Close MySQL connection

// ...
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotels</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div id="sidebar">
    <div id="closeBtn" onclick="closeNav()">✖</div>
    <a href="index.php">Customers</a>
    <a href="hotel.php">Hotels</a>
    <a href="#">Link 3</a>
</div>

<div id="menuToggle" onclick="openNav()">☰</div>

<!-- New content added -->
<div id="content">
    <div id="mainText">Data Hotel</div>
    <br>
    <div class="centeredFormContainers">
    <form class = "formbutton" methos = "POST">
        <button class="button button1" id="button1" name="buttonStatus" value="graph">Graph Chart</button>
        <button class="button button2" id="button2" name="buttonStatus" value="table">Table</button>
    </form>
    <br>
    <form action="" method="post" class="hotelform">
        <label for="hotel">Select Hotels:</label>
        <select id="hotel" name="hotel">
            <option value="all">All Hotels</option>
            <?php
            // Fetch unique countries from MongoDB
            $query = "SELECT DISTINCT nama FROM hotels";
            $result = $conn->query($query);
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $hotel = $row['nama'];
                    echo "<option value=\"$hotel\">$hotel</option>";
                }
            }
            ?>
        </select>
        <button class="btn btn-primary btn-filter" id="buttons1" name="buttonStatus" value="year">By Year</button>
        <button class="btn btn-primary btn-filter" id="buttons2" name="buttonStatus" value="month">By month</button>
        <button class="btn btn-primary btn-filter" id="buttons3" name="buttonStatus" value="day">By day</button>
    </form>
    <br>

    </div>
    <br>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php 
        if($defaultCookieValue === 'graph'){
    ?>
    
    <!-- Create a canvas for Total Bookings -->
    <canvas id="totalBookingsChart" width="400" height="200"></canvas>

    <!-- Create a canvas for Total Sum -->
    <canvas id="totalSumChart" width="400" height="200"></canvas>
    <?php 
    }elseif($defaultCookieValue === 'table'){
    ?>
    <?php 

    }?>
</div>


<script>
    function openNav() {
        document.getElementById("sidebar").style.width = "250px";
        document.body.style.overflow = "hidden"; /* Disable body scrolling */
    }

    function closeNav() {
        document.getElementById("sidebar").style.width = "0";
        document.body.style.overflow = "auto"; /* Enable body scrolling */
    }
    // PHP data to JavaScript
    var bookingsByTime = <?php echo json_encode($bookingsByTime); ?>;

    // Extract labels (time) and data for Total Bookings and Total Sum
    var labels = Object.keys(bookingsByTime);
    var totalBookingsData = labels.map(function (time) {
        return bookingsByTime[time]['total_bookings'];
    });
    var totalSumData = labels.map(function (time) {
        return bookingsByTime[time]['total_sum'];
    });

    // Create a bar chart for Total Bookings
    var ctxTotalBookings = document.getElementById('totalBookingsChart').getContext('2d');
    var totalBookingsChart = new Chart(ctxTotalBookings, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Total Bookings',
                data: totalBookingsData,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Create a bar chart for Total Sum
    var ctxTotalSum = document.getElementById('totalSumChart').getContext('2d');
    var totalSumChart = new Chart(ctxTotalSum, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Total Profit',
                data: totalSumData,
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
</script>

</body>
</html>
