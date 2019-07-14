<?php

namespace NGSOFT\Curl\Exceptions;

use RuntimeException;

class CurlException extends RuntimeException {

    /**
     * Codes
     */
    const CODE_PARSINGREQUEST = 0;
    const CODE_INVALIDOPT = 8;
    const CODE_METADATA = 16;

}
