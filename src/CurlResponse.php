<?php

declare(strict_types=1);

namespace NGSOFT\Curl;

use ArrayAccess,
    Countable,
    NGSOFT\Curl\Exceptions\CurlResponseException,
    RuntimeException,
    stdClass;

/**
 * @property-read string $url Last effective URL
 * @property-read int $status HTTP Status Code
 * @property-read string $statustext HTTP Status Text
 * @property-read string $version HTTP Protocol Version
 * @property-read string $content_type Content-Type
 * @property-read int $redirect_count Number of redirects
 * @property-read string $redirect_url Next url to redirect to
 * @property-read array<string,string[]> $headers Parsed Headers
 * @property-read int $header_size Header Size
 * @property-read array<string,string[]> $request_headers Parsed Request headers
 * @property-read bool $curl_exec curl_exec() return value
 * @property-read string $curl_error curl_error() return value
 * @property-read int $curl_errno curl_errno() return value
 * @property-read stdClass $curl_info curl_info() return value converted to object
 * @property-read resource $body File handle containing the contents
 * @property-read string $contents Getter to retrieve the contents
 * @property-read CurlRequest $request The Request
 */
final class CurlResponse implements ArrayAccess, Countable {

    /** @var array<string,mixed> */
    private $storage = [];

    public function __destruct() {
        if (
                isset($this->storage["curl_resource"])
                and gettype($this->storage["curl_resource"]) === "resource"
        ) curl_close($this->storage["curl_resource"]);
    }

    /**
     * Creates a new CurlResponse using the metadatas provided
     * @param array $metadatas
     * @return static
     */
    public static function from(array $metadatas): self {
        $response = new static();
        $response->storage = $metadatas;
        $response->assertValidMetadatas();
        if ($response->curl_errno > 0) {
            throw new CurlResponseException($response, $response->curl_error, CurlResponseException::CODE_CURLERROR);
        }
        return $response;
    }

    private function assertValidMetadatas() {

        foreach ([
    "url", "status", "statustext", "version", "content_type",
    "redirect_count", "redirect_url", "headers", "header_size", "request_headers",
    "curl_exec", "curl_error", "curl_errno", "curl_info", "curl_resource", "body", "request"
        ] as $key) {
            if (!array_key_exists($key, $this->storage)) {
                throw new \RuntimeException("Invalid Metadata Provided.");
            }
        }
    }

    ////////////////////////////   Getters   ////////////////////////////

    /**
     *  Last effective URL
     * @return string
     */
    public function getUrl(): string {
        return $this->storage["url"];
    }

    /**
     * HTTP Status Code
     * @return int
     */
    public function getStatus(): int {
        return $this->storage["status"];
    }

    /**
     * HTTP Status Text
     * @return string
     */
    public function getStatustext(): string {
        return $this->storage["statustext"];
    }

    /**
     * HTTP Protocol Version
     * @return string
     */
    public function getVersion(): string {
        return $this->storage["version"];
    }

    /**
     * HTTP Protocol Version
     * @return string
     */
    public function getContentType(): string {
        return $this->storage["content_type"];
    }

    /**
     * Number of redirects
     * @return int
     */
    public function getRedirectCount(): int {
        return $this->storage["redirect_count"];
    }

    /**
     *  Next url to redirect to
     * @return string
     */
    public function getRedirectUrl(): string {
        return $this->storage["redirect_url"];
    }

    /**
     * Parsed Headers
     * @return array
     */
    public function getHeaders(): array {
        return $this->storage["headers"];
    }

    /**
     * Header Size
     * @return int
     */
    public function getHeaderSize(): int {
        return $this->storage["header_size"];
    }

    /**
     * Parsed Request headers
     * @return array
     */
    public function getRequestHeaders(): array {
        return $this->storage["request_headers"];
    }

    /**
     * curl_exec() return value
     * @return bool
     */
    public function getCurlExec(): bool {
        return $this->storage["curl_exec"];
    }

    /**
     * curl_error() return value
     * @return string
     */
    public function getCurlError(): string {
        return $this->storage["curl_error"];
    }

    /**
     * curl_errno() return value
     * @return int
     */
    public function getCurlErrno(): int {
        return $this->storage["curl_errno"];
    }

    /**
     * curl_info() return value converted to object
     * @return stdClass
     */
    public function getCurlInfo(): \stdClass {
        $info = &$this->storage["curl_info"];
        if ($info === null) {
            $info = (object) curl_getinfo($this->storage["curl_resource"]);
            curl_close($this->storage["curl_resource"]);
        }
        return $info;
    }

    /**
     * File handle containing the contents
     * @return resource
     */
    public function getBody() {
        return $this->storage["body"];
    }

    /**
     * Get Contents
     * @return string
     * @throws CurlResponseException
     */
    public function getContents(): string {
        if (-1 === fseek($this->body, 0)) {
            throw new CurlResponseException($this, "Cannot seek stream.", CurlResponseException::CODE_INVALIDSTREAM);
        }
        return stream_get_contents($this->body);
    }

    /**
     * Get The Original Request
     * @return CurlRequest
     */
    public function getRequest(): CurlRequest {
        return $this->storage["request"];
    }

    ////////////////////////////   Utils   ////////////////////////////

    /**
     * Convert CamelCased to camel_cased
     * @param string $camelCased
     * @return string
     */
    private function toSnake(string $camelCased): string {
        return strtolower(preg_replace('/[A-Z]/', '_\\0', lcfirst($camelCased)));
    }

    /**
     * Convert snake_case to SnakeCase
     * @param string $snake_case
     * @return string
     */
    private function toCamelCase(string $snake_case): string {
        return preg_replace_callback('/(^|_|\.)+(.)/', function ($match) {
            return ('.' === $match[1] ? '_' : '') . strtoupper($match[2]);
        }, $snake_case);
    }

    ////////////////////////////   Magic Methods   ////////////////////////////

    /** {@inheritdoc} */
    public function __get($name) {
        $method = sprintf('get%s', $this->toCamelCase($name));
        if (!method_exists($this, $method)) throw new RuntimeException("Invalid property $name");
        return $this->{$method}();
    }

    /** {@inheritdoc} */
    public function __isset($name) {
        $method = sprintf('get%s', $this->toCamelCase($name));
        return method_exists($this, $method);
    }

    /**
     * {@inheritdoc}
     * @phan-suppress PhanParamTooMany
     */
    public function __set($name, $value) {
        $method = sprintf('set%s', $this->toCamelCase($name));
        if (method_exists($this, $method)) $this->{$method}($value);
    }

    /**
     * Returns contents from the stream
     * @return string
     */
    public function __toString() {
        try {
            return $this->getContents();
        } catch (CurlResponseException $exc) {
            $exc->getCode();
            return "";
        }
    }

    ////////////////////////////   ArrayAccess...   ////////////////////////////

    /** {@inheritdoc} */
    public function offsetExists($offset) {
        return $this->__isset($offset);
    }

    /** {@inheritdoc} */
    public function offsetGet($offset) {
        return $this->__get($offset);
    }

    /** {@inheritdoc} */
    public function offsetSet($offset, $value) {
        $this->__set($offset, $value);
    }

    /** {@inheritdoc} */
    public function offsetUnset($offset) {
        throw new RuntimeException("Cannot Unset " . __CLASS__ . "[" . $offset . "]");
    }

    /** {@inheritdoc} */
    public function count() {
        $count = count($this->storage);
        return $count ? $count + 1 : 0;
    }

}
