<?php
include('connect.php');
require_once 'autoload.php';

$client = new MongoDB\Client();
$bookings = $client->pdds->bookings;
$collection = $client->pdds->room_types;

$countryQuery = "SELECT DISTINCT negara FROM hotels";
$countryResult = $conn->query($countryQuery);

$tipekamar = $collection->distinct('nama');

if (!$countryResult || !$tipekamar) {
    echo "Error fetching data.";
    exit();
}

if (isset($_POST['submit'])) {
    // print_r('tol');
    $country = $_POST['country'];
    $ratings = $_POST['ratings'];
    $roomTypes = $_POST['roomTypes'];

    if (!empty($country)) {
        $query = "SELECT id FROM hotels WHERE negara = ? and score >= ?";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("sd", $country, $ratings);

        $stmt->execute();

        $stmt->bind_result($hotelId);

        $hotelIds = [];
        while ($stmt->fetch()) {
            $hotelIds[] = $hotelId;
        }

        $stmt->close();

        // Fetch all bookings for selected hotels
        $allBookingsFilter = ['id_hotel' => ['$in' => $hotelIds]];
        $allBookings = $bookings->find($allBookingsFilter);

        $idBookingKamarArray = [];

        foreach ($allBookings as $booking) {
            $id_booking = $booking['_id'];
            $id_kamar = $booking['id_kamar'];
            $id_hotel = $booking['id_hotel'];
        
            // Fetch the kamar name from room_types collection
            $kamarFilter = ['_id' => $id_kamar];
            $kamarData = $collection->findOne($kamarFilter);
        
            // Add data to the result array only if kamar_name matches the selected room type
            if ($kamarData && $kamarData['nama'] == $roomTypes) {
                $idBookingKamarArray[] = [
                    'id_booking' => $id_booking,
                    'kamar_name' => $kamarData['nama'],
                    'id_hotel' => $id_hotel
                ];
            }
        }
        
        // Now $idBookingKamarArray contains the desired filtered data
    }
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
                <select name="roomTypes" id="roomTypes">
                    <?php
                    foreach ($tipekamar as $type) {
                        echo "<option value='" . $type . "'>" . $type . "</option>";
                    }
                    ?>
                </select>

                <input type="submit" name="submit" value="Submit">

            </form>
        </div>
                
        <table>
        <thead>
        <tr>
            <th>nama</th>
            <th>negara</th>
            <th>kota</th>
            <th>alamat</th>
            <th>score</th>
            <!-- Add other table headers as needed -->
        </tr>
        </thead>
        <?php 
                    foreach ($idBookingKamarArray as $booking) {
                        $id_booking = $booking['id_booking'];
                        $kamar_name = $booking['kamar_name'];
                        // Assuming you have the id_hotel stored in $row['id_hotel'] from the previous query
                        $id_hotel = $booking['id_hotel'];
                    
                        // Fetch hotel details using id_hotel
                        $query = "SELECT * FROM hotels WHERE id = '$id_hotel'";
                        $result = $conn->query($query);
                    
                        while ($row = $result->fetch_assoc()) {
                            $nama = $row['nama'];
                            $negara = $row['negara'];
                            $kota = $row['kota'];
                            $alamat = $row['alamat'];
                            $score = $row['score'];
                    
                            echo "<tr>";
                            echo "<td>$nama</td>";
                            echo "<td>$negara</td>";
                            echo "<td>$kota</td>";
                            echo "<td>$alamat</td>";
                            echo "<td>$score</td>";
                            echo "</tr>";
                        }
                    }
                ?>
        </table>
    </div>

    <script>
        // ... (kode JavaScript untuk sidebar) ...
    </script>

</body>

</html>
