<?php
// update_stat.php
$db = new mysqli('localhost', 'root', '', 'volleyball_stats');
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$match_id = intval($data['match_id'] ?? 0);
$player_id = intval($data['player_id'] ?? 0);
$stat = $data['stat'] ?? '';
$value = $data['value'] ?? null;

if (!$match_id || !$stat || $value === null) {
    echo json_encode(['success' => false, 'error' => 'Nieprawidłowe dane']);
    exit;
}

$points = 0;
if ($stat === 'attack' && $value === 'skończony') $points = 1;
if ($stat === 'serve' && $value === 'as') $points = 1;

if ($stat === 'opponent_error') {
    $sql = "INSERT INTO stats (match_id, player_id, opponent_error, points) VALUES ($match_id, NULL, $value, 0)";
} else {
    if (!$player_id) {
        echo json_encode(['success' => false, 'error' => 'Brak player_id']);
        exit;
    }
    if (in_array($stat, ['defense','error','block','unblock'])) {
        $value = intval($value);
        $sql = "INSERT INTO stats (match_id, player_id, $stat, points) VALUES ($match_id, $player_id, $value, $points)";
    } else {
        $val = $db->real_escape_string($value);
        $sql = "INSERT INTO stats (match_id, player_id, $stat, points) VALUES ($match_id, $player_id, '$val', $points)";
    }
}

if ($db->query($sql)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $db->error]);
}
?>
