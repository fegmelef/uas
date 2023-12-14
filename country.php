<?php
if (!isset($_COOKIE['datacookie'])) {
    $defaultCookieValue = 'booking';
    setcookie('datacookie', $defaultCookieValue, time() + 3600, '/'); 
} else {
    $defaultCookieValue = $_COOKIE['datacookie'];
}
include('connect.php');
require_once 'autoload.php';
use MongoDB\Client;

// Connect to MongoDB
$client = new Client();
$bookings = $client->pdds->bookings;
$reviews = $client->pdds->reviews;

$error = '';
$resultsFound = false;
$bookingsByTime = [];
$cookieValue = ''; // Initialize $cookieValue outside the if statement

if (isset($_POST['buttonsStatus'])) {
    $buttonStatus = $_POST["buttonsStatus"];

    if ($buttonStatus === 'booking') {
        $cookieValue = 'booking';
    } elseif ($buttonStatus == 'top') {
        $cookieValue = 'top';
    } else {
        $cookieValue = 'booking';
    }
    $results = [];

    setcookie('datacookie', $cookieValue, time() + 3600, '/');
    echo '<script type="text/javascript">window.location.href = window.location.href;</script>';
}
if (isset($_POST['country'])) {
    $country = $_POST['country'];

    if (!empty($country)) {
        // Assuming your table has a column named "negara" for the country
        $query = "SELECT id FROM customers WHERE negara = ?";
        
        // Use prepared statements to prevent SQL injection
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $country);
        
        // Execute the query
        $stmt->execute();
        
        // Bind the result variable
        $stmt->bind_result($customerId);
        
        // Fetch results into an array
        while ($stmt->fetch()) {
            $customerIds[] = $customerId;
        }
        
        // Close the statement
        $stmt->close();
    }

    $allBookingsFilter = ($country !== "all") ? ['id_customer' => ['$in' => $customerIds]] : [];
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

    ksort($bookingsByTime);
}

if (isset($_POST['negara'])) {
    $country = $_POST['negara'];

    if (!empty($country)) {
        // Assuming your table has a column named "negara" for the country
        $query = "SELECT id FROM customers WHERE negara = ?";
        
        // Use prepared statements to prevent SQL injection
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $country);
        
        // Execute the query
        $stmt->execute();
        
        // Bind the result variable
        $stmt->bind_result($customerId);
        
        // Fetch results into an array
        while ($stmt->fetch()) {
            $customerIds[] = $customerId;
        }
        
        // Close the statement
        $stmt->close();
    }

    // Fetch bookings from MongoDB
    $allBookingsFilter = ($country !== "all") ? ['id_customer' => ['$in' => $customerIds]] : [];

    $allBookings = $bookings->find($allBookingsFilter);

    $hotelScores = [];

    foreach ($allBookings as $booking) {
        $id = $booking['_id'];
        $hotel_id = $booking['id_hotel'];

        // Fetch hotel name from MySQL
        $query = "SELECT nama FROM hotels WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $hotel_id);
        $stmt->execute();
        $stmt->bind_result($hotel);
        while ($stmt->fetch()) {
            // If the hotel is not present in the array, initialize it
            if (!isset($hotelScores[$hotel])) {
                $hotelScores[$hotel] = ['score' => []];
            }

            // Fetch the score from MongoDB
            $reviewCursor = $reviews->find(['id_booking' => $id]);
            $review = $reviewCursor->toArray();
            if (!empty($review)) {
                $score_temp = $review[0]['score'];
                $hotelScores[$hotel]['score'][] = $score_temp;
            }
        }
        $stmt->close();
    }

    // Calculate average scores for each hotel
    $averageScores = [];
    foreach ($hotelScores as $hotel => $data) {
        $scoreArray = $data['score'];
        $averageScore = count($scoreArray) > 0 ? array_sum($scoreArray) / count($scoreArray) : 0;
        $averageScores[$hotel] = $averageScore;
    }

    // Sort hotels based on average scores
    arsort($averageScores);

    // Create arrays for top and bottom hotels
    $topHotels = array_slice($averageScores, 0, 5);
    $bottomHotels = array_slice($averageScores, -5, 5);

    // Print or use $topHotels and $bottomHotels as needed
}

