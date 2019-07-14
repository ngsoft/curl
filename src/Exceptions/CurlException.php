<?php

namespace NGSOFT\Curl\Exceptions;

use Psr\Log\LoggerInterface,
    RuntimeException;

class CurlException extends RuntimeException {

    /**
     * Codes
     */
    const CODE_PARSINGREQUEST = 0;
    const CODE_INVALIDOPT = 1;
    const CODE_CURLERROR = 2;

    /**
     * Logs the error to the logger
     * @param LoggerInterface|null $logger
     */
    public function logMessage(LoggerInterface $logger = null, string $message) {
        if ($logger !== null) $logger->error($message);
    }

}
