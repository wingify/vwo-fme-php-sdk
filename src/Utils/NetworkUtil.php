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
use vwo\Utils\UsageStatsUtil;
use vwo\Enums\EventEnum;
use vwo\Services\LoggerService;

class NetworkUtil {
  
  /**
   * Gets base properties for bulk operations.
   *
   * @param string $accountId The account identifier
   * @param string $userId The user identifier
   * @return array Array containing session ID and user UUID
   */
  public function getBasePropertiesForBulk($accountId, $userId) {
        $path = [
            'sId' => FunctionUtil::getCurrentUnixTimestamp(),
            'u' => UuidUtil::getUUID($userId, $accountId),
        ];
        return $path;
    }

  /**
   * Constructs the path parameters for settings API requests.
   *
   * @param string $apikey The API key for authentication
   * @param string $accountId The account identifier
   * @return array Array containing API key, random number, and account ID
   */
  public function getSettingsPath($apikey, $accountId) {
        $path = [
            'i' => $apikey,
            'r' => mt_rand(),
            'a' => $accountId,
        ];
        return $path;
    }

    /**
     * Constructs the path parameters for track event API requests.
     *
     * @param string $event The event type
     * @param string $accountId The account identifier
     * @param string $userId The user identifier
     * @return array Array containing event tracking parameters
     */
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