ksort($bookingsByTime);


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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <div class="buttonContainer">
        <a href="index.php" class="button button1">all customers</a>
        <a href="all_country.php" class="button button2">all country</a>
        <a href="country.php" class="button button3">country</a>
    </div>
    <div class="centeredFormContainers">
    <form method="post">
        <button class="btn btn-primary btn-filter" id="booking" name="buttonsStatus" value="booking">Booking</button>
        <button class="btn btn-primary btn-filter" id="top" name="buttonsStatus" value="top">Top 5</button>
    </form>
    <br>
    <?php
        if ($defaultCookieValue === 'booking') {
            ?>
        <form action="" method="post" class="countryform">
            <label for="country">Select Country:</label>
            <select id="country" name="country">
                <option value="all">All Countries</option>
                <?php
                $query = "SELECT DISTINCT negara FROM customers";
                $result = $conn->query($query);
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        $country = $row['negara'];
                        echo "<option value=\"$country\">$country</option>";
                    }
                }
                ?>
            </select>
            <button class="btn btn-primary btn-filter" id="buttons1" name="buttonStatus" value="year">By Year</button>
            <button class="btn btn-primary btn-filter" id="buttons2" name="buttonStatus" value="month">By month</button>
            <button class="btn btn-primary btn-filter" id="buttons3" name="buttonStatus" value="day">By day</button>
        </form>
        <br>
        
        <!-- Create a canvas for Total Bookings -->
        <canvas id="totalBookingsChart" width="400" height="200"></canvas>

        <!-- Create a canvas for Total Sum -->
        <canvas id="totalSumChart" width="400" height="200"></canvas>
    <?php 
        }
        elseif ($defaultCookieValue === 'top') {
    ?>
        <form action="" method="post" class="topform">
            <label for="country">Select Country:</label>
            <select id="negara" name="negara">
                <option value="all">All Countries</option>
                <?php
                $query = "SELECT DISTINCT negara FROM customers";
                $result = $conn->query($query);
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        $country = $row['negara'];
                        echo "<option value=\"$country\">$country</option>";
                    }
                }
                ?>
            </select>
            <button class="btn btn-primary btn-filter" id="buttons1" name="buttonStatus" value="pick">Pick</button>
        </form>
        <br>
        <!-- Create a canvas for Total Bookings -->
        <canvas id="topHotelsChart" width="400" height="200"></canvas>

        <!-- Create a canvas for Total Sum -->
        <canvas id="bottomHotelsChart" width="400" height="200"></canvas>
    <?php 
        } 
        ?>
        
    </div>
   
</div>


<script>
    document.addEventListener("DOMContentLoaded", function() {
        // PHP data to JavaScript
        var bookingsByTime = <?php echo json_encode($bookingsByTime); ?>;

        // Check if the data exists
        console.log(bookingsByTime);

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
    });
</script>

<!-- Script for Top Hotels and Bottom Hotels -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // PHP data to JavaScript
        var topHotelsData = <?php echo json_encode($topHotels); ?>;
        var bottomHotelsData = <?php echo json_encode($bottomHotels); ?>;

        // Extract labels (hotel names) and data for Top Hotels and Bottom Hotels
        var topLabels = Object.keys(topHotelsData);
        var topScoresData = topLabels.map(function (hotel) {
            return topHotelsData[hotel];
        });

        var bottomLabels = Object.keys(bottomHotelsData);
        var bottomScoresData = bottomLabels.map(function (hotel) {
            return bottomHotelsData[hotel];
        });

        // Create a bar chart for Top Hotels
        var ctxTopHotels = document.getElementById('topHotelsChart').getContext('2d');
        var topHotelsChart = new Chart(ctxTopHotels, {
            type: 'bar',
            data: {
                labels: topLabels,
                datasets: [{
                    label: 'Top 5 Hotel',
                    data: topScoresData,
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

        // Create a bar chart for Bottom Hotels
        var ctxBottomHotels = document.getElementById('bottomHotelsChart').getContext('2d');
        var bottomHotelsChart = new Chart(ctxBottomHotels, {
            type: 'bar',
            data: {
                labels: bottomLabels,
                datasets: [{
                    label: 'Bottom 5 Hotel',
                    data: bottomScoresData,
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
    });
</script>


</body>
</html>
