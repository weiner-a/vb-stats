<?php
$db = new mysqli('localhost', 'root', '', 'volleyball_stats');

$match_id = isset($_GET['match_id']) ? intval($_GET['match_id']) : 0;
if (!$match_id) {
    die("Nie podano identyfikatora meczu.");
}

// Pobranie wszystkich zawodników
$players = $db->query("SELECT * FROM players")->fetch_all(MYSQLI_ASSOC);

// Pobranie całkowitej liczby błędów przeciwnika z tabeli matches
$matchStats = $db->query("SELECT opponent_errors_count FROM matches WHERE id = $match_id");
$opponentErrorsRow = $matchStats->fetch_assoc();
$opponentErrorsCount = $opponentErrorsRow ? (int)$opponentErrorsRow['opponent_errors_count'] : 0;

// NOWE - Statystyki rozegrania dla każdego rozgrywającego
$settingStatsByPlayer = [];
$res = $db->query("
    SELECT 
        player_id,
        p.first_name,
        p.last_name,
        SUM(CASE WHEN setting = 'perfekcyjne' THEN 1 ELSE 0 END) AS setting_perfekcyjne,
        SUM(CASE WHEN setting = 'grywalne' THEN 1 ELSE 0 END) AS setting_grywalne,
        SUM(CASE WHEN setting = 'utrudniające' THEN 1 ELSE 0 END) AS setting_utrudniajace,
        SUM(CASE WHEN setting = 'blad' THEN 1 ELSE 0 END) AS setting_blad,
        SUM(CASE WHEN setting IS NOT NULL THEN 1 ELSE 0 END) AS total_setting
    FROM stats s
    LEFT JOIN players p ON s.player_id = p.id
    WHERE match_id = $match_id AND setting IS NOT NULL
    GROUP BY player_id
    HAVING total_setting > 0
");
while ($row = $res->fetch_assoc()) {
    $settingStatsByPlayer[$row['player_id']] = $row;
}

$statsData = [];
$noStatsPlayers = [];
foreach ($players as $p) {
    $pid = $p['id'];
    $res = $db->query("
        SELECT
            SUM(points) AS total_points,
            SUM(CASE WHEN attack IS NOT NULL THEN 1 ELSE 0 END) AS total_attacks,
            SUM(CASE WHEN attack = 'skończony' THEN 1 ELSE 0 END) AS attacks_finished,
            SUM(CASE WHEN attack = 'blad' THEN 1 ELSE 0 END) AS attacks_errors,
            SUM(CASE WHEN reception IS NOT NULL THEN 1 ELSE 0 END) AS total_receptions,
            SUM(CASE WHEN reception IN ('perfekcyjne','pozytywne') THEN 1 ELSE 0 END) AS receptions_positive,
            SUM(CASE WHEN reception = 'perfekcyjne' THEN 1 ELSE 0 END) AS receptions_perfect,
            SUM(CASE WHEN serve IS NOT NULL THEN 1 ELSE 0 END) AS total_serves,
            SUM(CASE WHEN serve = 'as' THEN 1 ELSE 0 END) AS serve_aces,
            SUM(CASE WHEN serve = 'blad' THEN 1 ELSE 0 END) AS serves_errors,
            SUM(defense) AS total_defense,
            SUM(block) AS total_blocks,
            SUM(unblock) AS total_unblocks,
            SUM(error) AS total_own_errors
        FROM stats 
        WHERE player_id = $pid AND match_id = $match_id
    ");
    $stats = $res->fetch_assoc();

    $hasStats = false;
    if ($stats) {
        foreach ($stats as $key => $value) {
            if ($value > 0) {
                $hasStats = true;
                break;
            }
        }
    }
    if ($hasStats) {
        $statsData[$pid] = $stats;
    } else {
        $noStatsPlayers[] = $p;
    }
}

function percent($part, $total) {
    return $total > 0 ? round(($part / $total) * 100, 1) . '%' : '-';
}

function efficiency($finished, $errors, $total) {
    if ($total === 0) return '-';
    return round((($finished - $errors) / $total) * 100, 1) . '%';
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Podsumowanie statystyk meczu</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<h2>Podsumowanie statystyk meczu</h2>

<table>
<tr>
<th>Zawodnik</th>
<th>Ilość punktów</th>
<th>Skuteczność ataku</th>
<th>Efektywność ataku</th>
<th>Pozytywne przyjęcie</th>
<th>Perfekcyjne przyjęcie</th>
<th>Efektywność przyjęcia</th>
<th>Ilość asów serwisowych</th>
<th>Skuteczność zagrywki</th>
<th>Efektywność zagrywki</th>
<th>Ilość bloków</th>
<th>Ilość wybloków</th>
<th>Ilość błędów własnych</th>
</tr>

<?php foreach ($statsData as $pid => $s):
    $p = null;
    foreach ($players as $player) {
        if ($player['id'] == $pid) {
            $p = $player;
            break;
        }
    }
    if (!$p) continue;

    $totalAttacks = (int)$s['total_attacks'];
    $attacksFinished = (int)$s['attacks_finished'];
    $attacksErrors = (int)$s['attacks_errors'];

    $totalReceptions = (int)$s['total_receptions'];
    $posReceptions = (int)$s['receptions_positive'];
    $perfectReceptions = (int)$s['receptions_perfect'];

    $totalServes = (int)$s['total_serves'];
    $serveAces = (int)$s['serve_aces'];
    $serveErrors = (int)$s['serves_errors'];

    $ownErrors = (int)$s['total_own_errors'];
    $totalDefense = (int)$s['total_defense'];

    $receptionEfficiency = $totalReceptions > 0 ? round((($posReceptions - ((int)@$s['receptions_negative'] ?? 0)) / $totalReceptions) * 100, 1) . '%' : '-';
?>
<tr>
<td><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></td>
<td><?php echo (int)$s['total_points']; ?></td>
<td><?php echo $totalAttacks > 0 ? percent($attacksFinished, $totalAttacks) : '-'; ?></td>
<td><?php echo $totalAttacks > 0 ? efficiency($attacksFinished, $attacksErrors, $totalAttacks) : '-'; ?></td>
<td><?php echo $totalReceptions > 0 ? percent($posReceptions, $totalReceptions) : '-'; ?></td>
<td><?php echo $totalReceptions > 0 ? percent($perfectReceptions, $totalReceptions) : '-'; ?></td>
<td><?php echo $totalReceptions > 0 ? $receptionEfficiency : '-'; ?></td>
<td><?php echo $serveAces > 0 ? $serveAces : '-'; ?></td>
<td><?php echo $totalServes > 0 ? percent($serveAces, $totalServes) : '-'; ?></td>
<td><?php echo $totalServes > 0 ? efficiency($serveAces, $serveErrors, $totalServes) : '-'; ?></td>
<td><?php echo (int)$s['total_blocks'] ?: '-'; ?></td>
<td><?php echo (int)$s['total_unblocks'] ?: '-'; ?></td>
<td><?php echo $ownErrors > 0 ? $ownErrors : '-'; ?></td>
</tr>

<tr>
<td colspan="13" style="text-align:left; padding: 10px 15px; background: #f4f4f4;">
<?php
    if ($totalAttacks > 0) {
        echo "Atak: {$attacksFinished} / {$totalAttacks}<br>";
    }
    if ($totalReceptions > 0) {
        echo "Przyjęcie pozytywne: {$posReceptions} / {$totalReceptions}<br>";
        echo "Przyjęcie perfekcyjne: {$perfectReceptions} / {$totalReceptions}<br>";
    }
    if ($serveAces > 0) {
        echo "Ilość asów serwisowych: {$serveAces}<br>";
    }
    if ($totalDefense > 0) {
        echo "Ilość obron: {$totalDefense}<br>";
    }
?>
</td>
</tr>

<?php endforeach; ?>
</table>

<!-- NOWE - Statystyki rozegrania dla każdego rozgrywającego -->
<?php if (!empty($settingStatsByPlayer)): ?>
<div style="margin: 20px 0;">
    <h3 style="color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px;">📊 Statystyki rozegrania</h3>
    <?php foreach ($settingStatsByPlayer as $pid => $setting): ?>
    <div style="margin: 15px 0; padding: 15px; background: #e8f4f8; border-radius: 8px; border-left: 5px solid #3498db;">
        <h4 style="margin-top: 0; color: #2c3e50;">
            <?php echo htmlspecialchars($setting['first_name'] . ' ' . $setting['last_name']); ?>
            <span style="font-size: 0.9em; color: #7f8c8d;">(<?php echo $setting['total_setting']; ?> rozegranych)</span>
        </h4>
        <p><strong>Perfekcyjne:</strong> <?php echo $setting['setting_perfekcyjne']; ?> / <?php echo $setting['total_setting']; ?> (<?php echo percent($setting['setting_perfekcyjne'], $setting['total_setting']); ?>)</p>
        <p><strong>Grywalne:</strong> <?php echo $setting['setting_grywalne']; ?> / <?php echo $setting['total_setting']; ?> (<?php echo percent($setting['setting_grywalne'], $setting['total_setting']); ?>)</p>
        <p><strong>Utrudniające:</strong> <?php echo $setting['setting_utrudniajace']; ?> / <?php echo $setting['total_setting']; ?> (<?php echo percent($setting['setting_utrudniajace'], $setting['total_setting']); ?>)</p>
        <p><strong>Błędy:</strong> <?php echo $setting['setting_blad']; ?> / <?php echo $setting['total_setting']; ?> (<?php echo percent($setting['setting_blad'], $setting['total_setting']); ?>)</p>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<p><strong>Ilość błędów przeciwnika w meczu:</strong> <?php echo $opponentErrorsCount; ?></p>

<?php if (!empty($noStatsPlayers)): ?>
    <h3>Zawodnicy bez zanotowanych statystyk:</h3>
    <ul>
    <?php foreach ($noStatsPlayers as $p): ?>
        <li><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></li>
    <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post" action="export_csv.php" style="display:inline;">
  <input type="hidden" name="match_id" value="<?php echo $match_id; ?>">
  <button type="submit">Eksportuj do CSV</button>
</form>

</body>
</html>
