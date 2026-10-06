<?php
require 'db.php';
header('Content-Type: application/json');

$zoneId = isset($_GET['zone_id']) ? (int)$_GET['zone_id'] : 0;

$zoneStmt = $conn->prepare("SELECT id, name, display_name, moisture_min, moisture_max FROM zones WHERE id = ?");
$zoneStmt->bind_param("i", $zoneId);
$zoneStmt->execute();
$zoneData = $zoneStmt->get_result()->fetch_assoc();
$zoneStmt->close();

if (!$zoneData) {
    echo json_encode(["error" => "Invalid or missing zone_id"]);
    exit();
}

// Check current motor status FIRST — this decides whether we generate new readings at all
$motorResult = $conn->query("SELECT status, mode, updated_at FROM motor_status WHERE id = 1");
$motorData = $motorResult->fetch_assoc();
$motorIsOn = ($motorData['status'] === 'on');

// Crop context, used only for auto-decision logic further down
$cropId = isset($_GET['crop_id']) ? (int)$_GET['crop_id'] : 0;
$cropData = null;
if ($cropId > 0) {
    $cropStmt = $conn->prepare("SELECT id, name, min_moisture, max_temp_boost FROM crop_profiles WHERE id = ?");
    $cropStmt->bind_param("i", $cropId);
    $cropStmt->execute();
    $cropData = $cropStmt->get_result()->fetch_assoc();
    $cropStmt->close();
}

// Derives a plausible temperature from a moisture value, so readings stay internally consistent
function simulateTemperature($moisture) {
    if ($moisture < 20) return round(34 + mt_rand(0, 400) / 100, 1);
    if ($moisture < 40) return round(28 + mt_rand(0, 500) / 100, 1);
    if ($moisture < 55) return round(22 + mt_rand(0, 400) / 100, 1);
    if ($moisture < 70) return round(19 + mt_rand(0, 300) / 100, 1);
    return round(15 + mt_rand(0, 300) / 100, 1);
}

$refreshSeconds = 10; // only relevant while the motor is ON

// Get every sensor in this zone, and whether each one is due for a new simulated reading
$sensorListStmt = $conn->prepare("SELECT id, calibration_offset FROM sensors WHERE zone_id = ?");
$sensorListStmt->bind_param("i", $zoneId);
$sensorListStmt->execute();
$sensorList = $sensorListStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$sensorListStmt->close();

if ($motorIsOn) {
    foreach ($sensorList as $sensor) {
        $sensorId = $sensor['id'];

        $ageStmt = $conn->prepare("SELECT TIMESTAMPDIFF(SECOND, reading_time, NOW()) AS age_seconds FROM sensor_readings WHERE sensor_id = ? ORDER BY id DESC LIMIT 1");
        $ageStmt->bind_param("i", $sensorId);
        $ageStmt->execute();
        $ageRow = $ageStmt->get_result()->fetch_assoc();
        $ageStmt->close();

        $needsNewReading = (!$ageRow || $ageRow['age_seconds'] >= $refreshSeconds);

        if ($needsNewReading) {
            $moistureMin = $zoneData['moisture_min'];
            $moistureMax = $zoneData['moisture_max'];
            $newMoisture = round($moistureMin + mt_rand() / mt_getrandmax() * ($moistureMax - $moistureMin), 1);
            $newTemp = simulateTemperature($newMoisture);

            $insertStmt = $conn->prepare("INSERT INTO sensor_readings (sensor_id, moisture_level, temperature) VALUES (?, ?, ?)");
            $insertStmt->bind_param("idd", $sensorId, $newMoisture, $newTemp);
            $insertStmt->execute();
            $insertStmt->close();
        }
    }
}
// If motor is OFF, we skip all of the above — nothing new gets inserted,
// so every sensor simply returns whatever its last recorded value was.

