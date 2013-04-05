<?php

namespace Lily\Adapter;

class HTTP
{
	private static $statusText = array(
		'100' => 'Continue',
		'101' => 'Switching Protocols',

		'200' => 'OK',
		'201' => 'Created',
		'202' => 'Accepted',
		'203' => 'Non-Authoritative Information',
		'204' => 'No Content',
		'205' => 'Reset Content',
		'206' => 'Partial Content',

		'300' => 'Multiple Choices',
		'301' => 'Moved Permanently',
		'302' => 'Found',
		'303' => 'See Other',
		'304' => 'Not Modified',
		'305' => 'Use Proxy',
		'307' => 'Temporary Redirect',

		'400' => 'Bad Request',
		'401' => 'Unauthorized',
		'402' => 'Payment Required',
		'403' => 'Forbidden',
		'404' => 'Not Found',
		'405' => 'Method Not Allowed',
		'406' => 'Not Acceptable',
		'407' => 'Proxy Authentication Required',
		'408' => 'Request Timeout',
		'409' => 'Conflict',
		'410' => 'Gone',
		'411' => 'Length Required',
		'412' => 'Precondition Failed',
		'413' => 'Request Entity Too Large',
		'414' => 'Request Uri Too Long',
		'415' => 'Unsupported Media Type',
		'416' => 'Requested Range Not Satisfiable',
		'417' => 'Expectation Failed',

		'500' => 'Internal Server Error',
		'501' => 'Not Implemented',
		'502' => 'Bad Gateway',
		'503' => 'Service Unavailable',
		'504' => 'Gateway Timeout',
		'505' => 'HTTP Version Not Supported',
	);

	private $forceSlowHeaders = FALSE;
	private $returnResponse = FALSE;

	public function __construct(array $config = NULL)
	{
		foreach (array('forceSlowHeaders', 'returnResponse') as $_setting) {
			if (isset($config[$_setting]) AND $config[$_setting] === TRUE) {
				$this->{$_setting} = TRUE;
			}
		}
	}

	private function forceSlowHeaders()
	{
		return $this->forceSlowHeaders;
	}

	private function returnResponse()
	{
		return $this->returnResponse;
	}

	private function host()
	{
		if (isset($_SERVER['SERVER_NAME'])) {
			return $_SERVER['SERVER_NAME'];	
		}
	}

	private function port()
	{
		if (isset($_SERVER['SERVER_PORT'])) {
			return $_SERVER['SERVER_PORT'];	
		}
	}

	private function addr()
	{
		if (isset($_SERVER['SERVER_ADDR'])) {
			return $_SERVER['SERVER_ADDR'];	
		}
	}

	private function remote_addr()
	{
		if (isset($_SERVER['REMOTE_ADDR'])) {
			return $_SERVER['REMOTE_ADDR'];	
		}
	}

	protected function default_protocol()
	{
		return 'http';
	}

	private function scheme()
	{
		if (isset($_SERVER['SERVER_PROTOCOL'])) {
			$parts = explode('/', $_SERVER['SERVER_PROTOCOL']);
			return strtolower($parts[0]);
		} else {
			return $this->default_protocol();
		}
	}

	private function method()
	{
		if (isset($_SERVER['REQUEST_METHOD'])) {
			return strtolower($_SERVER['REQUEST_METHOD']);
		} else {
			return 'get';
		}
	}

	private function uri()
	{
		if ( ! empty($_SERVER['PATH_INFO'])) {
			$uri = $_SERVER['PATH_INFO'];
		} elseif (isset($_SERVER['REQUEST_URI'])) {
			$uri = $_SERVER['REQUEST_URI'];

			// `parse_url()` cannot parse malformed URLs like
			// `http://localhost/http://example.com/index.php`
			// so only if truthy do we use what it returns,
			// otherwise we default to the raw `REQUEST_URI`
			if ($request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))
			{
				$uri = $request_uri;
			}

			$uri = rawurldecode($uri);

		} elseif (isset($_SERVER['PHP_SELF'])) {
			$uri = $_SERVER['PHP_SELF'];
		} elseif (isset($_SERVER['REDIRECT_URL'])) {
			$uri = $_SERVER['REDIRECT_URL'];
		} else {
			$uri = NULL;
		}

