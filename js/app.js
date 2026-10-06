function getMoistureCondition(moisture) {
    moisture = parseFloat(moisture);

    if (moisture < 30) {
        return { label: "🔥 Very Dry", color: "#b71c1c" };
    } else if (moisture >= 30 && moisture < 40) {
        return { label: "🌵 Dry Land", color: "#e65100" };
    } else if (moisture >= 40 && moisture < 70) {
        return { label: "💧 Moist Land", color: "#2e7d32" };
    } else {
        return { label: "🌊 Wet / Waterlogged", color: "#0d47a1" };
    }
}

function loadDashboard() {
    fetch("php/dashboard-data.php")
        .then(response => response.json())
        .then(data => {
            document.getElementById("moisture").textContent = data.sensor.moisture_level;
            document.getElementById("temperature").textContent = data.sensor.temperature;
            document.getElementById("reading_time").textContent = data.sensor.reading_time;

            const condition = getMoistureCondition(data.sensor.moisture_level);
            const statusEl = document.getElementById("moisture_status");
            statusEl.textContent = condition.label;
            statusEl.style.color = condition.color;
            statusEl.style.fontWeight = "bold";

            document.getElementById("motor_status").textContent = data.motor.status;
            document.getElementById("motor_mode").textContent = data.motor.mode;
        })
        .catch(error => console.error("Error loading dashboard:", error));
}

document.getElementById("motorOnBtn").addEventListener("click", function () {
    fetch("php/motor.php?action=on&mode=manual")
        .then(response => response.text())
        .then(message => {
            alert(message);
            loadDashboard();
        });
});

document.getElementById("motorOffBtn").addEventListener("click", function () {
    fetch("php/motor.php?action=off&mode=manual")
        .then(response => response.text())
        .then(message => {
            alert(message);
            loadDashboard();
        });
});

loadDashboard();