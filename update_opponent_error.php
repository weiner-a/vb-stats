<?php
$db = new mysqli('localhost', 'root', '', 'volleyball_stats');
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$match_id = intval($data['match_id'] ?? 0);

if (!$match_id) {
    echo json_encode(['success' => false, 'error' => 'Brak ID meczu']);
    exit;
}

// Aktualizacja licznika błędów przeciwnika w tabeli matches
$sql = "UPDATE matches SET opponent_errors_count = opponent_errors_count + 1 WHERE id = $match_id";
if ($db->query($sql)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $db->error]);
}
