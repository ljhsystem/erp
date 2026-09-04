<?php

declare(strict_types=1);

namespace Core\Security;

final class SecretResolver
{
    private string $secureConfigRoot;

    public function __construct(?string $secureConfigRoot = null)
    {
        $this->secureConfigRoot = $secureConfigRoot
            ?? dirname(PROJECT_ROOT) . DIRECTORY_SEPARATOR . 'secure-config';
    }

    public function resolve(string $credentialCode, string $field): string
    {
        $credentialCode = trim($credentialCode);
        $field = trim($field);
        if ($credentialCode === '' || $field === '') {
            throw new SecretResolutionException('UNKNOWN_CREDENTIAL');
        }

        if ($credentialCode === 'ERP_APP_MAIN') {
            if ($field !== 'secret') {
                throw new SecretResolutionException('UNKNOWN_CREDENTIAL');
            }

            $config = $this->load('app_secret.php');
            return $this->requiredString($config['APP_SECRET'] ?? null);
        }

        $supported = [
            'SECURITY_RRN_KEY' => 'secret',
            'DAUM_SMTP_MAIN' => 'password',
            'GOOGLE_SMTP_MAIN' => 'password',
            'BUSINESS_STATUS_API' => 'service_key',
            'INTERNAL_API_MAIN' => 'secret',
        ];
        if (!isset($supported[$credentialCode]) || $supported[$credentialCode] !== $field) {
            throw new SecretResolutionException('UNKNOWN_CREDENTIAL');
        }

        $config = $this->load('appsetting_secrets.php');
        $credential = $config[$credentialCode] ?? null;
        if (!is_array($credential) || !array_key_exists($field, $credential)) {
            throw new SecretResolutionException('UNKNOWN_CREDENTIAL');
        }

        return $this->requiredString($credential[$field]);
    }

    private function load(string $fileName): array
    {
        if (!is_dir($this->secureConfigRoot)) {
            throw new SecretResolutionException('SECURE_CONFIG_NOT_FOUND');
        }

        $path = $this->secureConfigRoot . DIRECTORY_SEPARATOR . $fileName;
        if (!is_file($path)) {
            throw new SecretResolutionException('SECURE_CONFIG_NOT_FOUND');
        }
        if (!is_readable($path)) {
            throw new SecretResolutionException('SECURE_CONFIG_NOT_READABLE');
        }

        $config = require $path;
        if (!is_array($config)) {
            throw new SecretResolutionException('SECURE_CONFIG_INVALID');
        }

        return $config;
    }

    private function requiredString(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            throw new SecretResolutionException('SECRET_NOT_CONFIGURED');
        }

        return $value;
    }
}
