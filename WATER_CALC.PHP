<?php
require_once 'connect.php';

// === CONFIGURATION ===
define('SELLING_PRICE_PER_GALLON', 50.00); // PHP per container
$cost_per_container = [
    1 => 25.00, // Round
    2 => 20.00  // Slim
];
define('GALLONS_PER_CUBIC_METER', 264.172);

// Fetch all water orders
$sql = "
    SELECT 
        o.order_date,
        od.quantity,
        od.container_id,
        c.customer_id,
        CONCAT(c.first_name, ' ', c.last_name) AS name
    FROM orders o
    JOIN order_details od ON o.reference_id = od.reference_id
    JOIN customers c ON o.customer_id = c.customer_id
    ORDER BY o.order_date
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) die("<h3>No data. Run test SQL first.</h3>");

// === CORE METRICS ===
$total_gallons = array_sum(array_column($rows, 'quantity'));
$total_revenue = $total_gallons * SELLING_PRICE_PER_GALLON;

$total_cost = 0;
foreach ($rows as $r) {
    $cost = $cost_per_container[$r['container_id']] ?? 25.00;
    $total_cost += $r['quantity'] * $cost;
}
$total_profit = $total_revenue - $total_cost;

// === CUBIC METER ANALYSIS ===
$total_cubic_meters = $total_gallons / GALLONS_PER_CUBIC_METER;
$revenue_per_m3 = $total_revenue / max(1, $total_cubic_meters);
$cost_per_m3 = $total_cost / max(1, $total_cubic_meters);
$profit_per_m3 = $revenue_per_m3 - $cost_per_m3;

$unique_days = count(array_unique(array_map(fn($r) => date('Y-m-d', strtotime($r['order_date'])), $rows)));
$avg_daily = $unique_days > 0 ? round($total_gallons / $unique_days, 1) : 0;

// === HEATMAP ===
$heatmap = array_fill(0, 7, array_fill(0, 24, 0));
foreach ($rows as $r) {
    $dow = date('w', strtotime($r['order_date']));
    $hour = date('G', strtotime($r['order_date']));
    $heatmap[$dow][$hour] += $r['quantity'];
}

// === REFILL CYCLE ===
$cycles = [];
foreach ($rows as $r) {
    $cid = $r['customer_id'];
    $date = $r['order_date'];
    $cycles[$cid][] = $date;
}
$refill_days = [];
foreach ($cycles as $dates) {
    sort($dates);
    for ($i = 1; $i < count($dates); $i++) {
        $diff = (strtotime($dates[$i]) - strtotime($dates[$i-1])) / 86400;
        $refill_days[] = round($diff, 1);
    }
}
$avg_refill = !empty($refill_days) ? round(array_sum($refill_days)/count($refill_days), 1) : 0;

// === CUSTOMER SEGMENTATION ===
$per_customer = [];
foreach ($rows as $r) {
    $per_customer[$r['customer_id']]['name'] = $r['name'];
    $per_customer[$r['customer_id']]['gallons'] = ($per_customer[$r['customer_id']]['gallons'] ?? 0) + $r['quantity'];
}
foreach ($per_customer as &$c) {
    $g = $c['gallons'];
    $c['segment'] = $g >= 100 ? 'Heavy' : ($g >= 50 ? 'Regular' : 'Light');
}
$segments = ['Heavy' => 0, 'Regular' => 0, 'Light' => 0];
foreach ($per_customer as $c) $segments[$c['segment']]++;

// === ANOMALY DETECTION (SAFE) ===
$daily = [];
foreach ($rows as $r) {
    $day = date('Y-m-d', strtotime($r['order_date']));
    $daily[$day] = ($daily[$day] ?? 0) + $r['quantity'];
}
$values = array_values($daily);
function safe_std_dev($arr) {
    $n = count($arr);
    if ($n <= 1) return 0;
    $mean = array_sum($arr) / $n;
    $sum = 0;
    foreach ($arr as $v) $sum += pow($v - $mean, 2);
    return sqrt($sum / ($n - 1));
}
$mean = array_sum($values) / count($values);
$std = safe_std_dev($values);
$anomalies = [];
if ($std > 0) {
    foreach ($daily as $date => $gal) {
        $z = abs($gal - $mean) / $std;
        if ($z > 2.5) $anomalies[] = ['date' => $date, 'gallons' => $gal, 'z' => round($z, 2)];
    }
}

