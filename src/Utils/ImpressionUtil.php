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
use vwo\Enums\HeadersEnum;
use vwo\Services\SettingsService;
use vwo\Services\UrlService;
use vwo\Enums\UrlEnum;
use vwo\Packages\NetworkLayer\Manager\NetworkManager;
use vwo\Packages\NetworkLayer\Models\RequestModel;
use vwo\Packages\Logger\Core\LogManager;

class ImpressionUtil
{
    private $accountId;
    

    /**
     * Creates and sends an impression for a variation shown event.
     * This function constructs the necessary properties and payload for the event
     * and uses the NetworkUtil to send a POST API request.
     *
     * @param SettingsModel $settings - The settings model containing configuration.
     * @param int $campaignId - The ID of the campaign.
     * @param int $variationId - The ID of the variation shown to the user.
     * @param ContextModel $context - The user context model containing user-specific data.
     */
    public static function SendImpressionForVariationShown(
        SettingsModel $settings,
        $payload,
        ContextModel $context
    ) {
        // Get base properties for the event
        $networkUtil = new NetworkUtil();

        $properties = $networkUtil->getEventsBaseProperties(
            EventEnum::VWO_VARIATION_SHOWN,
            urlencode($context->getUserAgent()), // Encode user agent to ensure URL safety
            $context->getIpAddress()
        );

        // Send the constructed properties and payload as a POST request
        $networkUtil->sendPostApiRequest($properties, $payload);
    }

    /**
     * Sends a batch of events to the VWO server.
     *
     * @param array $batchPayload The batch payload to send.
     * @return bool True if the batch of events was sent successfully, false otherwise.
     */
    public static function SendImpressionForVariationShownInBatch($batchPayload) {
        return self::sendBatchEvents($batchPayload);
    }

    /**
     * Sends a batch of events to the VWO server.
     *
     * @param array $batchPayload The batch payload to send.
     * @return bool True if the batch of events was sent successfully, false otherwise.
     */
    private static function sendBatchEvents($batchPayload) {
        $accountId = SettingsService::instance()->accountId ?? null;
        $retryConfig = NetworkManager::instance()->getRetryConfig();

        $networkUtil = new NetworkUtil();
        $properties = $networkUtil->getEventBatchingQueryParams($accountId);
        $headers = [];
        $headers[HeadersEnum::AUTHORIZATION] = SettingsService::instance()->sdkKey;
        
        $eventCount = is_array($batchPayload) ? count($batchPayload) : 1;
        $batchPayload = [
            'ev' => $batchPayload
        ];


        $request = new RequestModel(
            UrlService::getBaseUrl(),
            'POST',
            UrlEnum::BATCH_EVENTS,
            $properties,
            $batchPayload,
            $headers,
            SettingsService::instance()->protocol,
            SettingsService::instance()->port,
            $retryConfig
        );

        try {
            $response = NetworkManager::instance()->post($request);
            $statusCode = $response->getStatusCode();
            
            // When shouldWaitForTrackingCalls is false, socket connections are used (fire-and-forget)
            // No status code is available, so we don't log success (we can't verify it)
            if ($statusCode === null) {
                LogManager::instance()->info('Impression sent to VWO server via socket connection for ' . $eventCount . ' events.');
                return true;
            }
            
            if ($statusCode == 200) {
                LogManager::instance()->info('Impression sent successfully for ' . $eventCount . ' events.');
                return true;
            } else {
                LogManager::instance()->error('Impression failed to send for ' . $eventCount . ' events');
                return false;
            }
        } catch (\Exception $e) {
            LogManager::instance()->error('Error occurred while sending impressions. Error:' . $e->getMessage());
            return false;
        }
    }
}

