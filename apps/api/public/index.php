<?php

declare(strict_types=1);

use PDO;
use WonderOS\Api\EntityApi;
use WonderOS\Api\GraphApi;
use WonderOS\Knowledge\Graph\GraphTraversal;
use WonderOS\Knowledge\Infrastructure\Persistence\PdoEditorialLensRepository;
use WonderOS\Knowledge\Infrastructure\Persistence\PdoEntityRepository;
use WonderOS\Knowledge\Infrastructure\Persistence\PdoRelationshipRepository;
use WonderOS\Knowledge\Infrastructure\Persistence\PdoRelationshipTypeRepository;

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

$entities = new PdoEntityRepository($pdo);
$relationships = new PdoRelationshipRepository($pdo);
$relationshipTypes = new PdoRelationshipTypeRepository($pdo);
$lenses = new PdoEditorialLensRepository($pdo);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$graphApi = new GraphApi(new GraphTraversal($entities, $relationships, $relationshipTypes), $lenses);
$response = $graphApi->handle($method, $path, $_GET);

if ($response === null) {
    $entityApi = new EntityApi($entities, $relationships, $relationshipTypes);
    $response = $entityApi->handle($method, $path, file_get_contents('php://input') ?: '', $_GET);
}

http_response_code($response['status']);
echo json_encode($response['body'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
