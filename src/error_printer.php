<?php

use AmoCRM\Exceptions\AmoCRMApiErrorResponseException;
use AmoCRM\Exceptions\AmoCRMApiException;

function printError(AmoCRMApiException $e): void
{
    $errorTitle = $e->getTitle();
    $code = $e->getCode();
    $debugInfo = var_export($e->getLastRequestInfo(), true);

    $validationErrors = null;
    if ($e instanceof AmoCRMApiErrorResponseException) {
        $validationErrors = var_export($e->getValidationErrors(), true);
    }

    $error = <<<EOF
        Error: $errorTitle
        Code: $code
        Debug: $debugInfo
    EOF;

    if ($validationErrors !== null) {
        $error .= PHP_EOL . 'Validation-Errors: ' . $validationErrors . PHP_EOL;
    }

    file_put_contents(
        $_SERVER['DOCUMENT_ROOT'] . '/../logs/app.log',
        print_r('<pre>' . $error . '</pre>', true) . PHP_EOL, 
        FILE_APPEND
    );
}
