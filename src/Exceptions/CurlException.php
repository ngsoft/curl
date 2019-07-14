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
    const CODE_INVALIDSTREAM = 3;

}
