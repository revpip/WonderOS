<?php

declare(strict_types=1);

use PDO;
use WonderOS\Core\Auth\PdoAuthRepository;
use WonderOS\Core\Auth\Role;
use WonderOS\Core\Auth\User;

require dirname(__DIR__) . '/vendor/autoload.php';

$email = $argv[1] ?? '';
$name = $argv[2] ?? '';
$password = $argv[3] ?? '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || trim($name) === '' || strlen($password) < 12) {
    fwrite(STDERR, "Usage: php tools/create-admin.php admin@example.com \"Admin Name\" \"a-password-of-12+-characters\"\n");
    exit(1);
}

$pdo = new PDO((string)getenv('DATABASE_DSN'), (string)getenv('DATABASE_USER'), (string)getenv('DATABASE_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$repository = new PdoAuthRepository($pdo);
$data = random_bytes(16); $data[6]=chr((ord($data[6])&0x0f)|0x40); $data[8]=chr((ord($data[8])&0x3f)|0x80);
$uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data),4));
$repository->createUser(new User($uuid,strtolower($email),trim($name),Role::Administrator),password_hash($password,PASSWORD_DEFAULT));
fwrite(STDOUT, "WonderOS administrator created for {$email}.\n");
