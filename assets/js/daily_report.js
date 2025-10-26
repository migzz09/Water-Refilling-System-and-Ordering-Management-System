/**
 * WaterWorld - daily_report Page Scripts
 */

function toggleReportSidebar() {
    document.getElementById('reportSidebar').classList.toggle('active');
}

function fetchDates() {
    fetch('daily_report.php?get_dates=true')
        .then(response => response.json())
        .then(dates => {
            const ul = document.getElementById('reportDates');
            ul.innerHTML = '';
            dates.forEach(d => {
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.href = '#';
                a.textContent = d;
                a.onclick = () => {
                    window.location.href = 'daily_report.php?date=' + d;
                };
                li.appendChild(a);
                ul.appendChild(li);
            });
        })
        .catch(error => console.error('Error fetching dates:', error));
}

document.addEventListener('DOMContentLoaded', function() {
    fetchDates();
});

const labels = <?= json_encode($labels) ?>;
const revenueData = <?= json_encode($revenue_data) ?>;
const ordersData = <?= json_encode($orders_data) ?>;
const paymentsByMethod = <?= json_encode($payments_by_method) ?>;

new Chart(document.getElementById('revenueLine'), {
    type: 'line',
    data: { labels: labels, datasets: [{ label: 'Revenue (₱)', data: revenueData, tension:0.3, fill:true, borderColor:'#007bff', backgroundColor:'rgba(0,123,255,0.2)' }] },
    options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true, ticks:{callback:v=>'₱'+v}}} }
});

new Chart(document.getElementById('ordersBar'), {
    type: 'bar',
    data: { labels: labels, datasets: [{ label:'Orders', data: ordersData, backgroundColor:'#28a745' }] },
    options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
});

const pieLabels = paymentsByMethod.map(p=>p.method_name || 'Method');
const pieData = paymentsByMethod.map(p=>parseFloat(p.total) || 0);
new Chart(document.getElementById('paymentsPie'), {
    type:'pie',
    data:{ labels:pieLabels, datasets:[{ data:pieData, backgroundColor:['#007bff','#ffc107','#28a745','#dc3545','#6f42c1'] }] },
    options:{ responsive:true, plugins:{legend:{position:'bottom'}} }
});