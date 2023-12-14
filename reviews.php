<?php
include('connect.php');
require_once 'autoload.php';

$client = new MongoDB\Client();
$bookings = $client->pdds->bookings;
$collection = $client->pdds->room_types;

$countryQuery = "SELECT DISTINCT negara FROM hotels";
$countryResult = $conn->query($countryQuery);

$roomTypes = $collection->distinct('nama');

if (!$countryResult || !$roomTypes) {
    echo "Error fetching data.";
    exit();
}
if (isset($_POST['country']) && isset($_POST['ratings']) && isset($_POST['roomTypes'])) {
    $country = $_POST['country'];
    $ratings = $_POST['ratings'];
    $roomTypes = $_POST['roomTypes'];

    if (!empty($country)) {
        $query = "SELECT id FROM hotels WHERE negara = ? and score >= ?";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("sd", $country, $ratings); // Mengikat parameter untuk negara (string) dan ratings (double)

        // Execute the query
        $stmt->execute();

        // Bind the result variable
        $stmt->bind_result($hotelId);

        // Fetch results into an array
        $hotelIds = [];
        while ($stmt->fetch()) {
            $hotelIds[] = $hotelId;
        }

        $stmt->close();

        // Lakukan sesuatu dengan $hotelIds di sini
    }
    // print_r($hotelIds);
    $allBookingsFilter = ['id_hotel' => ['$in' => $hotelIds]];
    $allBookings = $bookings->find($allBookingsFilter);

    foreach ($allBookings as $bookings) {
        $kamar_ids[] = $bookings['id_kamar']; // Accumulate id_kamar values into an array
    }

    // Fetch all rooms with _id matching $kamar_ids
    $allKamarFilter = ['_id' => ['$in' => $kamar_ids]];
    $allKamar = $collection->find($allKamarFilter);
    
    
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>

<body>

    <div id="sidebar">
        <!-- ... (kode sidebar) ... -->
    </div>

    <div id="menuToggle" onclick="openNav()">&#9776;</div>

    <div id="content">
        <div id="mainText">Data Customer</div>
        <div id="mainText">Masukan Filter</div>
        <br>
        <div class="centeredFormContainer">
            <form action="#" method="POST">
                <label for="country">Pilih Negara:</label>
                <select name="country" id="country">
                    <?php
                    while ($row = $countryResult->fetch_assoc()) {
                        echo "<option value='" . $row['negara'] . "'>" . $row['negara'] . "</option>";
                    }
                    ?>
                </select>

                <label for="ratings">Ratings (0-5):</label>
                <input type="number" step="0.1" name="ratings" id="ratings" min="0" max="5" required>

                <label for="roomType">Pilih Tipe Kamar:</label>
                <select name="roomType" id="roomType">
                    <?php
                    foreach ($roomTypes as $type) {
                        echo "<option value='" . $type . "'>" . $type . "</option>";
                    }
                    ?>
                </select>

                <input type="submit" value="Submit">
            </form>
        </div>

        <table>
            <!-- ... (tabel untuk menampilkan hasil) ... -->
        </table>
    </div>

    <script>
        // ... (kode JavaScript untuk sidebar) ...
    </script>

</body>

</html>