<?php

declare(strict_types=1);

namespace Vatly\API\HttpClient;

use Composer\CaBundle\CaBundle;
use Vatly\API\Exceptions\ApiException;
use Vatly\API\Exceptions\CurlConnectTimeoutException;
use Vatly\API\VatlyApiClient;

class CurlHttpClient implements HttpClientInterface
{
    /**
     * Default response timeout (in seconds).
     */
    public const DEFAULT_TIMEOUT = 10;

    /**
     * Default connect timeout (in seconds).
     */
    public const DEFAULT_CONNECT_TIMEOUT = 2;

    /**
     * The maximum number of retries
     */
    public const MAX_RETRIES = 5;

    /**
     * The amount of milliseconds the delay is being increased with on each retry.
     */
    public const DELAY_INCREASE_MS = 1000;

    /**
     * Make a http request.
     *
     * @param string $httpMethod
     * @param string $url
     * @param array $headers
     * @param ?string $httpBody
     * @return object|null
     * @throws \Vatly\API\Exceptions\ApiException
     * @throws \Vatly\API\Exceptions\CurlConnectTimeoutException
     */
    public function send(string $httpMethod, string $url, array $headers, ?string $httpBody): ?object
    {
        for ($i = 0; $i <= self::MAX_RETRIES; $i++) {
            usleep($i * self::DELAY_INCREASE_MS);

            try {
                return $this->attemptRequest($httpMethod, $url, $headers, $httpBody);
            } catch (CurlConnectTimeoutException $e) {
                // Nothing
            }
        }

        throw new CurlConnectTimeoutException(
            "Unable to connect to Vatly. Maximum number of retries (". self::MAX_RETRIES .") reached."
        );
    }

    /**
     * @param string $httpMethod
     * @param string $url
     * @param array $headers
     * @param string $httpBody
     * @return object|null
     * @throws \Vatly\API\Exceptions\ApiException
     */
    protected function attemptRequest(string $httpMethod, string $url, array $headers, ?string $httpBody): ?object
    {
        $curl = curl_init($url);
        $headers["Content-Type"] = "application/json";

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->parseHeaders($headers));
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, self::DEFAULT_CONNECT_TIMEOUT);
        curl_setopt($curl, CURLOPT_TIMEOUT, self::DEFAULT_TIMEOUT);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_CAINFO, CaBundle::getBundledCaBundlePath());

        switch ($httpMethod) {
            case VatlyApiClient::HTTP_POST:
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS,  $httpBody);

                break;
            case VatlyApiClient::HTTP_GET:
                break;
            case VatlyApiClient::HTTP_PATCH:
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
                curl_setopt($curl, CURLOPT_POSTFIELDS, $httpBody);

                break;
            case VatlyApiClient::HTTP_DELETE:
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                curl_setopt($curl, CURLOPT_POSTFIELDS,  $httpBody);

                break;
            default:
                throw new \InvalidArgumentException("Invalid http method: ". $httpMethod);
        }

        $startTime = microtime(true);
        $response = curl_exec($curl);
        $endTime = microtime(true);

        if ($response === false) {
            $executionTime = $endTime - $startTime;
            $curlErrorNumber = curl_errno($curl);
            $curlErrorMessage = "Curl error: " . curl_error($curl);

            if ($this->isConnectTimeoutError($curlErrorNumber, $executionTime)) {
                throw new CurlConnectTimeoutException("Unable to connect to Vatly. " . $curlErrorMessage);
            }

            throw new ApiException($curlErrorMessage);
        }

        $statusCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return $this->parseResponseBody($response, $statusCode, $httpBody);
    }

    /**
     * The version number for the underlying http client, if available.
     * @example Guzzle/6.3
     *
     * @return string
     */
    public function versionString(): string
    {
        return 'Curl/*';
    }

    /**
     * Whether this http adapter provides a debugging mode. If debugging mode is enabled, the
     * request will be included in the ApiException.
     *
     * @return false
     */
    public function supportsDebugging(): bool
    {
        return false;
    }

    /**
     * @param int $curlErrorNumber
     * @param string|float $executionTime
     * @return bool
     */
    protected function isConnectTimeoutError(int $curlErrorNumber, $executionTime): bool
    {
        $connectErrors = [
            \CURLE_COULDNT_RESOLVE_HOST => true,
            \CURLE_COULDNT_CONNECT => true,
            \CURLE_SSL_CONNECT_ERROR => true,
            \CURLE_GOT_NOTHING => true,
        ];

        if (isset($connectErrors[$curlErrorNumber])) {
            return true;
        };

        if ($curlErrorNumber === \CURLE_OPERATION_TIMEOUTED) {
            if ($executionTime > self::DEFAULT_TIMEOUT) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * @param string $response
     * @param int $statusCode
     * @param string $httpBody
     * @return object|null
     * @throws \Vatly\Api\Exceptions\ApiException
     */
    protected function parseResponseBody(string $response, int $statusCode, ?string $httpBody): ?object
    {
        if (empty($response)) {
            if ($statusCode === VatlyApiClient::HTTP_NO_CONTENT) {
                return null;
            }

            throw new ApiException("No response body found.");
        }

        $body = @json_decode($response);

        // GUARDS
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException("Unable to decode Vatly response: '{$response}'.");
        }

        if (isset($body->error)) {
            throw new ApiException($body->error->message);
        }

        if ($statusCode >= 400) {
            $message = "Error {$statusCode} executing API call: {$body->message})";

            $field = null;

            if (! empty($body->field)) {
                $field = $body->field;
            }

            if (isset($body->links, $body->links->documentation)) {
                $message .= ". Documentation: {$body->links->documentation->href}";
            }

            if ($httpBody) {
                $message .= ". Request body: {$httpBody}";
            }

            throw new ApiException($message, $statusCode, $field);
        }

        return $body;
    }

    protected function parseHeaders(array $headers): array
    {
        $result = [];

        foreach ($headers as $key => $value) {
            $result[] = $key .': ' . $value;
        }

        return $result;
    }

    public function enableDebugging(): void
    {
        //
    }

    public function disableDebugging(): void
    {
        //
    }
}
