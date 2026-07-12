<?php

declare(strict_types=1);

use PDO;
use WonderOS\Api\EntityApi;
use WonderOS\Knowledge\Infrastructure\Persistence\PdoEntityRepository;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

$allowedOrigin = getenv('CONSOLE_ORIGIN') ?: 'http://localhost:8081';
if (($_SERVER['HTTP_ORIGIN'] ?? '') === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Vary: Origin');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$pdo = new PDO((string) getenv('DATABASE_DSN'), (string) getenv('DATABASE_USER'), (string) getenv('DATABASE_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$api = new EntityApi(new PdoEntityRepository($pdo));
$response = $api->handle(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
    file_get_contents('php://input') ?: '',
    $_GET,
);
http_response_code($response['status']);
echo json_encode($response['body'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
