<?php

include_once __DIR__ . '/../src/bootstrap.php';

use \src\LeadHook;
use \League\OAuth2\Client\Token\AccessTokenInterface;

session_start();

function myLog($content): void
{
    file_put_contents(
        $_SERVER['DOCUMENT_ROOT'] . '/../logs/app.log',
        date('Y-m-d H:i:s') . ' | ' . print_r($content, true) . PHP_EOL,
        FILE_APPEND
    );
}

myLog(['request' => $_REQUEST]);
try {
    $accessToken = getToken();

    $apiClient->setAccessToken($accessToken)
        ->setAccountBaseDomain($accessToken->getValues()['baseDomain'])
        ->onAccessTokenRefresh(
            function (AccessTokenInterface $accessToken, string $baseDomain) {
                saveToken(
                    [
                        'accessToken' => $accessToken->getToken(),
                        'refreshToken' => $accessToken->getRefreshToken(),
                        'expires' => $accessToken->getExpires(),
                        'baseDomain' => $baseDomain,
                    ]
                );
            }
        );


    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        myLog('in post');
        if (isset($_REQUEST['leads']['update'])) {

            myLog('in update');
            // $leadHook = new LeadHook($apiClient);
            // myLog($leadHook);
            // $leadHook->addNoteOnUpdate($_REQUEST);
            include_once __DIR__ . '/../src/leads.php';
        }
        if (isset($_REQUEST['leads']['create'])) {
        }
        if (isset($_REQUEST['contacts']['update'])) {
        }
        if (isset($_REQUEST['contacts']['update'])) {
        }
    }
} catch (Exception $e) {
    myLog($e);
    print_r($e);
}
