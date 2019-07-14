<?php

declare(strict_types=1);

namespace NGSOFT\Curl;

use NGSOFT\Curl\Exceptions\{
    CurlResponseException, NetworkException, RequestException
};
use Psr\Http\{
    Client\ClientInterface, Message\RequestInterface, Message\ResponseFactoryInterface, Message\ResponseInterface,
    Message\StreamFactoryInterface
};
use RuntimeException;

if (!interface_exists(ResponseFactoryInterface::class)) {
    throw new RuntimeException("Cannot use the HTTP Client, you do not provide a PSR-17 implementation. see https://packagist.org/providers/psr/http-factory-implementation");
}

/**
 * PSR 18 Curl Client
 */
final class Client implements ClientInterface {

    const VERSION = CurlRequest::VERSION;

    /** @var ResponseFactoryInterface */
    private $responsefactory;

    /** @var array<int,mixed> */
    private $curlopts = [];

    /**
     *
     * @param ResponseFactoryInterface $responsefactory
     * @param array $curlopts
     */
    public function __construct(ResponseFactoryInterface $responsefactory, array $curlopts = []) {
        $this->responsefactory = $responsefactory;
        $this->curlopts = $curlopts;
    }

    /** {@inheritdoc} */
    public function sendRequest(RequestInterface $request): ResponseInterface {
        $method = $request->getMethod() ?: null;
        $url = (string) $request->getUri();
        $data = (string) $request->getBody() ?: null;
        $headers = $request->getHeaders();
        $curl = (new CurlRequest())->withHeaders($headers);
        try {
            $response = $this->sendRequestWithCurlRequest($curl, $url, $method, $data);
        } catch (CurlResponseException $e) {
            if ($e->getCode() === CurlResponseException::CODE_CURLERROR) {
                $cr = $e->getCurlResponse();
                $errno = $cr->curl_errno;
                switch ($errno) {
                    case CURLE_COULDNT_RESOLVE_PROXY:
                    case CURLE_COULDNT_RESOLVE_HOST:
                    case CURLE_COULDNT_CONNECT:
                    case CURLE_OPERATION_TIMEOUTED:
                    case CURLE_SSL_CONNECT_ERROR:
                        throw new NetworkException($request, $cr->curl_error);
                    default:
                        throw new RequestException($request, $cr->curl_error);
                }
            } else throw $e;
        }

        return $response;
    }

    /**
     * Use CurlRequest to build the request to obtain a PSR7 Response
     * @param CurlRequest $request
     * @param string $url
     * @param string|null $method GET|HEAD|POST|PUT|DELETE|CONNECT|OPTIONS|TRACE|PATCH
     * @param null|string|array<string,mixed> $data
     * @return ResponseInterface
     */
    public function sendRequestWithCurlRequest(CurlRequest $request, string $url, string $method = null, $data = null): ResponseInterface {
        $opts = $this->curlopts;

        $cr = $request->withOpts($opts)->fetch($url, $method, $data);

        $response = $this->responsefactory->createResponse($cr->status, $cr->statustext);
        foreach ($cr->headers as $name => $values) {
            $response = $response->withAddedHeader($name, $values);
        }
        if ($this->responsefactory instanceof StreamFactoryInterface) {
            $response = $response->withBody($this->responsefactory->createStreamFromResource($cr->body));
        } else $response->getBody()->write($cr->contents);

        return $response->withProtocolVersion($cr->version);
    }

}
