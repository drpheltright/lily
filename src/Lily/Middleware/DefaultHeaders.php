<?php

namespace Lily\Middleware;

class DefaultHeaders
{
    private $headers;

    public function __construct($headers)
    {
        $this->headers = $headers;
    }

    public function wrapHandler($handler)
    {
        $headers = $this->headers;

        return function ($request) use ($handler, $headers) {
            $response = $handler($request);

            foreach ($headers as $_header => $_v) {
                if ( ! isset($response['headers'][$_header])) {
                    $response['headers'][$_header] = $_v;
                }
            }

            return $response;
        };
    }
}
