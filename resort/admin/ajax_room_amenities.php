<?php
require_once __DIR__ . '/../includes/config.php';
requireAdminLogin();
header('Content-Type: application/json');
$room_id = (int)($_GET['room_id'] ?? 0);
$ids = [];
if ($room_id > 0) {
    $db = getDB();
    $res = $db->query("SELECT amenity_id FROM room_amenities WHERE room_id=$room_id");
    while ($r = $res->fetch_assoc()) $ids[] = (int)$r['amenity_id'];
}
echo json_encode($ids);
