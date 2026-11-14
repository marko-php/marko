<?php

declare(strict_types=1);

namespace Marko\Http\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigException;
use Marko\Http\Contracts\HttpClientInterface;
use Marko\Http\Exceptions\ConnectionException;
use Marko\Http\Exceptions\HttpException;
use Marko\Http\Exceptions\InvalidRequestOptionException;
use Marko\Http\HttpResponse;
use Marko\Http\RequestOptions;

class GuzzleHttpClient implements HttpClientInterface
{
    /**
     * Driver-specific escape hatch: an array merged verbatim into the Guzzle
     * request options. Not portable across HttpClientInterface drivers.
     */
    public const string GUZZLE_OPTIONS = 'guzzle';

    /**
     * Matches absolute URLs in an exception message, capturing the scheme,
     * the optional userinfo, the host and path, and the optional query and
     * fragment, so the secrets in userinfo and the query can be redacted.
     */
    private const string URL_PATTERN = '~\b([a-z][a-z0-9+.\-]*://)([^\s/?#@`\'"<>]*@)?([^\s?#`\'"<>]*)([?#][^\s`\'"<>]*)?~i';

    private ?GuzzleClientInterface $client = null;

    public function __construct(
        private readonly ConfigRepositoryInterface $config,
    ) {}

    /**
     * @param array<string, mixed> $options
     *
     * @throws HttpException|ConnectionException|InvalidRequestOptionException
     */
    public function request(
        string $method,
        string $url,
        array $options = [],
    ): HttpResponse {
        $guzzleOptions = $this->buildOptions($url, $options);

        try {
            $response = $this->client()->request($method, $url, $guzzleOptions);

            return $this->toHttpResponse(
                $response->getStatusCode(),
                (string) $response->getBody(),
                $response->getHeaders(),
            );
        } catch (GuzzleConnectException $e) {
            throw new ConnectionException($this->redactUrls($e->getMessage()), previous: $e);
        } catch (GuzzleRequestException $e) {
            $response = $e->getResponse();
            $httpResponse = $response !== null
                ? $this->toHttpResponse(
                    $response->getStatusCode(),
                    (string) $response->getBody(),
                    $response->getHeaders(),
                )
                : null;

            throw new HttpException($this->redactUrls($e->getMessage()), $httpResponse, previous: $e);
        }
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws HttpException|ConnectionException|InvalidRequestOptionException
     */
    public function get(
        string $url,
        array $options = [],
    ): HttpResponse {
        return $this->request('GET', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws HttpException|ConnectionException|InvalidRequestOptionException
     */
    public function post(
        string $url,
        array $options = [],
    ): HttpResponse {
        return $this->request('POST', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws HttpException|ConnectionException|InvalidRequestOptionException
     */
    public function put(
        string $url,
        array $options = [],
    ): HttpResponse {
        return $this->request('PUT', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws HttpException|ConnectionException|InvalidRequestOptionException
     */
    public function patch(
        string $url,
        array $options = [],
    ): HttpResponse {
        return $this->request('PATCH', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws HttpException|ConnectionException|InvalidRequestOptionException
     */
    public function delete(
        string $url,
        array $options = [],
    ): HttpResponse {
        return $this->request('DELETE', $url, $options);
    }

    protected function createClient(): GuzzleClientInterface
    {
        return new Client();
    }

    private function client(): GuzzleClientInterface
    {
        return $this->client ??= $this->createClient();
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function buildOptions(
        string $url,
        array $options,
    ): array {
        RequestOptions::validate($options, [self::GUZZLE_OPTIONS]);

        $escapeHatch = $options[self::GUZZLE_OPTIONS] ?? [];

        if (!is_array($escapeHatch)) {
            throw InvalidRequestOptionException::invalidType(self::GUZZLE_OPTIONS, 'array', $escapeHatch);
        }

        unset($options[self::GUZZLE_OPTIONS]);

        $guzzleOptions = $options;
        unset($guzzleOptions[RequestOptions::RESOLVE_TO]);

        if (isset($options[RequestOptions::RESOLVE_TO])) {
            $guzzleOptions['curl'] = $this->pinnedCurlOptions(
                $url,
                (string) $options[RequestOptions::RESOLVE_TO],
                $escapeHatch,
            );
            unset($escapeHatch['curl']);
        }

        $guzzleOptions[RequestOptions::HTTP_ERRORS] = RequestOptions::throwsOnHttpError($options);
        $guzzleOptions[RequestOptions::TIMEOUT] ??= $this->timeoutFromConfig('timeout');
        $guzzleOptions[RequestOptions::CONNECT_TIMEOUT] ??= $this->timeoutFromConfig('connect_timeout');

        $bearerToken = RequestOptions::bearerToken($options);

        if ($bearerToken !== null) {
            unset($guzzleOptions[RequestOptions::AUTH]);
            $headers = $options[RequestOptions::HEADERS] ?? [];
            $headers['Authorization'] = "Bearer $bearerToken";
            $guzzleOptions[RequestOptions::HEADERS] = $headers;
        }

        if (is_int($options[RequestOptions::ALLOW_REDIRECTS] ?? null)) {
            $guzzleOptions[RequestOptions::ALLOW_REDIRECTS] = ['max' => $options[RequestOptions::ALLOW_REDIRECTS]];
        }

        return array_replace($guzzleOptions, $escapeHatch);
    }

    /**
     * The cURL options that pin the connection to $address: CURLOPT_RESOLVE maps the URL's
     * host and port to the address, so the Host header, TLS SNI and certificate check still
     * use the hostname. CURLOPT_FRESH_CONNECT stops cURL from reusing a pooled connection
     * that was opened to whatever address the host resolved to earlier.
     *
     * Only Guzzle's cURL handlers honour these options; its stream handler would silently
     * ignore them, so anything that routes the request there is rejected.
     *
     * @param array<mixed> $escapeHatch
     *
     * @return array<int, mixed>
     *
     * @throws InvalidRequestOptionException
     */
    private function pinnedCurlOptions(
        string $url,
        string $address,
        array $escapeHatch,
    ): array {
        $option = RequestOptions::RESOLVE_TO;

        if (!extension_loaded('curl')) {
            throw InvalidRequestOptionException::unsupportedByDriver(
                $option,
                self::class,
                'Pinning the connection needs the PHP curl extension; without it Guzzle falls back to its'
                . ' stream handler, which cannot pin a connection to an IP address.',
            );
        }

        if (!empty($escapeHatch['stream'])) {
            throw InvalidRequestOptionException::unsupportedByDriver(
                $option,
                self::class . " with the 'stream' Guzzle option",
                'Guzzle sends streamed requests through its stream handler, which cannot pin a connection'
                . ' to an IP address.',
            );
        }

        if (array_key_exists('proxy', $escapeHatch)) {
            throw InvalidRequestOptionException::conflictingOptions(
                $option,
                self::GUZZLE_OPTIONS . '.proxy',
                'A proxy resolves the destination host itself, so the connection cannot be pinned to an IP.',
            );
        }

        if (($escapeHatch[RequestOptions::ALLOW_REDIRECTS] ?? false) !== false) {
            throw InvalidRequestOptionException::pinnedRequestFollowsRedirects();
        }

        $curl = $escapeHatch['curl'] ?? [];

        if (!is_array($curl)) {
            throw InvalidRequestOptionException::invalidType(self::GUZZLE_OPTIONS . '.curl', 'array', $curl);
        }

        foreach ([CURLOPT_RESOLVE => 'CURLOPT_RESOLVE', CURLOPT_CONNECT_TO => 'CURLOPT_CONNECT_TO'] as $curlOption => $name) {
            if (array_key_exists($curlOption, $curl)) {
                throw InvalidRequestOptionException::conflictingOptions(
                    $option,
                    self::GUZZLE_OPTIONS . ".curl[$name]",
                    "$name decides which address cURL connects to, which is what '$option' sets.",
                );
            }
        }

        $parts = parse_url($url);
        $scheme = is_array($parts) ? strtolower($parts['scheme'] ?? '') : '';
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';

        if (
            !in_array($scheme, ['http', 'https'], true)
            || $host === ''
        ) {
            throw InvalidRequestOptionException::unpinnableUrl();
        }

        $pinned = [CURLOPT_FRESH_CONNECT => true];
        $literal = trim($host, '[]');

        if (filter_var($literal, FILTER_VALIDATE_IP) !== false) {
            // cURL never resolves an IP literal, so the URL itself must already name the pinned address.
            if (inet_pton($literal) !== inet_pton($address)) {
                throw InvalidRequestOptionException::conflictingOptions(
                    $option,
                    'the URL host',
                    "The URL names the IP address $literal, which cURL connects to directly instead of $address.",
                );
            }

            return array_replace($curl, $pinned);
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $target = str_contains($address, ':') ? "[$address]" : $address;
        $pinned[CURLOPT_RESOLVE] = ["$host:$port:$target"];

        return array_replace($curl, $pinned);
    }

    /**
     * @throws ConfigException
     */
    private function timeoutFromConfig(
        string $name,
    ): float {
        $key = "http-guzzle.$name";
        $seconds = $this->config->getFloat(key: $key);

        if ($seconds < 0) {
            throw new ConfigException(
                message: sprintf('Configuration key "%s" must not be negative', $key),
                context: sprintf('Got %s', $seconds),
                suggestion: sprintf(
                    'Set %s to a number of seconds (0 waits forever) in config/http-guzzle.php, or pass "%s" per request.',
                    $key,
                    $name,
                ),
            );
        }

        return $seconds;
    }

    /**
     * Guzzle puts the full request URL in its exception messages, and those
     * messages end up in logs. Strip userinfo and replace the query string so
     * credentials and API keys passed in the URL are not leaked. The original
     * Guzzle exception stays available as the previous exception.
     */
    private function redactUrls(
        string $message,
    ): string {
        return (string) preg_replace_callback(
            self::URL_PATTERN,
            static fn (array $matches): string => $matches[1]
                . (($matches[2] ?? '') !== '' ? '***@' : '')
                . $matches[3]
                . (str_starts_with($matches[4] ?? '', '?') ? '?…' : ''),
            $message,
        );
    }

    /**
     * headers() gets one string per header (repeated values joined with ", ");
     * headerValues() keeps every value, so Set-Cookie survives intact.
     *
     * @param array<string, array<string>> $rawHeaders
     */
    private function toHttpResponse(
        int $statusCode,
        string $body,
        array $rawHeaders,
    ): HttpResponse {
        $headerValues = [];

        foreach ($rawHeaders as $name => $values) {
            $headerValues[$name] = array_values($values);
        }

        return new HttpResponse(
            statusCode: $statusCode,
            body: $body,
            headers: $this->flattenHeaders($headerValues),
            headerValues: $headerValues,
        );
    }

    /**
     * Repeated header values are joined with ", ". This is lossy for headers
     * such as Set-Cookie whose values may themselves contain commas; use
     * HttpResponse::headerValues() to read each value.
     *
     * @param array<string, array<string>> $headers
     *
     * @return array<string, string>
     */
    private function flattenHeaders(
        array $headers,
    ): array {
        $flat = [];

        foreach ($headers as $name => $values) {
            $flat[$name] = implode(', ', $values);
        }

        return $flat;
    }
}
