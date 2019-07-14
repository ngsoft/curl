<?php

namespace NGSOFT\Curl;

use ArrayAccess,
    Countable,
    IteratorAggregate,
    NGSOFT\Curl\Exceptions\CurlException,
    RuntimeException;

/**
 * @phan-file-suppress PhanUnusedPublicMethodParameter
 * @property string $url
 * @property string $content_type
 * @property int $http_code
 * @property int $header_size
 * @property int $request_size
 * @property int $redirect_count
 * @property double $total_time
 * @property double $namelookup_time
 * @property double $connect_time
 * @property double $pretransfer_time
 * @property int $size_upload
 * @property int $size_download
 * @property int $speed_download
 * @property double $starttransfer_time
 * @property double $redirect_time
 * @property string $redirect_url
 * @property string $primary_ip
 * @property int $primary_port
 * @property string $local_ip
 * @property int $local_port
 * @property int $http_version
 * @property string $scheme
 * @property int $appconnect_time_us
 * @property int $connect_time_us
 * @property int $namelookup_time_us
 * @property int $pretransfer_time_us
 * @property int $redirect_time_us
 * @property int $starttransfer_time_us
 * @property int $total_time_us
 *
 * @property resource $body
 * @property array $headers
 * @property string $curl_error
 * @property int $curl_errno
 * @property bool $curl_exec
 * @property string $contents
 */
class CurlResponse implements ArrayAccess, Countable {

    /** @var array<string,mixed> */
    private $storage = [];

    /**
     * Creates a new CurlResponse using the metadatas provided
     * @param array $metadatas
     * @return static
     */
    public static function create(array $metadatas): self {
        self::assertValidMetadatas($metadatas);
        $response = new static();
        $response->storage = $metadatas;
        return $response;
    }

    private static function assertValidMetadatas(array $metadatas) {
        foreach ([
    "curl_exec",
    "curl_error",
    "curl_errno",
    "body",
    "headers",
    "http_code",
    "url"
        ] as $key) {
            if (!array_key_exists($key, $metadatas)) {
                throw new RuntimeException("Invalid Metadata Provided.");
            }
        }
    }

    /**
     * Get Contents
     * @return string
     * @throws CurlException
     */
    private function getContents(): string {
        if (-1 === fseek($this->body, 0)) {
            throw new CurlException("Cannot seek stream.", CurlException::CODE_INVALIDSTREAM);
        }
        return stream_get_contents($this->body);
    }

    /**
     * Does nothing
     */
    private function noop() {

    }

    ////////////////////////////   Magic Methods   ////////////////////////////

    /** {@inheritdoc} */
    public function __get($name) {
        if (isset($this->{$name})) return $this->storage[$name];
        elseif ($name === "contents") return $this->getContents();
        throw new RuntimeException("Invalid property $name");
    }

    /** {@inheritdoc} */
    public function __isset($name) {
        return array_key_exists($name, $this->storage);
    }

    /** {@inheritdoc} */
    public function __set($name, $value) {
        $this->noop($name, $value);
    }

    /** {@inheritdoc} */
    public function __unset($name) {
        $this->noop($name);
    }

    /**
     * Returns contents from the stream
     * @return string
     */
    public function __toString() {
        try {
            return $this->getContents();
        } catch (CurlException $exc) {
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
        $this->noop($offset, $value);
    }

    /** {@inheritdoc} */
    public function offsetUnset($offset) {
        $this->noop($offset);
    }

    /** {@inheritdoc} */
    public function count() {
        $count = count($this->storage);
        return $count ? $count + 1 : 0;
    }

}
