<?php
include('connect.php');
require_once 'autoload.php';

// MongoDB connection
$client = new MongoDB\Client();
$bookings = $client->pdds->bookings;
// ... (existing code) ...

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggalCheckIn = isset($_POST['tanggalCheckIn']) ? $_POST['tanggalCheckIn'] : '2020-01-01';
    $tanggalCheckOut = isset($_POST['tanggalCheckOut']) ? $_POST['tanggalCheckOut'] : date('Y-m-d');
    $minspending = $_POST['spending'];
    // Convert string dates to MongoDB\BSON\UTCDateTime objects
    $checkInDate = new MongoDB\BSON\UTCDateTime(strtotime($tanggalCheckIn) * 1000);
    $checkOutDate = new MongoDB\BSON\UTCDateTime(strtotime($tanggalCheckOut) * 1000);

    $filter = [
        'tanggal_checkin' => ['$gte' => $checkInDate],
        'tanggal_checkout' => ['$lte' => $checkOutDate],
    ];

} else {
    $filter = [];

}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>

<div id="sidebar">
    <div id="closeBtn" onclick="closeNav()">&#10006;</div>
    <a href="index.php">Customers</a>
    <a href="hotel.php">Hotels</a>
    <a href="reviews.php">Reviews</a>
</div>

<div id="menuToggle" onclick="openNav()">&#9776;</div>

<!-- New content added -->
<div id="content">
    <div id="mainText">Data Customer</div>

    <!-- Buttons -->
    <div class="buttonContainer">
        <a href="add_riwayat.php" class="button button1">all customers</a>
        <a href="all_country.php" class="button button2">all country</a>
        <a href="country.php" class="button button3">country</a>
    </div>
    <div id="mainText">Filter Hotel</div>
    <div class="centeredFormContainer">
        <!-- Form for Date Range Filter -->
        <form action="" method="post" class="filterForm">
            <label for="tanggalCheckIn">Tanggal Check In:</label>
            <input type="date" id="tanggalCheckIn" name="tanggalCheckIn" required>

            <label for="tanggalCheckOut">Tanggal Check Out:</label>
            <input type="date" id="tanggalCheckOut" name="tanggalCheckOut" required>

            <label for="intFilter">Minimal Spending:</label>
            <input type="number" id="spending" name="spending" required>
            <br>
            <input type="submit" value="Filter">
        </form>
    </div>

    <!-- Table -->
    <table>
        <thead>
            <tr>
                <th>Nama</th>
                <th>Negara</th>
                <th>Email</th>
                <th>Nomor Telepon</th>
                <th>Alamat</th>
                <th>Total Booking</th>
                <th>Total Spending</th> 
            </tr>
        </thead>
        <tbody>
        <?php
        $customerQuery = "SELECT * FROM customers";
        $customerResult = $conn->query($customerQuery);
        
        // Check if the query was successful
        if ($customerResult !== false) {
            // Fetch each customer
            while ($customer = $customerResult->fetch_assoc()) {
                $customerId = (int)$customer['id'];
        
                if (!empty($filter)) {
                    $customerID = ['id_customer' => $customerId];
                    $filter = array_merge($filter, $customerID);
                }
                else{
                    $filter = ['id_customer' => $customerId];
                }
                // Total Booking

                // Total Spending (from PHPMyAdmin transactions table)
                $totalSpending = 0; // Default value if query fails
                $totalBooking = 0;
                // Get all bookings for the current customer
                $customerBookings = $bookings->find($filter);
                $results = iterator_to_array($customerBookings);
                foreach ($results as &$document) {
                    $totalBooking = $totalBooking +1;
                }
                $customerBookings = $bookings->find($filter);
                // Loop through each booking
                foreach ($customerBookings as $booking) {
                    $bookingId = (string)$booking['_id']; 
                    if ($conn->connect_errno) {
                        echo "Failed to connect to MySQL: " . $conn->connect_error;
                    } else {
                        $totalSpendingQuery = "SELECT SUM(total) AS total_spending FROM transactions WHERE id_bookings = '$bookingId'";
                        $totalSpendingResult = $conn->query($totalSpendingQuery);

                        // Check if the query was successful
                        if ($totalSpendingResult !== false) {
                            $totalSpendingRow = $totalSpendingResult->fetch_assoc();
                            $totalSpending += $totalSpendingRow['total_spending'];
                        } else {
                            echo "Error executing query: " . $conn->error;
                        }
                    }
                }
                if (!isset($minspending) || $minspending <= $totalSpending) {
                    echo "<tr>";
                    echo "<td>{$customer['nama']}</td>";
                    echo "<td>{$customer['negara']}</td>";
                    echo "<td>{$customer['email']}</td>";
                    echo "<td>{$customer['no_telepon']}</td>";
                    echo "<td>{$customer['alamat']}</td>";
                    echo "<td>{$totalBooking}</td>";
                    echo "<td>{$totalSpending}</td>";
                    echo "</tr>";
                }
            }       
        }
        ?>
    </tbody>
    </table>
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
</script>

</body>
</html>
