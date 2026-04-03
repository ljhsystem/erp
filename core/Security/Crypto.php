<?php
// /core/Security/Crypto.php
//주민등록번호 암호화 복호화....
namespace Core\Security;

use Core\Helpers\ConfigHelper;

class Crypto
{
    private string $key;
    private string $iv;

    public function __construct()
    {
        $security = ConfigHelper::get('Security', []);
    
        $this->key = $security['RRNKey'] ?? '';
    
        if (!$this->key) {
            throw new \RuntimeException('RRN key not set in appsetting.json');
        }
    
        $this->iv = substr(hash('sha256', 'rrn-fixed-iv', true), 0, 16);
    }

    public function encryptResidentNumber(string $plain): string
    {
        $encrypted = openssl_encrypt(
            $plain,
            'AES-256-CBC',
            $this->key,
            OPENSSL_RAW_DATA,
            $this->iv
        );

        if ($encrypted === false) {
            throw new \RuntimeException('주민등록번호 암호화 실패');
        }

        return base64_encode($encrypted);
    }

    public function decryptResidentNumber(?string $encrypted): string
    {
        if (!$encrypted) {
            return '';
        }

        $decoded = base64_decode($encrypted, true);

        if ($decoded === false) {
            return '';
        }

        $decrypted = openssl_decrypt(
            $decoded,
            'AES-256-CBC',
            $this->key,
            OPENSSL_RAW_DATA,
            $this->iv
        );

        return $decrypted ?: '';
    }
}