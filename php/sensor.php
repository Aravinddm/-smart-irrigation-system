<?php
require 'db.php';

if (isset($_GET['sensor_id']) && isset($_GET['moisture']) && isset($_GET['temperature'])) {
    $sensor_id = $_GET['sensor_id'];
    $moisture = $_GET['moisture'];
    $temperature = $_GET['temperature'];

    $stmt = $conn->prepare("INSERT INTO sensor_readings (sensor_id, moisture_level, temperature) VALUES (?, ?, ?)");
    $stmt->bind_param("idd", $sensor_id, $moisture, $temperature);

    if ($stmt->execute()) {
        echo "Reading saved successfully";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
} else {
    echo "Missing sensor_id, moisture, or temperature";
}
?>