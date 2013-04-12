<?php

namespace Lily\Util;

class RoutedApplication
{
    // Defines the pattern of a :param
    const REGEX_KEY = ':([a-zA-Z0-9_]++)';

    // What can be part of a :param value
    const REGEX_SEGMENT = '[^/.,;?\n]++';

    // What must be escaped in the route regex
    const REGEX_ESCAPE = '[.\\+*?[^\\]${}=!|<>]';

    public static function notFoundResponse()
    {
        return array(
            'status' => 404,
            'headers' => array(),
            'body' => 'Not found.',
        );
    }
    
    public static function normaliseRoute($route, $app)
    {
        if ( ! isset($route[3])) {
            $route[3] = array();
        }

        $route[3]['app'] = $app;

        return $route;
    }

    public static function methodMatches($request, $method)
    {
        return $method === NULL OR $request['method'] === $method;
    }

    public static function uriRegex($uri)
    {
        // The URI should be considered literal except for
        // keys and optional parts
        // Escape everything preg_quote would escape except
        // for : ( ) < >
        $expression = preg_replace(
            '#'.static::REGEX_ESCAPE.'#',
            '\\\\$0',
            $uri);

        if (strpos($expression, '(') !== FALSE) {
            // Make optional parts of the URI non-capturing
            // and optional
            $expression = str_replace(
                array('(', ')'),
                array('(?:', ')?'),
                $expression);
        }

        // Insert default regex for keys
        $replace = '#'.static::REGEX_KEY.'#';
        $expression = preg_replace(
            $replace,
            '(?P<$1>'.static::REGEX_SEGMENT.')',
            $expression);

        return '#^'.$expression.'$#uD';
    }

    public static function removeNumeric(array $mixedArray)
    {
        $assocArray = array();

        foreach ($mixedArray as $_k => $_v) {
            if (is_string($_k)) {
                $assocArray[$_k] = $_v;
            }
        }

        return $assocArray;
    }

    public static function uriMatches($request, $uri)
    {
        if ($uri === NULL) {
            return TRUE;

        // This match might be dangerous if the URL looks like
        // regex... might that happen?
        } elseif ($request['uri'] === $uri) {
            return TRUE;
        }

        $match =
            (bool) preg_match(
                static::uriRegex($uri),
                $request['uri'],
                $matches);

        if (isset($matches[1])) {
            return static::removeNumeric($matches);
        }

        return $match;
    }

    private $routes = array();

    public function __construct(array $routes = NULL)
    {
        if ($routes !== NULL) {
            $this->routes = $routes;
        }
    }

    protected function routes()
    {
        return $this->routes;
    }

    public function handler()
    {
        $app = $this;
        $routes = $this->routes();

        return function ($request) use ($app, $routes) {
            foreach ($routes as $_route) {
                list($method, $uri, $handler, $additionalRequest) =
                    RoutedApplication::normaliseRoute($_route, $app);

                $request += $additionalRequest;

                if ( ! RoutedApplication::methodMatches($request, $method)) {
                    continue;
                }

                $params = RoutedApplication::uriMatches($request, $uri);

                if ( ! $params) {
                    continue;
                }

                if (is_array($params)) {
                    $request['route-params'] = $params;
                    $request['params'] = $params + $request['params'];
                }

                if (is_callable($handler)) {
                    $response = $handler($request);
                } else {
                    $response = $handler;
                }

                if ($response !== FALSE) {
                    return $response;
                }
            }

            return RoutedApplication::notFoundResponse();
        };
    }

    public function uri($name, array $params = array())
    {
        $routes = $this->routes();
        $uri = $routes[$name][1];

        foreach ($params as $_k => $_v) {
            $uri = str_replace(":{$_k}", $_v, $uri);
        }

        return $uri;
    }
}
