/**
 * WaterWorld - daily_report Page Scripts
 */

function toggleReportSidebar() {
    document.getElementById('reportSidebar').classList.toggle('active');
}

function fetchDates() {
    fetch('/api/admin/daily_report.php?get_dates=true')
        .then(response => response.json())
        .then(res => {
            if (!res.success) return;
            const dates = res.data.available_dates || [];
            const ul = document.getElementById('reportDates');
            ul.innerHTML = '';
            dates.forEach(d => {
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.href = '#';
                a.textContent = d;
                a.onclick = () => {
                    window.location.href = '/WRSOMS/pages/admin/daily-report.html?date=' + d;
                };
                li.appendChild(a);
                ul.appendChild(li);
            });
        })
        .catch(error => console.error('Error fetching dates (API):', error));
}

document.addEventListener('DOMContentLoaded', function() {
    fetchDates();
    // If loaded directly as the client page, the page's own script will call loadReport
});