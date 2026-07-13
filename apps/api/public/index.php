<?php

declare(strict_types=1);
use PDO;
use WonderOS\Api\AuditApi;
use WonderOS\Api\AuditedEditorialLensStudioApi;
use WonderOS\Api\AuditedEntityMutationApi;
use WonderOS\Api\AuthApi;
use WonderOS\Api\ClaimApi;
use WonderOS\Api\ClaimCollaborationApi;
use WonderOS\Api\ClaimReviewApi;
use WonderOS\Api\ClaimReviewQueueApi;
use WonderOS\Api\EditorialLensStudioApi;
use WonderOS\Api\EntityApi;
use WonderOS\Api\GraphApi;
use WonderOS\Api\NotificationApi;
use WonderOS\Api\NotificationPreferencesApi;
use WonderOS\Core\Audit\PdoAuditRepository;
use WonderOS\Core\Auth\AuthService;
use WonderOS\Core\Auth\PdoAuthRepository;
use WonderOS\Core\Notification\PdoNotificationRepository;
use WonderOS\Knowledge\Graph\GraphTraversal;
use WonderOS\Knowledge\Infrastructure\Persistence\PdoClaimCollaborationRepository;
use WonderOS\Knowledge\Infrastructure\Persistence\PdoClaimRepository;
use WonderOS\Knowledge\Infrastructure\Persistence\PdoEditorialLensRepository;
use WonderOS\Knowledge\Infrastructure\Persistence\PdoEntityRepository;
use WonderOS\Knowledge\Infrastructure\Persistence\PdoRelationshipRepository;
use WonderOS\Knowledge\Infrastructure\Persistence\PdoRelationshipTypeRepository;

require dirname(__DIR__, 3) . '/vendor/autoload.php';
header('Content-Type: application/json; charset=utf-8');

$allowedOrigin = getenv('CONSOLE_ORIGIN') ?: 'http://localhost:8081';
if (($_SERVER['HTTP_ORIGIN'] ?? '') === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin); header('Vary: Origin');
    header('Access-Control-Allow-Headers: Content-Type, Authorization'); header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

$pdo=new PDO((string)getenv('DATABASE_DSN'),(string)getenv('DATABASE_USER'),(string)getenv('DATABASE_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$entities=new PdoEntityRepository($pdo); $relationships=new PdoRelationshipRepository($pdo); $relationshipTypes=new PdoRelationshipTypeRepository($pdo);
$claims=new PdoClaimRepository($pdo); $collaboration=new PdoClaimCollaborationRepository($pdo); $notifications=new PdoNotificationRepository($pdo); $lenses=new PdoEditorialLensRepository($pdo); $graph=new GraphTraversal($entities,$relationships,$relationshipTypes);
$authRepository=new PdoAuthRepository($pdo); $auth=new AuthService($authRepository); $audit=new PdoAuditRepository($pdo);
$method=$_SERVER['REQUEST_METHOD']??'GET'; $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/'; $rawBody=file_get_contents('php://input')?:'';
$headers=[]; foreach(function_exists('getallheaders')?getallheaders():[] as $name=>$value){$headers[strtolower((string)$name)]=(string)$value;}

$entityApi = new EntityApi($entities,$relationships,$relationshipTypes);
$studioApi = new EditorialLensStudioApi($lenses,$graph,$auth);

$response=(new AuditApi($auth,$audit))->handle($method,$path,$headers,$_GET);
if($response===null){$response=(new AuthApi($auth,$authRepository,$audit))->handle($method,$path,$rawBody,$headers);}
if($response===null){$response=(new NotificationPreferencesApi($pdo,$auth))->handle($method,$path,$rawBody,$headers);}
if($response===null){$response=(new NotificationApi($notifications,$auth))->handle($method,$path,$headers,$_GET);}
if($response===null){$response=(new ClaimCollaborationApi($collaboration,$claims,$auth,$audit,$notifications))->handle($method,$path,$rawBody,$headers);}
if($response===null){$response=(new ClaimReviewQueueApi($claims,$auth))->handle($method,$path,$headers,$_GET);}
if($response===null){$response=(new ClaimReviewApi($claims,$auth,$audit))->handle($method,$path,$rawBody,$headers);}
if($response===null){$response=(new ClaimApi($claims,$entities,$auth,$audit))->handle($method,$path,$rawBody,$headers);}
if($response===null){$response=(new AuditedEntityMutationApi($entityApi,$auth,$audit))->handle($method,$path,$rawBody,$headers,$_GET);}
if($response===null && !($method==='GET' && $path==='/v1/editorial-lenses')){$response=(new AuditedEditorialLensStudioApi($studioApi,$auth,$audit))->handle($method,$path,$rawBody,$headers);}
if($response===null){$response=(new GraphApi($graph,$lenses))->handle($method,$path,$_GET);}
if($response===null){$response=$entityApi->handle($method,$path,$rawBody,$_GET);}
http_response_code($response['status']);
echo json_encode($response['body'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);