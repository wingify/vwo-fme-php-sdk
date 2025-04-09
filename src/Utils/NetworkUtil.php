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

use vwo\Utils\FunctionUtil;
use vwo\Utils\UuidUtil;
use vwo\Constants\Constants;
use vwo\Utils\UrlUtil;
use vwo\Enums\UrlEnum;
use vwo\Packages\Logger\Core\LogManager;
use vwo\Utils\DataTypeUtil;
use vwo\Packages\NetworkLayer\Manager\NetworkManager;
use vwo\Enums\HeadersEnum;
use vwo\Packages\NetworkLayer\Models\RequestModel;
use vwo\Utils\ComposerUtil;
use Exception;
use vwo\Services\SettingsService;
use vwo\Services\UrlService;

class NetworkUtil {
  public function getBasePropertiesForBulk($accountId, $userId) {
        $path = [
            'sId' => FunctionUtil::getCurrentUnixTimestamp(),
            'u' => UuidUtil::getUUID($userId, $accountId),
        ];
        return $path;
    }

  public function getSettingsPath($apikey, $accountId) {
        $path = [
            'i' => $apikey,
            'r' => mt_rand(),
            'a' => $accountId,
        ];
        return $path;
    }

    public function getTrackEventPath($event, $accountId, $userId)
    {
        try {
            $sdkVersion = ComposerUtil::getSdkVersion();
        } catch (Exception $e) {
            LogManager::instance()->error($e->getMessage());
            $sdkVersion = Constants::SDK_VERSION; // Use the constant as a fallback
        }

        $path = [
            'event_type' => $event,
            'account_id' => $accountId,
            'uId' => $userId,
            'u' => UuidUtil::getUUID($userId, $accountId),
            'sdk' => Constants::SDK_NAME,
            'sdk-v' => $sdkVersion,
            'random' => FunctionUtil::getRandomNumber(),
            'ap' => Constants::AP,
            'sId' => FunctionUtil::getCurrentUnixTimestamp(),
            'ed' => json_encode(['p' => 'server']),
        ];

        return $path;
    }

    public function getEventBatchingQueryParams($accountId)
    {
        try {
            $sdkVersion = ComposerUtil::getSdkVersion();
        } catch (Exception $e) {
            LogManager::instance()->error($e->getMessage());
            $sdkVersion = Constants::SDK_VERSION; // Use the constant as a fallback
        }

        $path = [
            'a' => $accountId,
            'sd' => Constants::SDK_NAME,
            'sv' => $sdkVersion,
        ];

        return $path;
    }

  public function getEventsBaseProperties($setting, $eventName, $visitorUserAgent = '', $ipAddress = '') {
        $sdkKey = $setting->getSdkkey();;

        $properties = [
            'en' => $eventName,
            'a' => $setting->getAccountId(),
            'env' => $sdkKey,
            'eTime' => FunctionUtil::getCurrentUnixTimestampInMillis(),
            'random' => FunctionUtil::getRandomNumber(),
            'p' => 'FS'
        ];
        
        if (!empty($visitorUserAgent) && $visitorUserAgent !== null) {
            $properties['visitor_ua'] = $visitorUserAgent;
        }

        if (!empty($ipAddress) && $ipAddress !== null) {
            $properties['visitor_ip'] = $ipAddress;
        }

        $properties['url'] = Constants::HTTPS_PROTOCOL . UrlService::getBaseUrl() . UrlEnum::EVENTS;
        return $properties;
    }

  public function getEventBasePayload($settings, $userId, $eventName, $visitorUserAgent = '', $ipAddress = '') {
        $uuid = UuidUtil::getUUID($userId, $settings->getAccountId());
        $sdkKey = $settings->getSdkKey();

        try {
            $sdkVersion = ComposerUtil::getSdkVersion();
        } catch (Exception $e) {
            LogManager::instance()->error($e->getMessage());
            $sdkVersion = Constants::SDK_VERSION; // Use the constant as a fallback
        }

        $props = [
            'vwo_sdkName' => Constants::SDK_NAME,
            'vwo_sdkVersion' => $sdkVersion,
            'vwo_envKey' => $sdkKey,
        ];

        $properties = [
            'd' => [
                'msgId' => "{$uuid}-" . FunctionUtil::getCurrentUnixTimestampInMillis(),
                'visId' => $uuid,
                'sessionId' => FunctionUtil::getCurrentUnixTimestamp(),
                'event' => [
                    'props' => $props,
                    'name' => $eventName,
                    'time' => FunctionUtil::getCurrentUnixTimestampInMillis(),
                ],
                'visitor' => [
                    'props' => [
                        'vwo_fs_environment' => $sdkKey,
                    ],
                ],
            ],
        ];
        if (!empty($visitorUserAgent) && $visitorUserAgent !== null) {
            $properties['d']['visitor_ua'] = $visitorUserAgent;
        }

        if (!empty($ipAddress) && $ipAddress !== null) {
            $properties['d']['visitor_ip'] = $ipAddress;
        }

        return $properties;
    }

