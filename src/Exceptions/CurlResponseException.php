<?php

namespace NGSOFT\Curl\Exceptions;

use NGSOFT\Curl\CurlResponse,
    Throwable;

class CurlResponseException extends CurlException {

    const CODE_CURLERROR = 32;
    const CODE_INVALIDSTREAM = 64;

    /** @var CURLInfos */
    private $response;

    public function __construct(
            CurlResponse $response,
            string $message = "",
            int $code = 0,
            Throwable $previous = NULL
    ) {
        $this->response = $response;
        parent::__construct($message, $code, $previous);
    }

    public function getCurlResponse(): CurlResponse {
        return $this->response;
    }

    /**
     * Logs the error to the logger
     * @param LoggerInterface|null $logger
     */
    public function logMessage(LoggerInterface $logger = null) {
        if ($logger !== null) $logger->error($this->getMessage());
    }

}
