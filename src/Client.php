<?php

namespace NGSOFT\Curl;

use InvalidArgumentException;
use Psr\{
    Container\ContainerInterface, Http\Client\ClientInterface, Http\Message\MessageInterface, Http\Message\RequestFactoryInterface,
    Http\Message\RequestInterface, Http\Message\ResponseFactoryInterface, Http\Message\ResponseInterface,
    Http\Message\StreamFactoryInterface, Http\Message\StreamInterface, Http\Message\UriFactoryInterface, Http\Message\UriInterface
};
use RuntimeException;

if (!class_exists(MessageInterface)) {
    throw new RuntimeException("Cannot use the HTTP Client, you do not use a PSR-7 implementation. see https://packagist.org/providers/psr/http-message-implementation");
}

if (!class_exists(RequestFactoryInterface)) {
    throw new RuntimeException("Cannot use the HTTP Client, you do not use a PSR-17 implementation. see https://packagist.org/providers/psr/http-factory-implementation");
}

/**
 * This is a PSR 17 Proxy for PSR 17 implementations
 * And also a PSR 18 Implementation
 */
final class Client implements RequestFactoryInterface, ResponseFactoryInterface, StreamFactoryInterface, UriFactoryInterface, ClientInterface {
    ////////////////////////////   Instanciation and Configuration   ////////////////////////////

    /** @var array<string,string> */
    private static $options = [
        // That class will proxy out those factories
        "streamfactory" => StreamFactoryInterface::class,
        "requestfactory" => RequestFactoryInterface::class,
        "responsefactory" => ResponseFactoryInterface::class,
        "urifactory" => UriFactoryInterface::class,
    ];

    /**
     * @var StreamFactoryInterface
     */
    private $streamfactory;

    /**
     * @var RequestFactoryInterface
     */
    private $requestfactory;

    /**
     * @var ResponseFactoryInterface
     */
    private $responsefactory;

    /**
     *  @var UriFactoryInterface
     */
    private $urifactory;

    /**
     * Get a clone from the current instance
     * @return static
     */
    private function getClone() {
        return clone $this;
    }

    /**
     * Assert if given param is not an instance of factory
     * @param object $instance
     * @return static
     * @throws InvalidArgumentException
     */
    private function assertNotSelf(object $instance) {
        if ($instance instanceof self) {
            throw new InvalidArgumentException(
                    "You cannot register " . __CLASS__ . " within itself."
            );
        }
        return $this;
    }

    /**
     * Check if class configured correctly
     * @return static
     * @throws RuntimeException
     */
    private function assertConfigured(): self {
        foreach (self::$options as $prop => $interface) {
            if (!isset($this->{$prop})) {
                throw new RuntimeException(
                        __CLASS__ . " is not configured correctly :"
                        . $interface . " implementation is not set"
                );
            }
        }
        return $this;
    }

    /**
     * Add The PSR{7/11} Implementations instances
     * @param RequestFactoryInterface|ResponseFactoryInterface|StreamFactoryInterface|UriFactoryInterface|ContainerInterface ...$options
     * @return static
     */
    public function configure(...$options) {

        foreach ($options as $option) {
            $this->assertNotSelf($option);

            // Special Case : Use the PSR11 container to set the required implementations
            if ($option instanceof ContainerInterface) {
                foreach (self::$options as $prop => $interface) {
                    if ($option->has($interface)) {
                        $instance = $option->get($interface);
                        if (
                                ($instance instanceof $interface)
                                and ! ($instance instanceof self)
                        ) $this->configure($instance);
                    }
                }
                continue;
            }

            // Auto detects the PSR7 Implementation classes from the arguments
            $c = 0;
            foreach (self::$options as $prop => $interface) {
                // can be used to set mixins (like the current class) too
                if ($option instanceof $interface) {
                    $this->{$prop} = $option;
                    ++$c;
                }
            }

            if ($c === 0) {
                throw new InvalidArgumentException(
                        "Argument not an instance of "
                        . implode(", ", array_values(self::$options))
                );
            }
        }
        return $this;
    }

    /**
     * @param RequestFactoryInterface|ResponseFactoryInterface|StreamFactoryInterface|UriFactoryInterface|ContainerInterface ...$options
     * @throws InvalidArgumentException
     */
    public function __construct(...$options) {
        $this->configure(...$options);
    }

    /**
     * @param RequestFactoryInterface|ResponseFactoryInterface|StreamFactoryInterface|UriFactoryInterface|ContainerInterface ...$options
     * @throws InvalidArgumentException
     */
    public static function create(...$options): self {

        return new static(...$options);
    }

    ////////////////////////////   Factory Builder   ////////////////////////////
    // With that you can Mix PSR7 Implementations on the fly

    /**
     * Set the StreamFactoryInterface Implementation
     * @param StreamFactoryInterface $streamfactory
     * @return static A new instance with the implementation
     */
    public function withStreamFactory(StreamFactoryInterface $streamfactory) {
        $this->assertNotSelf($streamfactory);
        $clone = $this->getClone();
        $clone->streamfactory = $streamfactory;
        return $clone;
    }

    /**
     * Set the RequestFactoryInterface Implementation
     * @param RequestFactoryInterface $requestfactory
     * @return static A new instance with the implementation
     */
    public function setRequestFactory(RequestFactoryInterface $requestfactory) {
        $this->assertNotSelf($requestfactory);
        $clone = $this->getClone();
        $clone->requestfactory = $requestfactory;
        return $clone;
    }

    /**
     * set the ResponseFactoryInterface Implementation
     * @param ResponseFactoryInterface $responsefactory
     * @return static A new instance with the implementation
     */
    public function setResponseFactory(ResponseFactoryInterface $responsefactory) {
        $this->assertNotSelf($responsefactory);
        $clone = $this->getClone();
        $clone->responsefactory = $responsefactory;
        return $clone;
    }

    /**
     * Set the UriFactoryInterface Implementation
     * @param UriFactoryInterface $urifactory
     * @return static A new instance with the implementation
     */
    public function withUriFactory(UriFactoryInterface $urifactory) {
        $this->assertNotSelf($urifactory);
        $clone = $this->getClone();
        $clone->urifactory = $urifactory;
        return $clone;
    }

    /**
     * Set the Container Implementation
     * @param ContainerInterface $container
     * @return static A new instance with the implementation
     */
    public function withContainer(ContainerInterface $container) {
        return $this->getClone()->configure($container);
    }

    ////////////////////////////   PSR17   ////////////////////////////

    /** {@inheritdoc} */
    public function createRequest(string $method, $uri): RequestInterface {
        return $this->assertConfigured()->requestfactory->createRequest($method, $uri);
    }

    /** {@inheritdoc} */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface {
        return $this->assertConfigured()->responsefactory->createResponse($code, $reasonPhrase);
    }

    /** {@inheritdoc} */
    public function createStream(string $content = ''): StreamInterface {
        return $this->assertConfigured()->streamfactory->createStream($content);
    }

    /** {@inheritdoc} */
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface {
        return $this->assertConfigured()->streamfactory->createStreamFromFile($filename, $mode);
    }

    /** {@inheritdoc} */
    public function createStreamFromResource($resource): StreamInterface {
        return $this->assertConfigured()->streamfactory->createStreamFromResource($resource);
    }

    /** {@inheritdoc} */
    public function createUri(string $uri = ''): UriInterface {
        return $this->assertConfigured()->urifactory->createUri($uri);
    }

    ////////////////////////////   PSR18   ////////////////////////////

    /** {@inheritdoc} */
    public function sendRequest(RequestInterface $request): ResponseInterface {

    }

}
