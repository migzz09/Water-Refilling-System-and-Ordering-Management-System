<?php include 'connect.php'; ?>
<!DOCTYPE html>
<html>
<head>
  <title>Transaction History</title>
  <link rel="stylesheet" href="steel.css">
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f6f9fc;
      color: #333;
      margin: 0;
      padding: 20px;
    }
    .header-title { text-align: center; margin-bottom: 10px; }
    .header-actions { position: absolute; top: 15px; left: 20px; }
    .dashboard-image { width: 45px; height: 45px; cursor: pointer; }
    h3 { text-align: center; font-weight: normal; color: #555; }
    .table-container {
      margin: 20px auto;
      width: 98%;
      max-width: 1300px;
      background: white;
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      overflow: hidden;
    }
    table { width: 100%; border-collapse: collapse; }
    thead { background-color: #0099cc; color: white; }
    th, td { padding: 12px 15px; text-align: center; border-bottom: 1px solid #ddd; }
    tr:hover { background-color: #f1f1f1; }
    @media (max-width: 900px) {
      td, th { font-size: 12px; padding: 8px; }
    }
  </style>
</head>
<body>
  <div class="header-actions">
    <a href="admin_dashboard.php">
      <img src="images/admin-icon.png" alt="Admin Dashboard" class="dashboard-image">
    </a>
  </div>

  <h2 class="header-title">💧 Water World - Transaction History 💧</h2>
  <h3>Complete record of all customer transactions with product details and pricing.</h3>

  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>Order Ref</th>
          <th>Order Date</th>
          <th>Customer</th>
          <th>Order Type</th>
          <th>Container (unit price)</th>
          <th>Quantity</th>
          <th>Subtotal</th>
          <th>Order Status</th>
          <th>Batch Status</th>
          <th>Payment</th>
          <th>Delivery</th>
        </tr>
      </thead>
      <tbody>
        <?php
        try {
          // note: select od.order_detail_id so each detail row is unique
          $sql = "
            SELECT
              o.reference_id AS order_ref,
              o.order_date,
              CONCAT(c.first_name, ' ', COALESCE(c.middle_name, ''), ' ', c.last_name) AS customer_name,
              ot.type_name AS order_type,
              containers.container_type AS container_type,
              od.quantity,
              od.subtotal,
              -- compute unit price from recorded subtotal / quantity to reflect actual paid price
              (CASE WHEN od.quantity = 0 THEN 0 ELSE (od.subtotal / od.quantity) END) AS unit_price,
              os.status_name AS order_status,
              COALESCE(bs.status_name, 'N/A') AS batch_status,
              ps.status_name AS payment_status,
              ds.status_name AS delivery_status
            FROM orders o
            LEFT JOIN customers c ON o.customer_id = c.customer_id
            LEFT JOIN order_types ot ON o.order_type_id = ot.order_type_id
            LEFT JOIN order_details od ON o.reference_id = od.reference_id
            LEFT JOIN containers ON od.container_id = containers.container_id
            LEFT JOIN order_status os ON o.order_status_id = os.status_id
            LEFT JOIN batches b ON o.batch_id = b.batch_id
            LEFT JOIN batch_status bs ON b.batch_status_id = bs.batch_status_id
            LEFT JOIN payments p ON o.reference_id = p.reference_id
            LEFT JOIN payment_status ps ON p.payment_status_id = ps.payment_status_id
            LEFT JOIN deliveries d ON b.batch_id = d.batch_id
            LEFT JOIN delivery_status ds ON d.delivery_status_id = ds.delivery_status_id
            ORDER BY o.order_date DESC, o.reference_id DESC, od.order_detail_id DESC
            LIMIT 250
          ";

          $stmt = $pdo->query($sql);

          if ($stmt && $stmt->rowCount() > 0) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
              // safe extraction, fallback values
              $order_ref = htmlspecialchars($row['order_ref']);
              $order_date = htmlspecialchars($row['order_date']);
              $customer = htmlspecialchars(trim($row['customer_name']));
              $order_type = htmlspecialchars($row['order_type'] ?? 'N/A');
              $container_type = htmlspecialchars($row['container_type'] ?? 'Unknown');
              $quantity = (int)($row['quantity'] ?? 0);
              $subtotal = number_format((float)($row['subtotal'] ?? 0), 2);
              $unit_price = number_format((float)($row['unit_price'] ?? 0), 2);
              $order_status = htmlspecialchars($row['order_status'] ?? 'N/A');
              $batch_status = htmlspecialchars($row['batch_status'] ?? 'N/A');
              $payment_status = htmlspecialchars($row['payment_status'] ?? 'N/A');
              $delivery_status = htmlspecialchars($row['delivery_status'] ?? 'N/A');

              // show container type together with the actual unit price recorded for this order detail
              $container_display = $container_type . " (₱" . $unit_price . ")";

              echo "<tr>
                      <td>{$order_ref}</td>
                      <td>{$order_date}</td>
                      <td>{$customer}</td>
                      <td>{$order_type}</td>
                      <td>{$container_display}</td>
                      <td>{$quantity}</td>
                      <td>₱{$subtotal}</td>
                      <td>{$order_status}</td>
                      <td>{$batch_status}</td>
                      <td>" . ($payment_status === 'N/A' ? 'N/A' : $payment_status) . "</td>
                      <td>" . ($delivery_status === 'N/A' ? 'N/A' : $delivery_status) . "</td>
                    </tr>";
            }
          } else {
            echo "<tr><td colspan='11'>No transactions found.</td></tr>";
          }
        } catch (PDOException $e) {
          echo "<tr><td colspan='11'>Error fetching data: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
        }
        ?>
      </tbody>
    </table>
  </div>
</body>
</html>
