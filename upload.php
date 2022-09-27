<?php

require_once __DIR__ . '/vendor/autoload.php';


$username = $argv[1];
$password = $argv[2];
$version = $argv[3];

if (empty($username) || empty($password) || empty($version)) {
    echo "Usage: php upload.php <username> <password> <version>";
    exit(1);
}

$pluginId = '3255';

$apiUrl = 'https://api.shopware.com/';

$client = new \GuzzleHttp\Client([
    'base_uri' => $apiUrl,
    'headers' => [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ],
]);

try {
    echo 'Login... ';

    $response = $client->request('POST', 'accesstokens', [
        'json' => [
            'shopwareId' => $username,
            'password' => $password,
        ],
    ]);

    $tokens = json_decode($response->getBody()->getContents());

    $token = $tokens->token;

    $client = new \GuzzleHttp\Client([
        'base_uri' => $apiUrl,
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Shopware-Token' => $token,
        ],
    ]);

    echo "Success" . PHP_EOL;
    echo 'Get Versions... ';


    $response = $client->request('GET', sprintf('plugins/%d/binaries', $pluginId));


    $binaries = json_decode($response->getBody()->getContents());

    echo "Success" . PHP_EOL;

    $binaries = array_filter($binaries, function ($binary) use ($version) {
        return $binary->version === $version;
    });

    if (count($binaries) > 1) {
        throw new \Exception('More than 1 Version ' . $version . ' aborting');
    }
    if (count($binaries) === 1) {
        echo 'Version ' . $version . ' already exists. Updating... ';
        $binary = array_shift($binaries);
        $response = $client->request('POST', sprintf('plugins/%d/binaries/%d/file', $pluginId, $binary->id), [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => fopen(
                        __DIR__ . '/' . sprintf('jtl-connector-shopware5-%s.zip', $version),
                        'r'
                    ),
                ]
            ]
        ]);
    } else {
        echo 'Version ' . $version . ' does not exist. Creating... ';
        $response = $client->request('POST', sprintf('plugins/%d/binaries', $pluginId), [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => fopen(
                        __DIR__ . '/' . sprintf('jtl-connector-shopware5-%s.zip', $version),
                        'r'
                    ),
                ]
            ]
        ]);
    }

    $binaries = json_decode($response->getBody()->getContents());

    echo "Success" . PHP_EOL;

    $binary = array_shift($binaries);

    $binary->version = $version;
    foreach ($binary->changelogs as $key => $changelog) {
        if ($changelog->locale->name === 'en_EN') {
            $binary->changelogs[$key]->text = 'https://changelog.jtl-software.de/en/systems/connector/shopware5';
        } else {
            $binary->changelogs[$key]->text = 'https://changelog.jtl-software.de/systems/connector/shopware5';
        }
    }

    echo 'Update Plugin Info... ';

    $response = $client->request('GET', 'pluginstatics/softwareVersions');

    $softwareVersions = json_decode($response->getBody()->getContents());

    $softwareVersions = array_filter($softwareVersions, function ($softwareVersion) {
        return $softwareVersion->parent === 170 || $softwareVersion->parent === 129;
    });

    $binary->compatibleSoftwareVersions = $softwareVersions;

    $binaryId = $binary->id;

    $response = $client->request('PUT', sprintf('plugins/%d/binaries/%d', $pluginId, $binaryId), [
        'json' => $binary,
    ]);

    echo "Success" . PHP_EOL;
    echo 'Requesting Code Review' . PHP_EOL;

    $response = $client->request('POST', sprintf('plugins/%d/reviews', $pluginId));

    echo 'Done' . PHP_EOL;

    exit(0);
} catch (\GuzzleHttp\Exception\ClientException $e) {
    die('API Error ' . $e->getResponse()->getStatusCode() . ': ' . $e->getResponse()->getBody());
}