    /**
     * Gets query parameters for event batching API requests.
     *
     * @param string $accountId The account identifier
     * @return array Array containing account ID, SDK name, and SDK version
     */
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
            'env' => SettingsService::instance()->sdkKey
        ];

        return $path;
    }

  /**
   * Constructs base properties for event tracking.
   *
   * @param string $eventName The name of the event
   * @param string $visitorUserAgent The user agent string (optional)
   * @param string $ipAddress The IP address of the visitor (optional)
   * @param bool $isUsageStatsEvent Whether this is a usage stats event (optional)
   * @param int|null $usageStatsAccountId The account ID for usage statistics (optional)
   * @return array Array containing event properties with URL
   */
  public function getEventsBaseProperties($eventName, $visitorUserAgent = '', $ipAddress = '', $isUsageStatsEvent = false, $usageStatsAccountId = null) {
        $sdkKey = SettingsService::instance()->sdkKey;
        $accountId = SettingsService::instance()->accountId;

        $properties = [
            'en' => $eventName,
            'a' => $accountId,
            'eTime' => FunctionUtil::getCurrentUnixTimestampInMillis(),
            'random' => FunctionUtil::getRandomNumber(),
            'p' => 'FS',
            'sn'=> Constants::SDK_NAME,
            'sv'=> ComposerUtil::getSdkVersion()
        ];
        
        if (!empty($visitorUserAgent) && $visitorUserAgent !== null) {
            $properties['visitor_ua'] = $visitorUserAgent;
        }

        if (!empty($ipAddress) && $ipAddress !== null) {
            $properties['visitor_ip'] = $ipAddress;
        }
        if(!$isUsageStatsEvent){
            $properties['env'] = $sdkKey;
        } else {
            $properties['a'] = $usageStatsAccountId;
        }

        $properties['url'] = Constants::HTTPS_PROTOCOL . UrlService::getBaseUrl() . UrlEnum::EVENTS;
        return $properties;
    }

  /**
   * Constructs the base payload structure for events.
   *
   * @param mixed $settings The settings object (can be null)
   * @param string $userId The user identifier
   * @param string $eventName The name of the event
   * @param string $visitorUserAgent The user agent string (optional)
   * @param string $ipAddress The IP address of the visitor (optional)
   * @param bool $isUsageStatsEvent Whether this is a usage stats event (optional)
   * @param int|null $usageStatsAccountId The account ID for usage statistics (optional)
   * @return array Array containing the base event payload structure
   */
  public function getEventBasePayload($settings, $userId, $eventName, $visitorUserAgent = '', $ipAddress = '', $isUsageStatsEvent = false, $usageStatsAccountId = null) {
        $accountId = $isUsageStatsEvent ? $usageStatsAccountId : SettingsService::instance()->accountId;
        $uuid = UuidUtil::getUUID($userId, $accountId);
  

        try {
            $sdkVersion = ComposerUtil::getSdkVersion();
        } catch (Exception $e) {
            LogManager::instance()->error($e->getMessage());
            $sdkVersion = Constants::SDK_VERSION; // Use the constant as a fallback
        }

        $props = [
            'vwo_sdkName' => Constants::SDK_NAME,
            'vwo_sdkVersion' => $sdkVersion,
        ];

        if(!$isUsageStatsEvent){
            // set env key for standard sdk events
            $props['vwo_envKey'] = SettingsService::instance()->sdkKey;
        }

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
            ],
        ];
        
        if (!$isUsageStatsEvent) {
            // set visitor props for standard sdk events
            $properties['d']['visitor'] = [
                'props' => [
                    'vwo_fs_environment' => SettingsService::instance()->sdkKey,
                ],
            ];
        }

        if (!empty($visitorUserAgent) && $visitorUserAgent !== null) {
            $properties['d']['visitor_ua'] = $visitorUserAgent;
        }

        if (!empty($ipAddress) && $ipAddress !== null) {
            $properties['d']['visitor_ip'] = $ipAddress;
        }

        return $properties;
    }

  /**
   * Constructs payload data for tracking user impressions.
   *
   * @param mixed $settings The settings object
   * @param string $eventName The name of the event
   * @param string $campaignId The campaign identifier
   * @param string $variationId The variation identifier
   * @param ContextModel $context The context object
   * @return array Array containing the track user payload data
   */
  public function getTrackUserPayloadData($settings, $eventName, $campaignId, $variationId, $context ) {
        $userId = $context->getId();
        $visitorUserAgent = $context->getUserAgent();
        $ipAddress = $context->getIpAddress();
        $postSegmentationVariables = $context->getPostSegmentationVariables();
        $customVariables = $context->getCustomVariables();

        $properties = $this->getEventBasePayload($settings, $userId, $eventName, $visitorUserAgent, $ipAddress);

        $properties['d']['event']['props']['id'] = $campaignId;
        $properties['d']['event']['props']['variation'] = $variationId;
        $properties['d']['event']['props']['isFirst'] = 1;

        if (is_array($postSegmentationVariables) && is_array($customVariables)) {
            foreach ($postSegmentationVariables as $key) {
                if (array_key_exists($key, $customVariables)) {
                    $properties['d']['visitor']['props'][$key] = $customVariables[$key];
                }
            }
        }

        // Add IP address as a standard attribute if available
        if (!empty($ipAddress) && $ipAddress !== null) {
            $properties['d']['visitor']['props']['ip'] = $ipAddress;
        }
        
        // if userAgent is passed, add os_version and browser_version
        if (!empty($visitorUserAgent) && $visitorUserAgent !== null) {
            if (!empty($context->getVwo()) && !empty($context->getVwo()->getUaInfo())) {
                $uaInfo = $context->getVwo()->getUaInfo();
                $properties['d']['visitor']['props']['vwo_osv'] = $uaInfo->os_version;
                $properties['d']['visitor']['props']['vwo_bv'] = $uaInfo->browser_version;
            }
            else {
                LogManager::instance()->error('To pass user agent related details as standard attributes, please set gateway as well in init method');
            }
        }
        
        
        LogManager::instance()->debug(
            "IMPRESSION_FOR_TRACK_USER: Impression built for vwo_variationShown event for Account ID:{$settings->getAccountId()}, User ID:{$userId}, and Campaign ID:{$campaignId}"
        );

        return $properties;
    }

  /**
   * Constructs payload data for tracking goal conversions.
   *
   * @param mixed $settings The settings object
   * @param string $userId The user identifier
   * @param string $eventName The name of the event
   * @param array $eventProperties Additional event properties (optional)
   * @param string $visitorUserAgent The user agent string (optional)
   * @param string $ipAddress The IP address of the visitor (optional)
   * @return array Array containing the track goal payload data
   */
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
            "IMPRESSION_FOR_TRACK_GOAL: Impression built for {$eventName} event for Account ID:{$settings->getAccountId()}, User ID:{$userId}"
        );

        return $properties;
    }

  /**
   * Constructs payload data for setting user attributes.
   *
   * @param mixed $settings The settings object
   * @param string $userId The user identifier
   * @param string $eventName The name of the event
   * @param array $attributes The attributes to set for the user
   * @param string $visitorUserAgent The user agent string (optional)
   * @param string $ipAddress The IP address of the visitor (optional)
   * @return array Array containing the attribute payload data
   */
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

  /**
   * Sends a POST API request with the given properties and payload.
   *
   * @param array $properties The query parameters for the request
   * @param array $payload The payload data to send
   * @return mixed The response from the API call or null on failure
   */
  public function sendPostApiRequest($properties, $payload) {

        $retryConfig = NetworkManager::Instance()->getRetryConfig();
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
            SettingsService::instance()->port,
            $retryConfig
        );

        try {
            $response = NetworkManager::Instance()->post($request);
            return $response;
        } catch (Exception $err) {
            $errorMessage = $err instanceof \Exception ? $err->getMessage() : 'Unknown error';
            LogManager::instance()->error("Error occurred while sending POST request $errorMessage");
        }
    }

  /**
   * Sends a GET API request with the given properties and endpoint.
   *
   * @param array $properties The query parameters for the request
   * @param string $endpoint The API endpoint to call
   * @return mixed The response from the API call or null on failure
   */
  public function sendGetApiRequest($properties, $endpoint) {
        $request = new RequestModel(
            UrlService::getBaseUrl(),
            'Get',
            $endpoint,
            $properties,
            null,
            null,
            SettingsService::instance()->protocol,
            SettingsService::instance()->port
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

  /**
   * Constructs payload data for messaging events.
   *
   * @param string $messageType The type of message
   * @param string $message The message content
   * @param string $eventName The name of the event
   * @return array Array containing the messaging event payload
   */
  public function getMessagingEventPayload($messageType, $message, $eventName) {
        $userId = SettingsService::instance()->accountId . '_' . SettingsService::instance()->sdkKey;
        $properties = $this->getEventBasePayload(null, $userId, $eventName, null, null);
    
        // Set environment key
        $properties['d']['event']['props'][Constants::VWO_FS_ENVIRONMENT] = SettingsService::instance()->sdkKey;
        $properties['d']['event']['props']['product'] = 'fme';
    
        $data = [
            'type' => $messageType,
            'content' => [
                'title' => $message,
                'dateTime' => FunctionUtil::getCurrentUnixTimestampInMillis(),
            ],
        ];
    
        $properties['d']['event']['props']['data'] = $data;
    
        return $properties;
    }    

    /**
     * Sends an event to the VWO server.
     *
     * @param array $properties The properties of the event
     * @param array $payload The payload of the event
     * @param string $eventName The name of the event
     * @return array|false The response data if successful, false otherwise
     */
    public function sendEvent($properties, $payload, $eventName) {
        $retryConfig = NetworkManager::Instance()->getRetryConfig();

        if($eventName == EventEnum::VWO_ERROR){
            $retryConfig['shouldRetry'] = false;
        }
        
        if($eventName == EventEnum::VWO_ERROR || $eventName == EventEnum::VWO_USAGE_STATS_EVENT) {
            $baseUrl = Constants::HOST_NAME;
            $protocol = Constants::HTTPS_PROTOCOL;
            $port = null;
        } else {
            $baseUrl = UrlService::getBaseUrl();
            $protocol = SettingsService::instance()->protocol ?? Constants::HTTPS_PROTOCOL;
            $port = SettingsService::instance()->port ?? null;
        }
        
        try {
            $request = new RequestModel(
                $baseUrl,
                'POST',
                UrlEnum::EVENTS,
                $properties,
                $payload,
                null,
                $protocol,
                $port,
                $retryConfig
            );
    
            // Perform the network POST request synchronously
            $response = NetworkManager::Instance()->post($request);
            return $response;
        } catch (Exception $e) {
            LoggerService::error('NETWORK_CALL_FAILED', ['method' => 'POST', 'error' => $e->getMessage()]);
            return false;
        }
    }    
    
    /**
     * Constructs the payload for SDK init called event.
     *
     * @param string $eventName The name of the event
     * @param int|null $settingsFetchTime Time taken to fetch settings in milliseconds
     * @param int|null $sdkInitTime Time taken to initialize the SDK in milliseconds
     * @return array The constructed payload with required fields
     */
    public function getSdkInitEventPayload($eventName, $settingsFetchTime = null, $sdkInitTime = null)
    {
        $userId = SettingsService::instance()->accountId . '_' . SettingsService::instance()->sdkKey;
        $properties = $this->getEventBasePayload(null, $userId, $eventName, null, null);

        // Set the required fields as specified
        $properties['d']['event']['props'][Constants::VWO_FS_ENVIRONMENT] = SettingsService::instance()->sdkKey;
        $properties['d']['event']['props'][Constants::PRODUCT] = Constants::FME;
        

        $data = [
            'isSDKInitialized' => true,
            'settingsFetchTime' => $settingsFetchTime,
            'sdkInitTime' => $sdkInitTime,
        ];
        $properties['d']['event']['props']['data'] = $data;

        return $properties;
    }

    /**
     * Constructs the payload for SDK usage stats event.
     *
     * @param string $eventName The name of the event.
     * @param int $usageStatsAccountId The account ID for usage statistics.
     * @return array The constructed payload with required fields.
     */
    public function getSDKUsageStatsEventPayload($eventName, $usageStatsAccountId)
    {
        // Build userId as accountId_sdkKey (not usageStatsAccountId_sdkKey)
        $userId = SettingsService::instance()->accountId . '_' . SettingsService::instance()->sdkKey;

        // Pass $usageStatsAccountId as the last argument (6th) to getEventBasePayload, with $isUsageStatsEvent = true
        $properties = $this->getEventBasePayload(
            null,
            $userId,
            $eventName,
            null,
            null,
            true,
            $usageStatsAccountId
        );

        // Set the required fields as specified
        $properties['d']['event']['props'][Constants::PRODUCT] = Constants::FME;
        $properties['d']['event']['props']['vwoMeta'] = UsageStatsUtil::getInstance()->getUsageStats();

        return $properties;
    }
}
?>