  public function getTrackUserPayloadData($settings, $userId, $eventName, $campaignId, $variationId, $visitorUserAgent = '', $ipAddress = '' ) {
        $properties = $this->getEventBasePayload($settings, $userId, $eventName, $visitorUserAgent, $ipAddress);

        $properties['d']['event']['props']['id'] = $campaignId;
        $properties['d']['event']['props']['variation'] = $variationId;
        $properties['d']['event']['props']['isFirst'] = 1;

        LogManager::instance()->debug(
            "IMPRESSION_FOR_EVENT_ARCH_TRACK_USER: Impression built for vwo_variationShown event for Account ID:{$settings->getAccountId()}, User ID:{$userId}, and Campaign ID:{$campaignId}"
        );

        return $properties;
    }

  public function getTrackGoalPayloadData($settings, $userId, $eventName, $eventProperties, $visitorUserAgent = '', $ipAddress = '' ) {
        $properties = $this->getEventBasePayload($settings, $userId, $eventName, $visitorUserAgent, $ipAddress);
        $properties['d']['event']['props']['isCustomEvent'] = true;
        $properties['d']['event']['props']['variation'] = 1;  // temporary value
        $properties['d']['event']['props']['id'] = 1;         // temporary value

        if ($eventProperties && DataTypeUtil::isObject($eventProperties) && count($eventProperties) > 0) {
            foreach ($eventProperties as $prop => $value) {
                $properties['d']['event']['props'][$prop] = $value;
            }
        }

        LogManager::instance()->debug(
            "IMPRESSION_FOR_EVENT_ARCH_TRACK_GOAL: Impression built for {$eventName} event for Account ID:{$settings->getAccountId()}, User ID:{$userId}"
        );

        return $properties;
    }

  public function getAttributePayloadData($settings, $userId, $eventName, $attributes, $visitorUserAgent = '', $ipAddress = '') {
        $properties = $this->getEventBasePayload($settings, $userId, $eventName, $visitorUserAgent, $ipAddress);
        $properties['d']['event']['props']['isCustomEvent'] = true;
        $properties['d']['event']['props'][Constants::VWO_FS_ENVIRONMENT] = $settings->getSdkKey();
        // Iterate over the attributes map and append to the visitor properties
        foreach ($attributes as $key => $value) {
            $properties['d']['visitor']['props'][$key] = $value;
        }
    
        LogManager::instance()->debug(
            "IMPRESSION_FOR_SYNC_VISITOR_PROP: Impression built for {$eventName} event for Account ID: {$settings->getAccountId()}, User ID: {$userId}"
        );

        return $properties;
    }

  public function sendPostApiRequest($properties, $payload) {
        NetworkManager::Instance()->attachClient();

        $headers = [];

        $userAgent = isset($payload['d']['visitor_ua']) ? $payload['d']['visitor_ua'] : null;
        $ipAddress = isset($payload['d']['visitor_ip']) ? $payload['d']['visitor_ip'] : null;

        // Set headers
        if ($userAgent) {
            $headers[HeadersEnum::USER_AGENT] = $userAgent;
        }
        if ($ipAddress) {
            $headers[HeadersEnum::IP] = $ipAddress;
        }

        $request = new RequestModel(
            UrlService::getBaseUrl(),
            'POST',
            UrlEnum::EVENTS,
            $properties,
            $payload,
            $headers,
            SettingsService::instance()->protocol,
            SettingsService::instance()->port
        );

        try {
            $response = NetworkManager::Instance()->post($request);
            return $response;
        } catch (Exception $err) {
            $errorMessage = $err instanceof \Exception ? $err->getMessage() : 'Unknown error';
            LogManager::instance()->error("Error occurred while sending POST request $errorMessage");
        }
    }


  public function sendGetApiRequest($properties, $endpoint) {
        NetworkManager::Instance()->attachClient();
        $request = new RequestModel(
            UrlService::getBaseUrl(),
            'Get',
            $endpoint,
            $properties,
            null,
            null,
            SettingsService::instance()->protocol,
            SettingsService::instance()->port,
        );
        try {
            $response = NetworkManager::Instance()->get($request);
            return $response; // Return the response model
        } catch (Exception $err) {
            $errorMessage = $err instanceof \Exception ? $err->getMessage() : 'Unknown error';
            LogManager::instance()->error("Error occurred while sending GET request $errorMessage ");
            return null;
        }
    }
}
?>
