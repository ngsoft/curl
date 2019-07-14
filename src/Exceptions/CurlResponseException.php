<?php

namespace NGSOFT\Curl\Exceptions;

use NGSOFT\Curl\CurlInfos,
    Throwable;

class CurlResponseException extends CurlException {

    const CODE_METADATA = 0;
    const CODE_CURL_ERROR = 1;

    /** @var CURLInfos */
    private $response;

    public function __construct(
            CurlInfos $response,
            string $message = "",
            int $code = 0,
            Throwable $previous = NULL
    ) {
        $this->response = $response;
        parent::__construct($message, $code, $previous);
    }

    public function getCurlResponse(): CurlInfos {
        return $this->response;
    }

    /**
     * Logs the error to the logger
     * @param LoggerInterface|null $logger
     */
    public function logMessage(LoggerInterface $logger = null, string $message) {
        if ($logger !== null) $logger->error($message);
    }

}
