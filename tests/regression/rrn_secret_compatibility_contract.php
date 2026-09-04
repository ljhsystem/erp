<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

use Core\Security\Crypto;
use Core\Security\SecretResolver;

function assertRrnContract(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$key = (new SecretResolver())->resolve('SECURITY_RRN_KEY', 'secret');
$plain = '9001011234567';
$iv = substr(hash('sha256', 'rrn-fixed-iv', true), 0, 16);
$legacyRaw = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
assertRrnContract(is_string($legacyRaw), '기존 RRN 알고리즘 Fixture 생성 실패');
$legacyCipher = base64_encode($legacyRaw);

$crypto = new Crypto();
$newCipher = $crypto->encryptResidentNumber($plain);
assertRrnContract(hash_equals($legacyCipher, $newCipher), '기존 RRN 암호문 호환 실패');
assertRrnContract($crypto->decryptResidentNumber($legacyCipher) === $plain, '기존 RRN 암호문 복호화 실패');
assertRrnContract($crypto->decryptResidentNumber($newCipher) === $plain, 'RRN Round-trip 실패');

unset($key, $plain, $iv, $legacyRaw, $legacyCipher, $newCipher, $crypto);
echo "rrn_secret_compatibility_contract: PASS\n";
