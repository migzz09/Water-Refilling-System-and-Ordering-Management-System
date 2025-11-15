<?php
/**
 * Automatic Archive Script
 * Run this script via cron job at end of day (e.g., 11:59 PM)
 * 
 * Cron example: 59 23 * * * /usr/bin/php /path/to/auto_archive.php
 * Windows Task Scheduler: Run daily at 11:59 PM
 */

require_once __DIR__ . '/../../config/connect.php';

try {
    echo "[" . date('Y-m-d H:i:s') . "] Starting automatic archive process...\n";
    
    $pdo->beginTransaction();
    
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    // Archive completed orders from yesterday (to ensure day is fully complete)
    $stmt = $pdo->prepare("
        SELECT o.*, d.delivery_id, d.delivery_status_id, d.pickup_time, d.delivery_time
        FROM orders o
        INNER JOIN deliveries d ON o.order_id = d.order_id
        WHERE d.delivery_status_id = 3
        AND o.delivery_date = ?
    ");
    $stmt->execute([$yesterday]);
    $completedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $archivedOrders = 0;
    $archivedBatches = [];
    
    foreach ($completedOrders as $order) {
        // Insert into archived_orders
        $stmt = $pdo->prepare("
            INSERT INTO archived_orders 
            (order_id, reference_id, user_id, address_id, order_date, delivery_date, 
             total_amount, payment_method, payment_status, order_status_id, batch_id, 
             created_at, updated_at, archived_at, archived_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NULL)
        ");
        $stmt->execute([
            $order['order_id'],
            $order['reference_id'],
            $order['user_id'],
            $order['address_id'],
            $order['order_date'],
            $order['delivery_date'],
            $order['total_amount'],
            $order['payment_method'],
            $order['payment_status'],
            $order['order_status_id'],
            $order['batch_id'],
            $order['created_at'],
            $order['updated_at']
        ]);
        
        // Archive order items
        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->execute([$order['order_id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as $item) {
            $stmt = $pdo->prepare("
                INSERT INTO archived_order_items 
                (order_item_id, order_id, container_id, water_type_id, order_type_id, 
                 quantity, price, subtotal, archived_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $item['order_item_id'],
                $item['order_id'],
                $item['container_id'],
                $item['water_type_id'],
                $item['order_type_id'],
                $item['quantity'],
                $item['price'],
                $item['subtotal']
            ]);
        }
        
        // Archive delivery
        $stmt = $pdo->prepare("
            INSERT INTO archived_deliveries 
            (delivery_id, order_id, batch_id, delivery_status_id, pickup_time, 
             delivery_time, notes, created_at, updated_at, archived_at)
            VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, NOW())
        ");
        $stmt->execute([
            $order['delivery_id'],
            $order['order_id'],
            $order['batch_id'],
            $order['delivery_status_id'],
            $order['pickup_time'],
            $order['delivery_time'],
            $order['created_at'],
            $order['updated_at']
        ]);
        
        if ($order['batch_id']) {
            $archivedBatches[$order['batch_id']] = true;
        }
        
        // Delete from main tables
        $stmt = $pdo->prepare("DELETE FROM deliveries WHERE delivery_id = ?");
        $stmt->execute([$order['delivery_id']]);
        
        $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
        $stmt->execute([$order['order_id']]);
        
        $stmt = $pdo->prepare("DELETE FROM orders WHERE order_id = ?");
        $stmt->execute([$order['order_id']]);
        
        $archivedOrders++;
    }
    
    // Archive completed batches
    $archivedBatchCount = 0;
    foreach (array_keys($archivedBatches) as $batchId) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as remaining 
            FROM orders 
            WHERE batch_id = ?
        ");
        $stmt->execute([$batchId]);
        $check = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($check['remaining'] == 0) {
            $stmt = $pdo->prepare("SELECT * FROM batches WHERE batch_id = ?");
            $stmt->execute([$batchId]);
            $batch = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($batch) {
                $stmt = $pdo->prepare("
                    INSERT INTO archived_batches 
                    (batch_id, batch_number, batch_date, vehicle_type, capacity, 
                     total_quantity, batch_status_id, created_at, updated_at, 
                     archived_at, archived_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NULL)
                ");
                $stmt->execute([
                    $batch['batch_id'],
                    $batch['batch_number'],
                    $batch['batch_date'],
                    $batch['vehicle_type'],
                    $batch['capacity'],
                    $batch['total_quantity'],
                    $batch['batch_status_id'],
                    $batch['created_at'],
                    $batch['updated_at']
                ]);
                
                $stmt = $pdo->prepare("DELETE FROM batches WHERE batch_id = ?");
                $stmt->execute([$batchId]);
                
                $archivedBatchCount++;
            }
        }
    }
    
    // Log the archive
    $stmt = $pdo->prepare("
        INSERT INTO archive_log 
        (archive_type, orders_archived, batches_archived, archived_by, 
         date_range_start, date_range_end, notes)
        VALUES ('automatic', ?, ?, NULL, ?, ?, ?)
    ");
    $stmt->execute([
        $archivedOrders,
        $archivedBatchCount,
        $yesterday,
        $yesterday,
        "Automatic end-of-day archive"
    ]);
    
    $pdo->commit();
    
    echo "[" . date('Y-m-d H:i:s') . "] Archive complete!\n";
    echo "  - Orders archived: $archivedOrders\n";
    echo "  - Batches archived: $archivedBatchCount\n";
    echo "  - Date: $yesterday\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    error_log("Auto-archive error: " . $e->getMessage());
    exit(1);
}
?>
