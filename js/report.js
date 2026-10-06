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

fetch("php/report.php")
    .then(response => response.json())
    .then(data => {
        const sensorBody = document.getElementById("sensorTableBody");
        data.sensor_readings.forEach(row => {
            const condition = getMoistureCondition(row.moisture_level);
            const tr = document.createElement("tr");
            tr.innerHTML = `
                <td>${row.moisture_level}</td>
                <td style="color:${condition.color}; font-weight:bold;">${condition.label}</td>
                <td>${row.temperature}</td>
                <td>${row.reading_time}</td>
            `;
            sensorBody.appendChild(tr);
        });

        const logBody = document.getElementById("logTableBody");
        data.irrigation_logs.forEach(row => {
            const tr = document.createElement("tr");
            tr.innerHTML = `
                <td>${row.mode}</td>
                <td>${row.action}</td>
                <td>${row.water_used_litres ?? "-"}</td>
                <td>${row.action_time}</td>
            `;
            logBody.appendChild(tr);
        });
    })
    .catch(error => console.error("Error loading report:", error));