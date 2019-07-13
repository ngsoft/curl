<?php

use NGSOFT\Curl\Exceptions\ClientException,
    Psr\Http\Client\RequestExceptionInterface;

namespace NGSOFT\Curl\Exceptions;

class RequestException extends ClientException implements RequestExceptionInterface {

}
