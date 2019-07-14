<?php

declare(strict_types=1);

namespace NGSOFT\Curl\Exceptions;

use Psr\{
    Http\Message\RequestInterface, Log\LoggerInterface
};
use RuntimeException,
    Throwable;

abstract class ClientException extends RuntimeException {

    private $request;

    /**
     * @param RequestInterface $request
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(
            RequestInterface $request,
            string $message = "",
            int $code = 0,
            Throwable $previous = NULL
    ) {
        $this->request = $request;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns the request.
     *
     * The request object MAY be a different object from the one passed to ClientInterface::sendRequest()
     *
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface {
        return $this->request;
    }

}
