<?php

declare(strict_types=1);

use PDO;
use WonderOS\Api\EntityApi;
use WonderOS\Knowledge\Entity\PdoEntityRepository;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = new PDO((string) getenv('DATABASE_DSN'), (string) getenv('DATABASE_USER'), (string) getenv('DATABASE_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$api = new EntityApi(new PdoEntityRepository($pdo));
$response = $api->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', file_get_contents('php://input') ?: '');
http_response_code($response['status']);
echo json_encode($response['body'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
