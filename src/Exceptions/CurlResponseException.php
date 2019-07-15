<?php

declare(strict_types=1);

namespace NGSOFT\Curl\Exceptions;

use NGSOFT\Curl\CurlResponse,
    Throwable;

class CurlResponseException extends CurlException {

    const CODE_PARSINGREQUEST = 0;
    const CODE_INVALIDOPT = 8;
    const CODE_METADATA = 16;
    const CODE_CURLERROR = 32;
    const CODE_INVALIDSTREAM = 64;

    /** @var CurlResponse */
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

}
