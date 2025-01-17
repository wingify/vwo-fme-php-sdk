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
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class NetworkClient implements NetworkClientInterface
{
    const HTTPS = 'HTTPS';

    private $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function GET($request)
    {
        $networkOptions = $request->getOptions();
        $responseModel = new ResponseModel();

        try {
            $response = $this->client->request('GET', $networkOptions['url'], [
                'headers' => $networkOptions['headers'],
                'timeout' => $networkOptions['timeout'] / 1000 // Convert milliseconds to seconds
            ]);

            $contentType = $response->getHeaderLine('content-type');
            $rawData = (string) $response->getBody();

            if (!preg_match('/^application\/json/', $contentType)) {
                $error = "Invalid content-type.\nExpected application/json but received $contentType";
                $responseModel->setError($error);
                return $responseModel;
            } else {
                $parsedData = json_decode($rawData, false);
                $responseModel->setStatusCode($response->getStatusCode());

                if ($response->getStatusCode() !== 200) {
                    $error = "Request failed for fetching account settings. Got Status Code: {$response->getStatusCode()} and message: $rawData";
                    $responseModel->setError($error);
                } else {
                    $responseModel->setData($parsedData);
                }
            }
        } catch (RequestException $e) {
            $responseModel->setError($e->getMessage());
        } catch (\Exception $e) {
            $responseModel->setError($e->getMessage());
        }

        return $responseModel;
    }

    public function POST($request)
    {
        $networkOptions = $request->getOptions();
        $responseModel = new ResponseModel();

        try {
            $response = $this->client->request('POST', $networkOptions['url'], [
                'headers' => $networkOptions['headers'],
                'json' => json_decode($networkOptions['body'], true),
                'timeout' => $networkOptions['timeout'] / 1000 // Convert milliseconds to seconds
            ]);

            $rawData = (string) $response->getBody();

            if ($response->getStatusCode() === 200) {
                $responseModel->setData($request->getBody());
            } elseif ($response->getStatusCode() === 413) {
                $parsedData = json_decode($rawData, true);
                $responseModel->setError($parsedData['error']);
                $responseModel->setData($request->getBody());
            } else {
                $parsedData = json_decode($rawData, true);
                $responseModel->setError($parsedData['message']);
                $responseModel->setData($request->getBody());
            }
        } catch (RequestException $e) {
            $responseModel->setError($e->getMessage());
            $responseModel->setData($request->getBody());
        } catch (\Exception $e) {
            $responseModel->setError($e->getMessage());
            $responseModel->setData($request->getBody());
        }

        return $responseModel;
    }
}
