<?php
require __DIR__ . '/../vendor/autoload.php';

use Application\App;

$app = new App();

try {
    $app->generateUrls();
} catch (Exception $e) {
    echo $e->getMessage();
}