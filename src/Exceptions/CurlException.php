<?php

namespace NGSOFT\Curl\Exceptions;

use Psr\Log\LoggerInterface,
    RuntimeException;

class CurlException extends RuntimeException {

    /**
     * Codes
     */
    const CODE_PARSINGREQUEST = 0;
    const CODE_INVALIDOPT = 8;
    const CODE_CURLERROR = 16;
    const CODE_INVALIDSTREAM = 32;

}
