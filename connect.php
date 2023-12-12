<?php
$servername = "localhost";
$username = "root"; // Change this to your MySQL username if it's not "root"
$password = ""; // Change this to your MySQL password if you've set one
$dbname = "hotel_db";

// Create a connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>