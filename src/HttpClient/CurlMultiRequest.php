<?php

namespace HttpClient;

use Lockable;


class CurlMultiRequest implements Lockable, \IteratorAggregate, \Countable
{

    /** @var \CurlMultiHandle|resource */
    protected $handle;
    protected $closed = true;
    protected $ready = false;
    protected $locked = false;
    /** @var array<string,CurlRequest> */
    protected $curlHandles = [];
    /** @var ?array<string,CurlResponse> */
    protected $results = null;
    /** @var ?array<string,CurlRequest> */
    protected $resultRequests = null;

    /**
     * @return static
     */
    public function execute()
    {
        if ($this->isLocked() || !$this->ready) {
            throw new \RuntimeException('CurlMultiRequest is locked or requests are not ready yet.');
        }

        $this->ready = false;
        $this->lock();


        $this->results = $this->resultRequests = [];
        $results = [];
        $handles = [];
        $mh = $this->getHandle();

        $n = 0;

        foreach ($this->curlHandles as $curlHandle) {
            if ($curlHandle->isReady()) {
                $n++;
                @curl_multi_add_handle(
                    $mh,
                    $handles[$curlHandle->getUid()] = $curlHandle->getHandle()
                );
            }
        }

        if (!$n) {
            throw new \RuntimeException('no requests are ready.');
        }

        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);


        while ($active && $mrc == CURLM_OK) {
            curl_multi_select($mh);
            usleep(90);
            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);

            while ($info = curl_multi_info_read($mh)) {

                $ch = $info["handle"];
                curl_multi_remove_handle($mh, $ch);
                $uid = array_search($ch, $handles, true);
                $req = $this->curlHandles[$uid];

                $result = $req->getResult();
                // redirection
                if ($req->isReady()) {
                    $result = $req->execute();
                }
                $results[$uid] = $result;
            }
        }

        $this->unlock();
        // sort results by request order
        foreach (array_keys($this->curlHandles) as $uid) {
            if (isset($results[$uid])) {
                $this->results[$uid] = $results[$uid];
                $this->resultRequests[$uid] = $this->curlHandles[$uid];
                $this->remove($uid);
            }
        }


        return $this->closeHandle();

    }

    /**
     * @return CurlResponse[]
     */
    public function getResults()
    {

        if (empty($this->results)) {
            return [];
        }


        return $this->results;
    }


    /**
     * @param CurlRequest|string $request
     * @return static
     */
    public function remove($request)
    {

        if (!$this->isLocked()) {
            if ($request instanceof CurlRequest) {
                $request = $request->getUid();
            }

            if (is_string($request)) {
                unset($this->curlHandles[$request]);
            }

            $this->ready = array_any($this->curlHandles, function ($request) {
                return $request->isReady();
            });
        }


        return $this;
    }


    public function add(CurlRequest $curlRequest)
    {
        if (!$this->isLocked()) {
            $this->curlHandles[$curlRequest->getUid()] = $curlRequest;
            if ($curlRequest->isReady()) {
                $this->ready = true;
            }
        }

        return $this;
    }

    /**
     * @param CurlRequest[] $requests
     * @return $this
     */
    public function addMany(array $requests)
    {
        foreach ($requests as $request) {
            if (!($request instanceof CurlRequest)) {
                throw new \InvalidArgumentException('$requests must be of type CurlRequest[]');
            }
            $this->add($request);

        }
        return $this;
    }

    /**
     * @return bool
     */
    public function isReady()
    {
        return $this->ready;
    }


    public function __destruct()
    {
        if (!$this->closed) {
            @curl_multi_close($this->handle);
        }
    }

    /**
     * @return \CurlMultiHandle|resource
     */
    public function getHandle()
    {
        if ($this->closed) {
            $this->handle = @curl_multi_init();
            $this->closed = false;
        }

        return $this->handle;
    }


    /**
     * @return static
     */
    public function closeHandle()
    {
        if (!$this->closed) {
            @curl_multi_close($this->handle);
            $this->closed = true;
            $this->ready = false;
            $this->handle = null;
        }

        return $this;
    }


    public function lock()
    {
        $this->locked = true;
    }

    public function unlock()
    {
        $this->locked = false;
    }

    public function isLocked()
    {
        return $this->locked;
    }

    /**
     * @return \Traversable<CurlRequest,CurlResponse>
     */
    public function getIterator()
    {
        if (is_array($this->results)) {
            foreach ($this->results as $uid => $response) {
                yield $this->resultRequests[$uid] => $response;
            }
        }

    }

    public function count()
    {
        return is_array($this->results) ? count($this->results) : 0;
    }
}