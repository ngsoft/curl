<?php

namespace NGSOFT\Curl\Exceptions;

use Psr\Log\LoggerInterface,
    RuntimeException;

class CurlException extends RuntimeException {

    /**
     * Logs the error to the logger
     * @param LoggerInterface|null $logger
     */
    public function logMessage(LoggerInterface $logger = null, string $message) {
        if ($logger !== null) $logger->error($message);
    }

}
