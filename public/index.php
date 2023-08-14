<?php
require '../vendor/autoload.php';

use Application\App;

$app = new App();

function echoStats($stats)
{
    foreach ($stats as $stat) {
        echo "Количество: {$stat['cnt']} Минута: {$stat['minute']} Средняя длинна контента {$stat['avg_content']}<br/>";
    }

    $firstMinute = min(array_column($stats, 'minute'));
    $lastMinute = max(array_column($stats, 'minute'));

    echo "<br/>Первая минута {$firstMinute} Последняя минута {$lastMinute}";
}

try {
    echo "Статистика из mariaDB:<br/>";
    $stats = $app->getStat();
    foreach ($stats as $stat) {
        echo "Количество: {$stat['cnt']} Минута: {$stat['minute']} Средняя длинна контента {$stat['avg_content']}<br/>";
    }

    $firstMinute = min(array_column($stats, 'minute'));
    $lastMinute = max(array_column($stats, 'minute'));

    echo "<br/>Первая минута {$firstMinute} Последняя минута {$lastMinute}";

    echo "<br/><br/><br/>";

    echo "Статистика из ClickHouse:<br/>";
    $stats = $app->getStatCH();

    echoStats($stats);


} catch (Exception $e) {
}