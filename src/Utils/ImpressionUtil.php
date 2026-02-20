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

namespace vwo\Utils;

use vwo\Models\SettingsModel;
use vwo\Enums\EventEnum;
use vwo\Utils\NetworkUtil;
use vwo\Models\User\ContextModel;
use vwo\Services\ServiceContainer;
use vwo\Enums\HeadersEnum;
use vwo\Services\SettingsService;
use vwo\Services\UrlService;
use vwo\Enums\UrlEnum;
use vwo\Packages\NetworkLayer\Manager\NetworkManager;
use vwo\Packages\NetworkLayer\Models\RequestModel;
use vwo\Packages\Logger\Core\LogManager;
use vwo\Enums\HttpMethodEnum;
use vwo\Packages\NetworkLayer\Models\ResponseModel;
use vwo\Services\LoggerService;
use vwo\Enums\ApiEnum;
use vwo\Utils\DebuggerServiceUtil;
use vwo\Constants\Constants;

class ImpressionUtil
{
    /**
     * Creates and sends an impression for a variation shown event.
     * This function constructs the necessary properties and payload for the event
     * and uses the NetworkUtil to send a POST API request.
     *
     * @param ServiceContainer $serviceContainer - The service container.
     * @param array $payload - The variation shown event payload.
     * @param ContextModel $context - The user context model containing user-specific data.
     */
    public static function SendImpressionForVariationShown(
        ServiceContainer $serviceContainer,
        $payload,
        ContextModel $context,
        $featureKey
    ) {
        // Get base properties for the event
        $networkUtil = new NetworkUtil($serviceContainer);
        $settings = $serviceContainer->getSettings();

        $properties = $networkUtil->getEventsBaseProperties(
            EventEnum::VWO_VARIATION_SHOWN,
            urlencode($context->getUserAgent()), // Encode user agent to ensure URL safety
            $context->getIpAddress()
        );

        $campaignKeyWithFeatureName = CampaignUtil::getCampaignKeyFromCampaignId($settings, $payload['d']['event']['props']['id']);
        // Get the variation name for the campaignId and variationId
        $variationName = CampaignUtil::GetVariationNameFromCampaignIdAndVariationId($settings, $payload['d']['event']['props']['id'], $payload['d']['event']['props']['variation']);
        $campaignKey = "";
        if ($featureKey == $campaignKeyWithFeatureName)
        {
            $campaignKey = Constants::IMPACT_ANALYSIS;
        } else {
            // Otherwise, split the campaignKeyWithFeatureName and get the part after featureKey + "_"
            $prefix = $featureKey . "_";
            if (!empty($campaignKeyWithFeatureName) && strpos($campaignKeyWithFeatureName, $prefix) === 0)
            {
                $campaignKey = substr($campaignKeyWithFeatureName, strlen($prefix));
            }
            else
            {
                $campaignKey = $campaignKeyWithFeatureName;
            }
        }

        // Get the campaign type from campaignId
        $campaignType = CampaignUtil::GetCampaignTypeFromCampaignId($settings, $payload['d']['event']['props']['id']);

        $campaignInfo = array(
            "campaignKey" => $campaignKey,
            "variationName" => $variationName,
            "featureKey" => $featureKey,
            "campaignType" => $campaignType
        );
        
        // Send the constructed properties and payload as a POST request
        $networkUtil->sendPostApiRequest($properties, $payload, $context->getId(), [], $campaignInfo);
    }

    /**
     * Sends a batch of events to the VWO server.
     *
     * @param array $batchPayload The batch payload to send.
     * @return bool True if the batch of events was sent successfully, false otherwise.
     */
    public static function SendImpressionForVariationShownInBatch($batchPayload, ServiceContainer $serviceContainer) {
        return self::sendBatchEvents($batchPayload, $serviceContainer);
    }

