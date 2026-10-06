<?php
require 'db.php';

if (isset($_GET['action']) && isset($_GET['mode'])) {
    $action = $_GET['action'];   // "on" or "off"
    $mode = $_GET['mode'];       // "manual" or "auto"

    if ($action !== "on" && $action !== "off") {
        die("Invalid action");
    }
    if ($mode !== "manual" && $mode !== "auto") {
        die("Invalid mode");
    }

    // Update the single motor_status row
    $update = $conn->prepare("UPDATE motor_status SET status = ?, mode = ? WHERE id = 1");
    $update->bind_param("ss", $action, $mode);
    $update->execute();
    $update->close();

    // Log this action into irrigation_logs
    $log = $conn->prepare("INSERT INTO irrigation_logs (mode, action) VALUES (?, ?)");
    $log->bind_param("ss", $mode, $action);
    $log->execute();
    $log->close();

    echo "Motor turned " . strtoupper($action);
} else {
    echo "Missing action or mode";
}
?>