<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/connect.php';

// Require admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT water_type_id, type_name, description FROM water_types ORDER BY water_type_id");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['type_name'])) {
            http_response_code(400);
            echo json_encode(['success'=>false,'message'=>'type_name is required']);
            exit;
        }
        $type_name = trim($data['type_name']);
        $description = isset($data['description']) ? trim($data['description']) : null;

        if (isset($data['water_type_id']) && $data['water_type_id']) {
            // update
            $stmt = $pdo->prepare("UPDATE water_types SET type_name = ?, description = ? WHERE water_type_id = ?");
            $stmt->execute([$type_name, $description, $data['water_type_id']]);
            echo json_encode(['success'=>true,'message'=>'Updated']);
            exit;
        } else {
            // create
            $stmt = $pdo->prepare("INSERT INTO water_types (type_name, description) VALUES (?, ?)");
            $stmt->execute([$type_name, $description]);
            echo json_encode(['success'=>true,'message'=>'Created','water_type_id'=>$pdo->lastInsertId()]);
            exit;
        }
    }

    if ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['water_type_id'])) {
            http_response_code(400);
            echo json_encode(['success'=>false,'message'=>'water_type_id is required']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM water_types WHERE water_type_id = ?");
        $stmt->execute([$data['water_type_id']]);
        echo json_encode(['success'=>true,'message'=>'Deleted']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Method not allowed']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}

?>
