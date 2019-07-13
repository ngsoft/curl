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

    /**
     * Logs the error to the logger
     * @param LoggerInterface|null $logger
     */
    public function logMessage(LoggerInterface $logger = null, string $message) {
        if ($logger !== null) $logger->error($message);
    }

}
