<?php
include('connect.php');
require_once 'autoload.php';
$client = new MongoDB\Client();
$bookings = $client->pdds->bookings;

$bookingData = $bookings->find();

// Process data to calculate required values
$countryStats = array();
$query = "SELECT negara, id AS _id FROM customers";
$result = $conn->query($query);

// Check if the query was successful
if ($result !== false) {
    // Fetch each row
    while ($customer = $result->fetch_assoc()) {
        $country = (string)$customer['negara'];
        $totalCustomers = isset($countryStats[$country]['totalCustomers']) ? $countryStats[$country]['totalCustomers'] + 1 : 1;
        $customerBookings = iterator_to_array($bookings->find(['id_customer' => (int)$customer['_id']]));
        
        $totalBookings = isset($countryStats[$country]['totalBookings']) ? $countryStats[$country]['totalBookings'] + count($customerBookings) : count($customerBookings);
        // Calculate total_orang for each booking
        $bookingIds = [];
        $totalOrang = 0;
        $totalkamar = 0;
        $totaldurasi = 0;

        // Process each booking for the customer
        foreach ($customerBookings as $booking) {
            $bookingIds[] = (int)$booking['_id'];
            $totalOrang += $booking['total_orang'];
            $totalkamar += $booking['total_kamar'];
            $totaldurasi += $booking['durasi'];
        }

        // Calculate average_orang
        $averageOrang = ($totalBookings > 0) ? $totalOrang / $totalBookings : 0;
        $averageKamar = ($totalBookings > 0) ? $totalkamar / $totalBookings : 0;
        $averageDurasi = ($totalBookings > 0) ? $totaldurasi / $totalBookings : 0;
        $existingBookingIds = isset($countryStats[$country]['bookingIds']) ? $countryStats[$country]['bookingIds'] : [];
        $countryStats[$country] = array(
            'totalCustomers' => $totalCustomers,
            'totalBookings' => $totalBookings,
            'averageOrang' => $averageOrang,
            'averageKamar' => $averageKamar,
            'averageDurasi' => $averageDurasi,
            'bookingIds' => array_merge($existingBookingIds, $bookingIds),
            // Add other calculated values here
        );
        }
} else {
    echo "Error executing query: " . $conn->error;
}

foreach ($countryStats as $country => $stats) {
    $totalBookings = $stats['totalBookings'];

    if ($totalBookings != 0) {
        $money = 0;

        foreach ($stats['bookingIds'] as $bookingId) {
            // Assuming your database connection is $conn
            $query = "SELECT total FROM transactions WHERE id = $bookingId";
            $result = $conn->query($query);

            if ($result !== false && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $money += $row['total'];
            } else {
                echo "Error executing query or no result for booking ID $bookingId\n";
            }
        }

        $avgMoney = $money / $totalBookings;

        // Update the array with money
        $countryStats[$country]['money'] = $money;
        $countryStats[$country]['avgMoney'] = $avgMoney;
    }
}



function sortByField(&$array, $field, $sortOrder = 'ascending') {
    if ($field === 'country') {
        if ($sortOrder === 'ascending') {
            ksort($array); // Sort alphabetically for 'Country'
        } else {
            krsort($array); // Sort alphabetically in reverse order
        }
    } else {
        uasort($array, function ($a, $b) use ($field, $sortOrder) {
            $valueA = $a[$field];
            $valueB = $b[$field];
        
            $comparison = ($valueA < $valueB) ? -1 : 1;
            if ($valueA === $valueB) {
                $comparison = 0;
            }
        
            return ($sortOrder === 'ascending') ? $comparison : -$comparison;
        });
    }
}


// Usage
if (isset($_GET['sortBy'])) {
    $sortBy = $_GET['sortBy'];
    $sortOrder = isset($_GET['sortOrder']) ? $_GET['sortOrder'] : 'ascending';

    if ($sortBy == 'TotalCustomers' || $sortBy == 'TotalBookings' || $sortBy == 'money'|| $sortBy == 'AverageOrang' || $sortBy == 'AverageKamar' || $sortBy == 'AverageSpending' ||$sortBy == 'avgMoney') {
        sortByField($countryStats, lcfirst($sortBy), $sortOrder);
    } elseif ($sortBy == 'Country') {
        sortByField($countryStats, 'country', $sortOrder);
    }
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
    <a href="#">Link 3</a>
</div>

<div id="menuToggle" onclick="openNav()">&#9776;</div>

<!-- New content added -->
<div id="content">
    <div id="mainText">Data All Country</div>

    <!-- Filter form -->
    

    <!-- Buttons -->
    <div class="buttonContainer">
        <a href="index.php" class="button button1">all customers</a>
        <a href="all_country.php" class="button button2">all country</a>
        <a href="country.php" class="button button3">country</a>
    </div>
    <br>
    <form id="filterForm" method="GET">
        <label for="sortBy">Sort By:</label>
        <select id="sortBy" name="sortBy">
            <option value="Country">Country</option>
            <option value="TotalCustomers">Total Customers</option>
            <option value="TotalBookings">Total Bookings</option>
            <option value="money">Total Spending</option>
            <option value="AverageOrang">Average Orang</option>
            <option value="AverageKamar">Average Kamar</option>
            <option value="AverageDurasi">Average Durasi</option>
            <option value="avgMoney">Average Spending</option>
        </select>
        <input type="submit" value="Apply">
    </form>
    <form id="sortOrderForm" method="GET">
        <input type="hidden" name="sortBy" value="<?php echo isset($_GET['sortBy']) ? $_GET['sortBy'] : 'ascending'; ?>">
        <button type="submit" name="sortOrder" value="<?php echo (isset($_GET['sortOrder']) && $_GET['sortOrder'] === 'ascending') ? 'descending' : 'ascending'; ?>">
        <?php echo (isset($_GET['sortOrder']) && $_GET['sortOrder'] === 'ascending') ? 'Sort Descending' : 'Sort Ascending'; ?>
        </button>
    </form>
    <table>
        <thead>
        <tr>
            <th>Country</th>
            <th>Total Customers</th>
            <th>Total Bookings</th>
            <th>Total Spending</th>
            <th>average Orang</th>
            <th>average Kamar</th>
            <th>average Durasi</th>
            <th>average Spending</th>
            <!-- Add other table headers as needed -->
        </tr>
        </thead>
        <tbody>
        <?php
        foreach ($countryStats as $country => $stats) {
            echo "<tr>";
            echo "<td>$country</td>";
            echo "<td>{$stats['totalCustomers']}</td>";
            echo "<td>{$stats['totalBookings']}</td>";
            echo "<td>{$stats['money']}</td>";
            echo "<td>{$stats['averageOrang']}</td>";
            echo "<td>{$stats['averageKamar']}</td>";
            echo "<td>{$stats['averageDurasi']}</td>";
            echo "<td>{$stats['avgMoney']}</td>";
            // Add other table cells for calculated values
            echo "</tr>";
        }
        ?>
    </tbody>
    </table>
    </div>
    
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
