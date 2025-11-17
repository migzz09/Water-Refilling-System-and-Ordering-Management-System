
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../../config/connect.php';

// Ensure containers table has a purchase_price column to support per-container purchase pricing
try {
    $pdo->exec("ALTER TABLE containers ADD COLUMN IF NOT EXISTS purchase_price decimal(10,2) DEFAULT NULL");
} catch (Exception $e) {
    // ignore - some MySQL/MariaDB older versions may not support IF NOT EXISTS, try safe alter
    try {
        $pdo->exec("ALTER TABLE containers ADD COLUMN purchase_price decimal(10,2) DEFAULT NULL");
    } catch (Exception $e2) {
        // ignore if fails; later selects will simply not include column
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Toggle visibility (launch/hide) for a container
    if (isset($_POST['container_id']) && isset($_POST['is_visible'])) {
        $containerId = (int)$_POST['container_id'];
        $isVisible = (int)$_POST['is_visible'];
        $stmt = $pdo->prepare("UPDATE containers SET is_visible = ? WHERE container_id = ?");
        $stmt->execute([$isVisible, $containerId]);
        echo json_encode(['success' => true, 'message' => 'Visibility updated']);
        exit;
    }

    // Accept both 'photo' and 'image' for file upload (CREATE)
    if (isset($_POST['container_type']) && isset($_POST['price']) && !isset($_POST['container_id'])) {
        $containerType = $_POST['container_type'];
        $price = (float)$_POST['price'];
        $stock = isset($_POST['stock_quantity']) ? (int)$_POST['stock_quantity'] : 0;
        $purchase_price = isset($_POST['purchase_price']) ? (float)$_POST['purchase_price'] : null;
        $image = null;
        $newFileName = null;
        if (isset($_FILES['photo'])) {
            $image = $_FILES['photo'];
        } elseif (isset($_FILES['image'])) {
            $image = $_FILES['image'];
        }

        // Allow any container type (no strict validation)

        if ($image) {
            // Handle image upload
            $targetDir = __DIR__ . '/../../assets/images/';
            $imageFileType = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'svg'];
            if (!in_array($imageFileType, $allowedTypes)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid image type']);
                exit;
            }
            $newFileName = uniqid('container_', true) . '.' . $imageFileType;
            $targetFile = $targetDir . $newFileName;
            if (!move_uploaded_file($image['tmp_name'], $targetFile)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
                exit;
            }
        }

        // Insert new container into database with photo filename and default is_visible=1
        // Insert new container including optional purchase_price
        if ($purchase_price !== null) {
            $stmt = $pdo->prepare("INSERT INTO containers (container_type, price, photo, is_visible, purchase_price) VALUES (?, ?, ?, 1, ?)");
            $stmt->execute([$containerType, $price, $newFileName, $purchase_price]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO containers (container_type, price, photo, is_visible) VALUES (?, ?, ?, 1)");
            $stmt->execute([$containerType, $price, $newFileName]);
        }
        $containerId = $pdo->lastInsertId();

        // Add to inventory with initial stock
        $stmt2 = $pdo->prepare("INSERT INTO inventory (container_id, container_type, stock) VALUES (?, ?, ?)");
        $stmt2->execute([$containerId, $containerType, $stock]);

        echo json_encode(['success' => true, 'message' => 'Container added successfully', 'container_id' => $containerId, 'image' => $newFileName]);
        exit;
    }

    // UPDATE existing container (change type, price, photo)
    if (isset($_POST['container_id']) && (isset($_POST['container_type']) || isset($_POST['price']) || isset($_FILES['photo']) || isset($_FILES['image']))) {
        $containerId = (int)$_POST['container_id'];
        // fetch existing photo
        $stmt = $pdo->prepare("SELECT photo FROM containers WHERE container_id = ?");
        $stmt->execute([$containerId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $containerType = isset($_POST['container_type']) ? $_POST['container_type'] : null;
        $price = isset($_POST['price']) ? (float)$_POST['price'] : null;
        $purchase_price = isset($_POST['purchase_price']) ? (float)$_POST['purchase_price'] : null;
        $image = null;
        $newFileName = null;
        if (isset($_FILES['photo'])) {
            $image = $_FILES['photo'];
        } elseif (isset($_FILES['image'])) {
            $image = $_FILES['image'];
        }

        if ($image) {
            $targetDir = __DIR__ . '/../../assets/images/';
            $imageFileType = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'svg'];
            if (!in_array($imageFileType, $allowedTypes)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid image type']);
                exit;
            }
            $newFileName = uniqid('container_', true) . '.' . $imageFileType;
            $targetFile = $targetDir . $newFileName;
            if (!move_uploaded_file($image['tmp_name'], $targetFile)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
                exit;
            }
            // remove old
            if (!empty($existing['photo'])) {
                $oldPath = $targetDir . $existing['photo'];
                if (file_exists($oldPath)) @unlink($oldPath);
            }
        }

        // build update
        $fields = [];
        $params = [];
        if ($containerType !== null) { $fields[] = 'container_type = ?'; $params[] = $containerType; }
        if ($price !== null) { $fields[] = 'price = ?'; $params[] = $price; }
        if ($newFileName !== null) { $fields[] = 'photo = ?'; $params[] = $newFileName; }
        if ($purchase_price !== null) { $fields[] = 'purchase_price = ?'; $params[] = $purchase_price; }
        if (!empty($fields)) {
            $params[] = $containerId;
            $sql = "UPDATE containers SET " . implode(', ', $fields) . " WHERE container_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        if ($containerType !== null) {
            $stmt = $pdo->prepare("UPDATE inventory SET container_type = ? WHERE container_id = ?");
            $stmt->execute([$containerType, $containerId]);
        }

        echo json_encode(['success' => true, 'message' => 'Container updated successfully']);
        exit;
    }

    // DELETE container
    if (isset($_POST['container_id']) && isset($_POST['delete']) && $_POST['delete'] == '1') {
        $containerId = (int)$_POST['container_id'];
        $stmt = $pdo->prepare("SELECT photo FROM containers WHERE container_id = ?");
        $stmt->execute([$containerId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        // delete inventory rows first
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE container_id = ?");
        $stmt->execute([$containerId]);
        $stmt = $pdo->prepare("DELETE FROM containers WHERE container_id = ?");
        $stmt->execute([$containerId]);
        if (!empty($existing['photo'])) {
            $targetDir = __DIR__ . '/../../assets/images/';
            $oldPath = $targetDir . $existing['photo'];
            if (file_exists($oldPath)) @unlink($oldPath);
        }
        echo json_encode(['success' => true, 'message' => 'Container deleted']);
        exit;
    }

    // Update price for a container (JSON)
    $input = json_decode(file_get_contents('php://input'), true);
    $containerId = isset($input['container_id']) ? (int)$input['container_id'] : null;
    $price = isset($input['price']) ? (float)$input['price'] : null;
    $purchase_price_json = isset($input['purchase_price']) ? (float)$input['purchase_price'] : null;
    if ($containerId && ($price !== null || $purchase_price_json !== null)) {
        $fields = [];
        $params = [];
        if ($price !== null) { $fields[] = 'price = ?'; $params[] = $price; }
        if ($purchase_price_json !== null) { $fields[] = 'purchase_price = ?'; $params[] = $purchase_price_json; }
        $params[] = $containerId;
        $sql = "UPDATE containers SET " . implode(', ', $fields) . " WHERE container_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'message' => 'Price updated successfully']);
        exit;
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing container_id or price']);
        exit;
    }
}

try {
    // Get all containers with their prices and photo
    $stmt = $pdo->query("SELECT container_id, container_type, price, photo, is_visible, purchase_price FROM containers WHERE is_visible = 1 ORDER BY container_id");
    $containers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = [];
    foreach ($containers as $c) {
        $type = $c['container_type'];
        $photo = $c['photo'];
        $image = $photo ? $photo : 'placeholder.svg';
        $response[] = [
            'container_id' => (int)$c['container_id'],
            'container_type' => $type,
            'price' => (float)$c['price'],
            'purchase_price' => isset($c['purchase_price']) ? (float)$c['purchase_price'] : null,
            'image' => $image,
            'is_visible' => (int)$c['is_visible']
        ];
    }
    echo json_encode($response);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>