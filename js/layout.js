// Shared layout behaviour for every sidebar page.
// Requires: <div id="sidebarMount"></div> and <body data-page="...">

function initLayout() {
    const currentPage = document.body.getAttribute("data-page");
    const params = new URLSearchParams(window.location.search);
    const carryParams = params.toString(); // e.g. zone_id=2&crop_id=3

    fetch("partials/sidebar.html")
        .then(res => res.text())
        .then(html => {
            document.getElementById("sidebarMount").innerHTML = html;

            // Highlight the active nav link + carry zone/crop params across pages
            document.querySelectorAll(".sidebar nav a").forEach(link => {
                if (link.getAttribute("data-page") === currentPage) {
                    link.classList.add("active");
                }
                if (carryParams) {
                    const base = link.getAttribute("href").split("?")[0];
                    link.setAttribute("href", base + "?" + carryParams);
                }
            });

            document.getElementById("sidebarLogout").addEventListener("click", function (e) {
                e.preventDefault();
                fetch("php/logout.php").then(() => window.location.href = "home.html");
            });
        })
        .catch(err => console.error("Sidebar load error:", err));

    // Populate the logged-in user's name in the topbar, if that element exists
    fetch("php/session-info.php")
        .then(res => res.json())
        .then(data => {
            const chip = document.getElementById("topUserName");
            if (!chip) return;
            if (data.logged_in) {
                chip.textContent = data.username;
                const avatar = document.getElementById("avatarInitial");
                if (avatar) avatar.textContent = data.username.charAt(0).toUpperCase();
            } else {
                window.location.href = "home.html";
            }
        })
        .catch(err => console.error("Session check error:", err));
}

document.addEventListener("DOMContentLoaded", initLayout);