// === 7-DAY FORECAST ===
$alpha = 0.3;
$forecast = [];
$last = null;
$daily_dates = array_keys($daily);
foreach ($daily as $date => $gal) {
    $last = $last === null ? $gal : $alpha * $gal + (1 - $alpha) * $last;
    $forecast[$date] = round($last, 1);
}
$next7 = [];
$last_date = end($daily_dates);
$last_val = end($forecast);
for ($i = 1; $i <= 7; $i++) {
    $next = date('Y-m-d', strtotime($last_date . " +1 day"));
    $next7[$next] = $last_val;
    $last_date = $next;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Consumption & Revenue Intelligence</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/luxon"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-luxon"></script>
    <style>
        :root { --primary: #007bff; --success: #28a745; --warning: #ffc107; --danger: #dc3545; --profit: #1e7e34; --loss: #c82333; }
        body { font-family: 'Segoe UI', sans-serif; background: #f8f9fa; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 1350px; margin: auto; }
        h1 { text-align: center; color: var(--primary); margin-bottom: 8px; }
        .subtitle { text-align: center; color: #666; font-style: italic; margin-bottom: 30px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .card h3 { margin: 0 0 15px; color: var(--primary); border-bottom: 2px solid var(--primary); padding-bottom: 8px; }
        .stat { font-size: 1.9em; font-weight: bold; }
        .profit { color: var(--profit); }
        .loss { color: var(--loss); }
        .label { color: #666; font-size: 0.9em; }
        .heatmap { display: grid; grid-template-columns: repeat(25, 1fr); gap: 2px; margin-top: 10px; }
        .cell { width: 100%; height: 20px; background: #e9ecef; border-radius: 2px; }
        .segment { padding: 8px 12px; margin: 5px; border-radius: 6px; color: white; font-weight: bold; font-size: 0.9em; }
        .heavy { background: var(--danger); }
        .regular { background: var(--warning); color: #000; }
        .light { background: var(--success); }
        .back { text-align: center; margin: 40px 0; }
        .back a { padding: 12px 30px; background: var(--primary); color: white; text-decoration: none; border-radius: 8px; font-weight: bold; }
        .back a:hover { background: #0056b3; }
    </style>
</head>
<body>

<div class="container">
    <h1>Water Consumption & Revenue Intelligence</h1>
    <p class="subtitle">Consumption + Profit per Cubic Meter</p>

    <!-- KPI: Consumption -->
    <div class="grid">
        <div class="card"><h3>Total Gallons</h3><div class="stat"><?php echo number_format($total_gallons); ?></div><div class="label">1 container = 1 gallon</div></div>
        <div class="card"><h3>Avg Daily</h3><div class="stat"><?php echo $avg_daily; ?></div><div class="label">gallons/day</div></div>
        <div class="card"><h3>Refill Cycle</h3><div class="stat"><?php echo $avg_refill; ?></div><div class="label">days between orders</div></div>
        <div class="card"><h3>Anomalies</h3><div class="stat"><?php echo count($anomalies); ?></div><div class="label">demand spikes</div></div>
    </div>

    <!-- KPI: Revenue & Profit per m³ -->
    <div class="grid">
        <div class="card">
            <h3>Total Revenue</h3>
            <div class="stat profit">PHP <?php echo number_format($total_revenue, 2); ?></div>
            <div class="label">at PHP <?php echo SELLING_PRICE_PER_GALLON; ?>/gal</div>
        </div>
        <div class="card">
            <h3>Total Cost</h3>
            <div class="stat loss">PHP <?php echo number_format($total_cost, 2); ?></div>
            <div class="label">Round: PHP 25, Slim: PHP 20</div>
        </div>
        <div class="card">
            <h3>Net Profit</h3>
            <div class="stat <?php echo $total_profit >= 0 ? 'profit' : 'loss'; ?>">PHP <?php echo number_format($total_profit, 2); ?></div>
            <div class="label">Revenue − Cost</div>
        </div>
        <div class="card">
            <h3>Profit per m³</h3>
            <div class="stat <?php echo $profit_per_m3 >= 0 ? 'profit' : 'loss'; ?>">PHP <?php echo number_format($profit_per_m3, 2); ?></div>
            <div class="label">1 m³ = 264.172 gal</div>
        </div>
    </div>

    <!-- Heatmap + Segments -->
    <div class="grid">
        <div class="card">
            <h3>Peak Demand Heatmap</h3>
            <div class="heatmap">
                <div class="cell"></div>
                <?php for($h=0;$h<24;$h++): ?><div class="cell" style="grid-column:<?php echo $h+2; ?>;font-size:0.7em;text-align:center;"><?php echo $h; ?></div><?php endfor; ?>
                <?php 
                $days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
                $all_vals = [];
                for($d=0;$d<7;$d++) for($h=0;$h<24;$h++) $all_vals[] = $heatmap[$d][$h];
                $max = $all_vals ? max($all_vals) : 1;
                for($d=0;$d<7;$d++): ?>
                    <div class="cell" style="grid-row:<?php echo $d+2; ?>;grid-column:1;font-size:0.8em;"><?php echo $days[$d]; ?></div>
                    <?php for($h=0;$h<24;$h++): 
                        $val = $heatmap[$d][$h];
                        $intensity = $max > 0 ? $val / $max : 0;
                        $color = $intensity > 0.7 ? '#dc3545' : ($intensity > 0.4 ? '#ffc107' : ($intensity > 0.1 ? '#28a745' : '#e9ecef'));
                    ?>
                        <div class="cell" style="background:<?php echo $color; ?>;grid-row:<?php echo $d+2; ?>;grid-column:<?php echo $h+2; ?>" title="<?php echo $val; ?> gal"></div>
                    <?php endfor; ?>
                <?php endfor; ?>
            </div>
        </div>

        <div class="card">
            <h3>Customer Segments</h3>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;">
                <?php foreach($segments as $seg => $count): ?>
                    <div class="segment <?php echo strtolower($seg); ?>">
                        <?php echo $seg; ?>: <?php echo $count; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <canvas id="segmentChart" height="180" style="margin-top:15px;"></canvas>
        </div>
    </div>

    <!-- Anomalies & Forecast -->
    <div class="grid">
        <div class="card">
            <h3>Demand Anomalies</h3>
            <?php if($anomalies): ?>
                <ul style="margin:10px 0; padding-left:20px; font-size:0.9em;">
                    <?php foreach($anomalies as $a): ?>
                        <li style="color:#dc3545;"><?php echo $a['date']; ?>: <strong><?php echo $a['gallons']; ?> gal</strong> (z: <?php echo $a['z']; ?>)</li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No anomalies.</p>
            <?php endif; ?>
        </div>
        <div class="card">
            <h3>7-Day Demand Forecast</h3>
            <canvas id="forecastChart" height="180"></canvas>
        </div>
    </div>

    <div class="back">
        <a href="admin_dashboard.php">Back to Admin</a>
    </div>
</div>

<script>
// Segment Pie
new Chart(document.getElementById('segmentChart'), {
    type: 'doughnut',
    data: {
        labels: ['Heavy', 'Regular', 'Light'],
        datasets: [{
            data: [<?php echo $segments['Heavy']; ?>, <?php echo $segments['Regular']; ?>, <?php echo $segments['Light']; ?>],
            backgroundColor: ['#dc3545', '#ffc107', '#28a745']
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

// Forecast
const allDates = <?php echo json_encode(array_merge(array_keys($daily), array_keys($next7))); ?>;
const allData = <?php echo json_encode(array_merge(array_values($forecast), array_values($next7))); ?>;
const splitIdx = <?php echo count($daily); ?>;

new Chart(document.getElementById('forecastChart'), {
    type: 'line',
    data: {
        labels: allDates,
        datasets: [
            { label: 'Actual', data: allData.map((v,i) => i < splitIdx ? v : null), borderColor: '#007bff', backgroundColor: 'rgba(0,123,255,0.1)', fill: true },
            { label: 'Forecast', data: allData.map((v,i) => i >= splitIdx ? v : null), borderColor: '#28a745', borderDash: [5,5], fill: false }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: { x: { type: 'time', time: { unit: 'day' } } }
    }
});
</script>

</body>
</html>
