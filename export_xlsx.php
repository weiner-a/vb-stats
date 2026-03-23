<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$db = new mysqli('localhost', 'root', '', 'volleyball_stats');

$match_id = intval($_POST['match_id'] ?? 0);
if (!$match_id) {
    die("Nie podano id meczu.");
}

$players = $db->query("SELECT * FROM players")->fetch_all(MYSQLI_ASSOC);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$headers = ['Zawodnik','Punkty','Skuteczność ataku','Efektywność ataku','Pozytywne przyjęcia','Perfekcyjne przyjęcia','Asy serwisowe'];
$sheet->fromArray($headers, NULL, 'A1');

$row = 2;
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
    if (!$s || array_sum($s) == 0) continue;

    $totalAttacks = (int)$s['total_attacks'];
    $attacksFinished = (int)$s['attacks_finished'];
    $attacksErrors = (int)$s['attacks_errors'];
    $totalReceptions = (int)$s['total_receptions'];
    $posReceptions = (int)$s['receptions_positive'];
    $perfectReceptions = (int)$s['receptions_perfect'];
    $serveAces = (int)$s['serve_aces'];

    $percent = function($part,$total){ return $total > 0 ? round(($part/$total)*100,1) . '%' : '-'; };
    $efficiency = function($finished,$errors,$total){ return $total > 0 ? round((($finished-$errors)/$total)*100,1) . '%' : '-'; };

    $playerName = $p['first_name'] . ' ' . $p['last_name'];

    $sheet->setCellValue("A{$row}", $playerName);
    $sheet->setCellValue("B{$row}", (int)$s['total_points']);
    $sheet->setCellValue("C{$row}", $totalAttacks>0 ? $percent($attacksFinished, $totalAttacks) : '-');
    $sheet->setCellValue("D{$row}", $totalAttacks>0 ? $efficiency($attacksFinished,$attacksErrors,$totalAttacks) : '-');
    $sheet->setCellValue("E{$row}", $totalReceptions>0 ? $percent($posReceptions, $totalReceptions) : '-');
    $sheet->setCellValue("F{$row}", $totalReceptions>0 ? $percent($perfectReceptions, $totalReceptions) : '-');
    $sheet->setCellValue("G{$row}", $serveAces>0 ? $serveAces : '-');

    $row++;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="stats_match_'.$match_id.'.xlsx"');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
