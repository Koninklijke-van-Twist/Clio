<?php

declare(strict_types=1);

$auth_list = [
    'test_aad' => ['mode' => 'basic', 'user' => 'test-user', 'pass' => 'test-pass'],
];

$environment = 'test_aad';
$auth = $auth_list[$environment];
$baseUrl = 'https://example.test/';

$ictUsers = [
    'tfalken@kvt.nl',
    'milanscheenloop@kvt.nl',
    'cvrij@kvt.nl',
];

function getIctUsers(): array
{
    global $ictUsers;

    if (!is_array($ictUsers)) {
        return [];
    }

    $normalized = [];
    foreach ($ictUsers as $email) {
        $email = strtolower(trim((string) $email));
        if ($email !== '') {
            $normalized[] = $email;
        }
    }

    return $normalized;
}

$mailSettings = [
    'from_email' => 'clio@example.test',
    'from_name' => 'Clio Test',
    'transport' => 'mail',
    'subject_prefix' => 'Clio',
    'smtp' => [
        'host' => 'smtp.example.test',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'test@example.test',
        'password' => 'test-password',
        'timeout' => 20,
    ],
];

$apiKeys = [
    'test-service' => '0000-0000-0000-0000',
];

$sharepointSettings = [
    'site_id' => 'example.test,site-id',
    'drive_id' => 'drive-id',
    'list_id' => 'list-id',
    'upload_folder' => '',
    'status_field' => 'Transcript',
    'access_token' => '',
    'tenant_id' => 'tenant-id',
    'client_id' => 'client-id',
    'client_secret' => 'client-secret',
    'token_scope' => 'https://graph.microsoft.com/.default',
    'token_url' => '',
    'verify_ssl' => true,
    'ca_bundle' => '',
];