    /**
     * Sends a batch of events to the VWO server.
     *
     * @param array $batchPayload The batch payload to send.
     * @return bool True if the batch of events was sent successfully, false otherwise.
     */
    private static function sendBatchEvents($batchPayload, ServiceContainer $serviceContainer) {
        $accountId = $serviceContainer->getSettingsService()->accountId ?? null;
        $retryConfig = $serviceContainer->getNetworkManager()->getRetryConfig();

        $networkUtil = new NetworkUtil($serviceContainer);
        $properties = $networkUtil->getEventBatchingQueryParams($accountId);
        $headers = [];
        $headers[HeadersEnum::AUTHORIZATION] = $serviceContainer->getSettingsService()->sdkKey;
        
        $eventCount = is_array($batchPayload) ? count($batchPayload) : 1;
        $batchPayload = [
            'ev' => $batchPayload
        ];

        $settingsService = $serviceContainer->getSettingsService();
        $request = new RequestModel(
            $settingsService->hostname,
            'POST',
            UrlService::getEndpointWithCollectionPrefix(UrlEnum::BATCH_EVENTS),
            $properties,
            $batchPayload,
            $headers,
            $serviceContainer->getSettingsService()->protocol,
            $serviceContainer->getSettingsService()->port,
            $retryConfig
        );

        // count the events present in payload to send the extraData in debug event
        $eventCount = count($batchPayload['ev']);
        $extraData = "getFlag events: " . $eventCount;

        try {
            $response = $serviceContainer->getNetworkManager()->post($request);
            $statusCode = $response->getStatusCode();

            // create debug event for batch events
            if ($statusCode !== null && $response->getTotalAttempts() > 0) {
                $debugEventProps = NetworkUtil::createNetworkAndRetryDebugEvent($response, $batchPayload, UrlEnum::BATCH_EVENTS, $extraData);
                $debugEventProps["uuid"] = $request->getUuid();

                DebuggerServiceUtil::sendDebugEventToVWO($debugEventProps);
            }
            
            // Handle batch response with comprehensive error handling
            $result = self::handleBatchResponse(
                $serviceContainer->getLoggerService(),
                UrlEnum::BATCH_EVENTS,
                $batchPayload,
                $properties,
                null,
                $response
            );
            
            return $result['status'] === 'success';
            
        } catch (\Exception $e) {
            // Handle exception case
            $result = self::handleBatchResponse(
                $serviceContainer->getLoggerService(),
                UrlEnum::BATCH_EVENTS,
                $batchPayload,
                $properties,
                $e,
                null
            );
            
            return false;
        }
    }

    /**
     * Handles the batch response from the VWO server.
     * Processes the response, logs appropriate messages, and returns the result.
     *
     * @param LogManager $logManager The log manager instance
     * @param string $endPoint The endpoint URL enum value
     * @param array $payload The batch payload containing events
     * @param array $queryParams The query parameters including account ID
     * @param \Exception|null $err Any error that occurred
     * @param ResponseModel|null $res The response model from the network call
     * @return array Associative array with 'status' and 'events' keys
     */
    private static function handleBatchResponse(
        LoggerService $loggerService,
        $endPoint,
        $payload,
        $queryParams,
        $err,
        $res
    ) {
        $eventsPerRequest = isset($payload['ev']) ? count($payload['ev']) : 0;
        $accountId = $queryParams['a'] ?? null;
        $error = $err ? $err : ($res ? $res->getError() : null);

        // Convert error to Exception if it's not already
        if ($error && !($error instanceof \Exception)) {
            if (is_string($error)) {
                $error = new \Exception($error);
            } else if (is_object($error) || is_array($error)) {
                $error = new \Exception(json_encode($error));
            }
        }

        // Handle error cases
        if ($error) {
            $loggerService->info('IMPRESSION_BATCH_FAILED');
            $loggerService->error('NETWORK_CALL_FAILED', [
                'method' => HttpMethodEnum::POST,
                'err' => $error->getMessage(),
            ]);
            return ['status' => 'error', 'events' => $payload];
        }

        $statusCode = $res ? $res->getStatusCode() : null;

        // Success case (treat any 2xx as success, for both cURL and socket flows)
        if (
            $statusCode !== null &&
            $statusCode >= Constants::HTTP_SUCCESS_MIN &&
            $statusCode <= Constants::HTTP_SUCCESS_MAX
        ) {
            $loggerService->info('IMPRESSION_BATCH_SUCCESS', [
                'accountId' => $accountId,
                'endPoint' => $endPoint,
            ]);
            return ['status' => 'success', 'events' => $payload];
        }

        /**
         * Socket-based fire-and-forget case:
         * - Request was dispatched successfully
         * - But we didn't wait for / receive an HTTP response, so statusCode is null.
         *
         * In this scenario, treat the batch as success instead of logging a failure.
         */
        if ($statusCode === null && $error === null) {
            $loggerService->info('IMPRESSION_BATCH_SUCCESS', [
                'accountId' => $accountId,
                'endPoint' => $endPoint,
            ]);
            return ['status' => 'success', 'events' => $payload];
        }

        // Other error cases (non-2xx status without explicit error object)
        $loggerService->error('IMPRESSION_BATCH_FAILED', [], false);
        $errorMessage = $error ? $error->getMessage() : 'Unknown error';
        $loggerService->error('NETWORK_CALL_FAILED', [
            'method' => HttpMethodEnum::POST,
            'err' => $errorMessage,
        ]);
        return ['status' => 'error', 'events' => $payload];
    }
}