// Now fetch the (possibly just-updated, possibly frozen) latest reading per sensor
$stmt = $conn->prepare("
    SELECT s.id AS sensor_id, s.name, s.calibration_offset,
           sr.moisture_level, sr.temperature, sr.reading_time
    FROM sensors s
    LEFT JOIN sensor_readings sr ON sr.id = (
        SELECT id FROM sensor_readings WHERE sensor_id = s.id ORDER BY id DESC LIMIT 1
    )
    WHERE s.zone_id = ?
");
$stmt->bind_param("i", $zoneId);
$stmt->execute();
$result = $stmt->get_result();

$sensors = [];
$moistureSum = 0;
$tempSum = 0;
$readingCount = 0;

while ($row = $result->fetch_assoc()) {
    $hasReading = $row['moisture_level'] !== null;
    $calibratedMoisture = $hasReading ? round($row['moisture_level'] + $row['calibration_offset'], 1) : null;

    $sensors[] = [
        "sensor_id" => $row['sensor_id'],
        "name" => $row['name'],
        "moisture_level" => $calibratedMoisture,
        "temperature" => $hasReading ? $row['temperature'] : null,
        "reading_time" => $hasReading ? $row['reading_time'] : null
    ];

    if ($hasReading) {
        $moistureSum += $calibratedMoisture;
        $tempSum += $row['temperature'];
        $readingCount++;
    }
}
$stmt->close();

$message = null;
$zoneAverage = null;

if (count($sensors) === 0) {
    $message = "No sensors configured for this zone.";
} elseif ($readingCount === 0) {
    $message = "Sensor(s) registered but no readings recorded yet. Turn the motor ON to generate an initial reading.";
} else {
    $zoneAverage = [
        "moisture_level" => round($moistureSum / $readingCount, 1),
        "temperature" => round($tempSum / $readingCount, 1)
    ];
    if (!$motorIsOn) {
        $message = "Motor is OFF — showing the last recorded reading (values are frozen until the motor is turned ON).";
    }
}

// ---------- Auto-decision pipeline: reading -> analysis -> decision -> motor control ----------
$decision = null;

if ($motorData['mode'] === 'auto' && $zoneAverage !== null) {
    $moisture = $zoneAverage['moisture_level'];
    $temperature = $zoneAverage['temperature'];

    // Fall back to the zone's own threshold if no crop was specified
    $requiredMoisture = $cropData ? $cropData['min_moisture'] : $zoneData['moisture_min'];
    $cropLabel = $cropData ? $cropData['name'] : "this zone's default";

    // A hot reading effectively raises the moisture bar (crop dries out faster)
    $tempBoost = $cropData && $temperature > 30 ? $cropData['max_temp_boost'] : 0;
    $effectiveThreshold = $requiredMoisture + $tempBoost;

    $waterNeeded = $moisture < $effectiveThreshold;
    $neededAction = $waterNeeded ? 'on' : 'off';

    $decision = "Soil moisture is {$moisture}% (needs ≥{$effectiveThreshold}% for {$cropLabel}"
        . ($tempBoost > 0 ? ", raised for {$temperature}°C heat" : "")
        . "). " . ($waterNeeded ? "Water is required — Motor ON." : "Moisture is sufficient — Motor OFF.");

    // Only write to the DB and log when the decision actually changes the current state
    if ($neededAction !== $motorData['status']) {
        $updateStmt = $conn->prepare("UPDATE motor_status SET status = ? WHERE id = 1");
        $updateStmt->bind_param("s", $neededAction);
        $updateStmt->execute();
        $updateStmt->close();

        $logStmt = $conn->prepare("INSERT INTO irrigation_logs (mode, action) VALUES ('auto', ?)");
        $logStmt->bind_param("s", $neededAction);
        $logStmt->execute();
        $logStmt->close();

        $motorData['status'] = $neededAction;
    }
}

echo json_encode([
    "zone_id" => $zoneData['id'],
    "zone_name" => $zoneData['name'],
    "zone_display_name" => $zoneData['display_name'],
    "sensor_count" => count($sensors),
    "sensors" => $sensors,
    "zone_average" => $zoneAverage,
    "message" => $message,
    "motor" => $motorData,
    "decision" => $decision
]);
?>