		return $uri;
	}

	private function slowHeaders()
	{
		$headers = array();

		if ( ! empty($_SERVER['CONTENT_TYPE'])) {
			$headers['content-type'] = $_SERVER['CONTENT_TYPE'];
		}

		if ( ! empty($_SERVER['CONTENT_LENGTH'])) {
			$headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
		}

		foreach ($_SERVER as $key => $value)
		{
			if (strpos($key, 'HTTP_') !== 0) {
				continue;
			}

			$key =
				strtolower(
					str_replace(
						array('HTTP_', '_'),
						array('', '-'),
						$key));
			$headers[$key] = $value;
		}

		return $headers;
	}

	private function headers()
	{
		$headers = array();

		if ( ! $this->forceSlowHeaders()) {
			if (function_exists('apache_request_headers')) {
				return apache_request_headers();
			} elseif (extension_loaded('http')) {
				return http_get_request_headers();
			}
		}

		return $this->slowHeaders();
	}

	private function query()
	{
		return $_GET;
	}

	private function post()
	{
		return $_POST;
	}

	private function params()
	{
		return $_POST + $_GET;
	}

	private function files()
	{
		return $_FILES;
	}

	private function parseRequest()
	{
		$headers = $this->headers();

		return array(
			'host' => $this->host(),
			'port' => $this->port(),
			'addr' => $this->addr(),

			'remote-addr' => $this->remote_addr(),
			
			'scheme' => $this->scheme(),
			'method' => $this->method(),
			'uri' => $this->uri(),
			'headers' => $headers,

			'type' => isset($headers['content-type']) ? $headers['content-type'] : NULL,
			'length' => isset($headers['content-length']) ? $headers['content-length'] : NULL,

			'query' => $this->query(),
			'post' => $this->post(),
			'params' => $this->params(),
			'files' => $this->files(),
		);
	}

	private function is_map(array $array)
	{
		// Keys of the array
		$keys = array_keys($array);

		// If the array keys of the keys match the keys, then the array must
		// not be map (e.g. the keys array looked like {0:0, 1:1...}).
		return array_keys($keys) !== $keys;
	}

	private function addDefaultHeadersToResponse(array $response)
	{
		$headers = $response['headers'];

		if (empty($headers['content-type'])) {
			$headers['content-type'] = 'text/html';
		}

		if (empty($headers['content-length'])) {
			if (function_exists('mb_strlen')) {
				$headers['content-length'] = mb_strlen($response['body']);
			} else {
				$headers['content-length'] = strlen($response['body']);
			}
		}

		$response['headers'] = $headers;
		return $response;
	}

	private function normaliseResponse($response)
	{
		if (is_string($response)) {
			$response = array(
				'status' => 200,
				'headers' => array(),
				'body' => $response,
			);
		} elseif ( ! $this->is_map($response)) {
			list($status, $headers, $body) = $response;
			$response = compact('status', 'headers', 'body');
		}

		return $this->addDefaultHeadersToResponse($response);
	}

	private function sendResponse(array $response)
	{
		$status = $response['status'];
		$statusText = static::$statusText[$status];
		header($statusText, TRUE, $status);

		foreach ($response['headers'] as $_header => $_value) {
			header("{$_header}: {$_value}");
		}

		echo $response['body'];
	}

	public function run($application)
	{
		$handler = $application->handler();
		$response = $this->normaliseResponse($handler($this->parseRequest()));

		if ($this->returnResponse()) {
			return $response;
		}
		
		$this->sendResponse($response);
	}
}
