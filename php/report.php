<?php
require 'db.php';
header('Content-Type: application/json');

// Last 20 sensor readings, newest first
$sensorResult = $conn->query("SELECT moisture_level, temperature, reading_time FROM sensor_readings ORDER BY id DESC LIMIT 20");
$sensorReadings = [];
while ($row = $sensorResult->fetch_assoc()) {
    $sensorReadings[] = $row;
}

// Last 20 irrigation log entries, newest first
$logResult = $conn->query("SELECT mode, action, water_used_litres, action_time FROM irrigation_logs ORDER BY id DESC LIMIT 20");
$irrigationLogs = [];
while ($row = $logResult->fetch_assoc()) {
    $irrigationLogs[] = $row;
}

echo json_encode([
    "sensor_readings" => $sensorReadings,
    "irrigation_logs" => $irrigationLogs
]);
?>