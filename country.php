<?php
include('connect.php');
require_once 'autoload.php';
use MongoDB\Client;

// Connect to MongoDB
$client = new Client();
$customers = $client->pdds->customers;
$bookings = $client->pdds->booking;

$error = '';
$resultsFound = false;
$bookingsByTime = [];

if (isset($_POST['country']) && $_POST['country'] !== "") {
    $country = $_POST['country'];

    // Fetch customer IDs from MongoDB
    $customerIds = $customers->find(['negara' => $country], ['projection' => ['_id' => 1]]);
    $customerIdsArray = array_map(function($document) {
        return $document['_id'];
    }, iterator_to_array($customerIds));

    // Fetch bookings from MongoDB
    $allBookings = $bookings->find([
        'id_customer' => ['$in' => $customerIdsArray],
    ]);

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
$conn->close();
// ...
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer</title>
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
    <div id="mainText">Data Country</div>
    <div class="buttonContainer">
        <a href="index.php" class="button button1">all customers</a>
        <a href="all_country.php" class="button button2">all country</a>
        <a href="country.php" class="button button3">country</a>
    </div>
    <br>
    <div class="centeredFormContainers">
    <form action="" method="post" class="countryform">
        <label for="country">Select Country:</label>
        <select id="country" name="country">
            <option value="">All Countries</option>
            <?php
            // Fetch unique countries from MongoDB
            $uniqueCountries = $customers->distinct('negara');
            foreach ($uniqueCountries as $country) {
                echo "<option value=\"$country\">$country</option>";
            }
            ?>
        </select>
        <button class="btn btn-primary btn-filter" id="buttons1" name="buttonStatus" value="year">By Year</button>
        <button class="btn btn-primary btn-filter" id="buttons2" name="buttonStatus" value="month">By month</button>
        <button class="btn btn-primary btn-filter" id="buttons3" name="buttonStatus" value="day">By day</button>
    </form>
    </div>
    <br>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Create a canvas for Total Bookings -->
    <canvas id="totalBookingsChart" width="400" height="200"></canvas>

    <!-- Create a canvas for Total Sum -->
    <canvas id="totalSumChart" width="400" height="200"></canvas>
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
