<?php

namespace NGSOFT\Curl\Exceptions;

use NGSOFT\Curl\Exceptions\ClientException,
    Psr\Http\Client\RequestExceptionInterface;

class RequestException extends ClientException implements RequestExceptionInterface {

}
