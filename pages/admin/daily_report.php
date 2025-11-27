<?php
// This legacy file has been removed. The admin interface now uses:
// - /WRSOMS/pages/admin/daily-report.html (client page)
// - /WRSOMS/api/admin/daily_report.php (API endpoint)

// Return a 410 Gone status to indicate the resource was removed.
http_response_code(410);
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Daily Report removed</title></head><body style="font-family:Inter,Arial,Helvetica,sans-serif;padding:24px;">
<h2>Daily Report page removed</h2>
<p>This legacy page has been removed. Use the new report page:</p>
<p><a href="/WRSOMS/pages/admin/daily-report.html">Open Daily Report</a></p>
<p>Or access the JSON API at <code>/WRSOMS/api/admin/daily_report.php</code>.</p>
</body></html>
