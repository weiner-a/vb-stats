<?php
require 'vendor/autoload.php';
use Dompdf\Dompdf;

$db = new mysqli('localhost', 'root', '', 'volleyball_stats');

$match_id = intval($_POST['match_id'] ?? 0);
if (!$match_id) {
    die("Nie podano id meczu.");
}

$players = $db->query("SELECT * FROM players")->fetch_all(MYSQLI_ASSOC);

$html = '<h1>Statystyki meczu #' . $match_id . '</h1>';
$html .= '<table border="1" cellpadding="5" cellspacing="0">';
$html .= '<tr><th>Zawodnik</th><th>Punkty</th><th>Skuteczność ataku</th><th>Efektywność ataku</th><th>Pozytywne przyjęcia</th><th>Perfekcyjne przyjęcia</th><th>Asy serwisowe</th></tr>';

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
            SUM(CASE WHEN serve = 'as' THEN 1 ELSE 0 END) AS serve_aces
        FROM stats 
        WHERE player_id=$pid AND match_id=$match_id
    ");
    $s = $res->fetch_assoc();
    $totalAttacks = (int)$s['total_attacks'];
    $attacksFinished = (int)$s['attacks_finished'];
    $attacksErrors = (int)$s['attacks_errors'];
    $totalReceptions = (int)$s['total_receptions'];
    $posReceptions = (int)$s['receptions_positive'];
    $perfectReceptions = (int)$s['receptions_perfect'];
    $serveAces = (int)$s['serve_aces'];

    if (!$s || array_sum($s) == 0) continue;

    $percent = fn($part,$total) => $total > 0 ? round(($part/$total)*100,1).'%' : '-';
    $efficiency = fn($finished,$errors,$total) => $total > 0 ? round((($finished-$errors)/$total)*100,1).'%' : '-';

    $playerName = htmlspecialchars($p['first_name'] . ' ' . $p['last_name']);

    $html .= "<tr>
        <td>{$playerName}</td>
        <td>{$s['total_points']}</td>
        <td>" . ($totalAttacks > 0 ? $percent($attacksFinished, $totalAttacks) : '-') . "</td>
        <td>" . ($totalAttacks > 0 ? $efficiency($attacksFinished,$attacksErrors,$totalAttacks) : '-') . "</td>
        <td>" . ($totalReceptions > 0 ? $percent($posReceptions, $totalReceptions) : '-') . "</td>
        <td>" . ($totalReceptions > 0 ? $percent($perfectReceptions, $totalReceptions) : '-') . "</td>
        <td>" . ($serveAces > 0 ? $serveAces : '-') . "</td>
    </tr>";
}
$html .= '</table>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("stats_match_{$match_id}.pdf");
exit;
