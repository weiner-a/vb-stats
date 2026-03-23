<?php
// export_csv.php
$db = new mysqli('localhost', 'root', '', 'volleyball_stats');

function zamienPolskieZnaki($text) {
    $polskie = ['ą', 'ć', 'ę', 'ł', 'ń', 'ó', 'ś', 'ź', 'ż', 'Ą', 'Ć', 'Ę', 'Ł', 'Ń', 'Ó', 'Ś', 'Ź', 'Ż'];
    $bezPolskich = ['a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z', 'A', 'C', 'E', 'L', 'N', 'O', 'S', 'Z', 'Z'];
    return str_replace($polskie, $bezPolskich, $text);
}

$match_id = intval($_POST['match_id'] ?? 0);
if (!$match_id) {
    die("Nie podano id meczu.");
}

$players = $db->query("SELECT * FROM players")->fetch_all(MYSQLI_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=match_stats_' . $match_id . '.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['Zawodnik','Punkty','Skuteczność ataku','Efektywność ataku','Pozytywne przyjęcia','Perfekcyjne przyjęcia','Efektywność przyjęcia','Asy serwisowe','Skuteczność zagrywki','Efektywność zagrywki','Bloki','Wybloki','Błędy własne']);

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
        WHERE player_id=$pid AND match_id=$match_id
    ");
    $s = $res->fetch_assoc();

    if (!$s || array_sum($s) == 0) continue;

    $totalAttacks = (int)$s['total_attacks'];
    $attacksFinished = (int)$s['attacks_finished'];
    $attacksErrors = (int)$s['attacks_errors'];
    $totalReceptions = (int)$s['total_receptions'];
    $posReceptions = (int)$s['receptions_positive'];
    $perfectReceptions = (int)$s['receptions_perfect'];
    $totalServes = (int)$s['total_serves'];
    $serveAces = (int)$s['serve_aces'];
    $serveErrors = (int)$s['serves_errors'];

    $percent = fn($part,$total) => $total > 0 ? round(($part/$total)*100,1).'%' : '-';
    $efficiency = fn($finished,$errors,$total) => $total > 0 ? round((($finished-$errors)/$total)*100,1).'%' : '-';

    $imieNazwisko = zamienPolskieZnaki($p['first_name'] . ' ' . $p['last_name']);

    fputcsv($output, [
        $imieNazwisko,
        (int)$s['total_points'],
        $totalAttacks > 0 ? $percent($attacksFinished, $totalAttacks) : '-',
        $totalAttacks > 0 ? $efficiency($attacksFinished,$attacksErrors,$totalAttacks) : '-',
        $totalReceptions > 0 ? $percent($posReceptions, $totalReceptions) : '-',
        $totalReceptions > 0 ? $percent($perfectReceptions, $totalReceptions) : '-',
        $totalReceptions > 0 ? round((($posReceptions-((int)$s['receptions_negative'] ?? 0))/ $totalReceptions)*100,1).'%' : '-',
        $serveAces > 0 ? $serveAces : '-',
        $totalServes > 0 ? $percent($serveAces, $totalServes) : '-',
        $totalServes > 0 ? $efficiency($serveAces,$serveErrors,$totalServes) : '-',
        (int)$s['total_blocks'] ?: '-',
        (int)$s['total_unblocks'] ?: '-',
        (int)$s['total_own_errors'] ?: '-'
    ]);
}
fclose($output);
exit;
