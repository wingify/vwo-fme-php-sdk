<?php

/**
 * Copyright 2024-2025 Wingify Software Pvt. Ltd.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace vwo\Packages\NetworkLayer\Client;

use vwo\Packages\NetworkLayer\Models\ResponseModel;
use vwo\Packages\NetworkLayer\Models\RequestModel;
use vwo\Services\LoggerService;
use vwo\Enums\LogLevelEnum;
use vwo\Constants\Constants;

class NetworkClient implements NetworkClientInterface
{
    const HTTPS = 'HTTPS';
    const DEFAULT_TIMEOUT = 5; // seconds
    private $isGatewayUrlNotSecure = false; // Flag to store the value
    private $shouldWaitForTrackingCalls = false;
    private $retryConfig = Constants::DEFAULT_RETRY_CONFIG;

    // Constructor to accept options and store the flag
    public function __construct($options = []) {
        if (isset($options['isGatewayUrlNotSecure'])) {
            $this->isGatewayUrlNotSecure = $options['isGatewayUrlNotSecure'];
        }
        if (isset($options['shouldWaitForTrackingCalls'])) {
            $this->shouldWaitForTrackingCalls = $options['shouldWaitForTrackingCalls'];
        }
        if(isset($options['retryConfig'])) {
            $this->retryConfig = $options['retryConfig'];
        }
    }

    private function shouldUseCurl($networkOptions)
    {
        if ($this->isGatewayUrlNotSecure || $this->shouldWaitForTrackingCalls) {
            return true;
        }
        return false;
    }

    private function makeCurlRequest($url, $method, $headers, $body = null, $timeout = 5, $retryConfig = [])
    {
        // Merge with defaults to ensure all keys are present
        $retryConfig = array_merge(Constants::DEFAULT_RETRY_CONFIG, $retryConfig);
        
        $maxRetries = $retryConfig[Constants::RETRY_MAX_RETRIES];
        $baseDelay = $retryConfig[Constants::RETRY_INITIAL_DELAY];
        $multiplier = $retryConfig[Constants::RETRY_BACKOFF_MULTIPLIER];
        $shouldRetry = $retryConfig[Constants::RETRY_SHOULD_RETRY];

        $retryDelays = [];
        for ($i = 0; $i < $maxRetries; $i++) {
            $retryDelays[$i] = $baseDelay * ($i === 0 ? 1 : pow($multiplier, $i));
        }
        $lastError = null;

        for ($attempt = 0; $attempt < $maxRetries && $shouldRetry === true; $attempt++) {
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
            ]);

            if (!isset($headers['Content-Type'])) {
                $headers['Content-Type'] = 'application/json';
            }

            // Set headers
            $curlHeaders = [];
            if (!empty($headers)) {
                foreach ($headers as $key => $value) {
                   $curlHeaders[] = "$key: $value"; 
                }
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);

            // Set body for POST requests
            if ($method === 'POST' && $body) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                if (defined('CURLOPT_POSTREDIR')) {
                    curl_setopt($ch, CURLOPT_POSTREDIR, 3); // keep POST on 301/302 redirects
                }
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);

            // Check if request was successful
            if (!$error && $httpCode >= 200 && $httpCode < 300) {
                return [
                    'body' => $response,
                    'status_code' => $httpCode
                ];
            }

            // Store the error for potential re-throwing
            $lastError = $error ? "cURL error: $error" : "HTTP error: $httpCode";
            
            // If this is not the last attempt, wait before retrying
            if ($attempt < $maxRetries ) {
                $delay = $retryDelays[$attempt] ?? $baseDelay;
                
                LoggerService::error('NETWORK_CALL_RETRY_ATTEMPT', [
                    'endPoint' => $url,
                    'err' => $lastError,
                    'delay' => $delay,
                    'attempt' => $attempt + 1,
                    'maxRetries' => $maxRetries
                ]);
                sleep($delay);
            } else {
                LoggerService::error('NETWORK_CALL_RETRY_FAILED', [
                    'endPoint' => $url,
                    'err' => $lastError
                ]);
            }
        }

        // If we get here, all retries failed
        throw new \Exception($lastError);
    }

    private function createSocketConnection($url, $timeout)
    {
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'];
        $port = isset($parsedUrl['port']) ? $parsedUrl['port'] : 443;
        $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '/';
        $query = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';

        // For HTTPS, we need to use ssl:// prefix
        $socket = fsockopen(
            "ssl://{$host}",
            $port,
            $errno,
            $errstr,
            $timeout
        );

        if (!$socket) {
            throw new \Exception("Failed to connect: $errstr ($errno)");
        }

        // Set socket timeout
        stream_set_timeout($socket, $timeout);
        return $socket;
    }

    private function sendRequest($socket, $method, $path, $headers, $body = null, $originalUrl = null)
    {
        if($method == "POST") {
            stream_set_blocking($socket, false);
        }
        $request = "$method $path HTTP/1.1\r\n";
        
        // Get host from the original URL, not from the path
        $host = '';
        if ($originalUrl) {
            $parsedUrl = parse_url($originalUrl);
            $host = $parsedUrl['host'];
            if (isset($parsedUrl['port']) && $parsedUrl['port'] != 443) {
                $host .= ':' . $parsedUrl['port'];
            }
        }
        $request .= "Host: $host\r\n";
        
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }
        
        //only for non empty headers
        if (!empty($headers)) {
            foreach ($headers as $key => $value) {
                $request .= "$key: $value\r\n";
            }
        }
        
        if ($body) {
            $request .= "Content-Length: " . strlen($body) . "\r\n";
            $request .= "\r\n";
            $request .= $body;
        } else {
            $request .= "\r\n";
        }
        $request .= 'Connection: close'. "\r\n\r\n";

        fwrite($socket, $request);
    }

    private function readResponse($socket)
    {
        $headers = '';
        $body = '';
        $contentLength = 0;
        $isReadingHeaders = true;

        // Read headers first
        while ($isReadingHeaders && !feof($socket)) {
            $line = fgets($socket, 1024);
            if ($line === false) {
                break;
            }
            
            $headers .= $line;
            // Check for Content-Length header
            if (stripos($line, 'Content-Length:') === 0) {
                $contentLength = (int)trim(substr($line, 16));
            }
            
            // End of headers
            if ($line === "\r\n") {
                $isReadingHeaders = false;
            }
        }

        // Read body if we have content length
        if ($contentLength > 0) {
            $bytesRead = 0;
            while ($bytesRead < $contentLength && !feof($socket)) {
                $chunk = fread($socket, min(1024, $contentLength - $bytesRead));
                if ($chunk === false) {
                    break;
                }
                $body .= $chunk;
                $bytesRead += strlen($chunk);
            }
        }

        return [
            'headers' => $headers,
            'body' => $body
        ];
    }

    public function GET($request)
    {
        $networkOptions = $request->getOptions();
        $responseModel = new ResponseModel();

        try {
            $curlResponse = $this->makeCurlRequest(
                $networkOptions['url'],
                'GET',
                $networkOptions['headers'],
                null,
                $networkOptions['timeout'] / 1000
            );
            $rawResponse = $curlResponse['body'];
            $responseModel->setStatusCode($curlResponse['status_code']);
            

            $parsedData = json_decode($rawResponse, false);
            if ($parsedData === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Failed to parse JSON response: " . json_last_error_msg());
            }

            $responseModel->setData($parsedData);
        } catch (\Exception $e) {
            $responseModel->setError($e->getMessage());
        }

        return $responseModel;
    }

    public function POST($request)
    {
        $networkOptions = $request->getOptions();
        $responseModel = new ResponseModel();

        // NetworkManager ensures retryConfig is set and validated in options
        $retryConfig = $request->getRetryConfig();

        try {
            // Check if we should use cURL (either for synchronous or gateway fallback)
            if ($this->shouldUseCurl($networkOptions)) {
                $curlResponse = $this->makeCurlRequest(
                    $networkOptions['url'],
                    'POST',
                    $networkOptions['headers'],
                    $networkOptions['body'],
                    $networkOptions['timeout'] / 1000,
                    $retryConfig
                );
                
                $rawResponse = $curlResponse['body'];
                $responseModel->setStatusCode($curlResponse['status_code']);
                
                // If body is empty or whitespace, don't attempt JSON parse
                if (is_string($rawResponse) && trim($rawResponse) === '') {
                    $responseModel->setData(null);
                } else {
                    $parsedData = json_decode($rawResponse, false);
                    if ($parsedData === null && json_last_error() !== JSON_ERROR_NONE) {
                        // Non-JSON response; keep status code and set error, but do not throw
                        $responseModel->setError('Non-JSON response body');
                        $responseModel->setData(null);
                    } else {
                        $responseModel->setData($parsedData);
                    }
                }
            } else {
                // Use socket connection (existing logic)
                $socket = $this->createSocketConnection(
                    $networkOptions['url'],
                    $networkOptions['timeout'] / 1000
                );

                $parsedUrl = parse_url($networkOptions['url']);
                $path = $parsedUrl['path'] . (isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '');

                $this->sendRequest($socket, 'POST', $path, $networkOptions['headers'], $networkOptions['body'], $networkOptions['url']);
                $rawResponse = $this->readResponse($socket);
                fclose($socket);
                
                $parsedBody = $rawResponse['body'];
                if (is_string($parsedBody) && trim($parsedBody) === '') {
                    $responseModel->setData(null);
                } else {
                    $parsedData = json_decode($parsedBody, false);
                    if ($parsedData === null && json_last_error() !== JSON_ERROR_NONE) {
                        $responseModel->setError('Non-JSON response body');
                        $responseModel->setData(null);
                    } else {
                        $responseModel->setData($parsedData);
                    }
                }
            }

        } catch (\Exception $e) {
            $responseModel->setError($e->getMessage());
        }

        return $responseModel;
    }
}
