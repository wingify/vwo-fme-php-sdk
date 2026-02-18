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
use vwo\Services\ServiceContainer;
use vwo\Enums\DebuggerCategoryEnum;
use vwo\Packages\Logger\Enums\LogLevelEnum;
use vwo\Utils\LogMessageUtil;
use vwo\Enums\ApiEnum;
use vwo\Packages\NetworkLayer\Models\ResponseModel;
use vwo\Utils\DebuggerServiceUtil;
use vwo\Enums\CampaignTypeEnum;

class NetworkUtil {
  private $serviceContainer;

  public function __construct(ServiceContainer $serviceContainer = null)
  {
    $this->serviceContainer = $serviceContainer;
  }
  
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
            $sdkVersion = Constants::SDK_VERSION; // Use the constant as a fallback
        }
        $settingsService = $this->serviceContainer->getSettingsService();
        $sdkKey = $settingsService->sdkKey;
        $path = [
            'a' => $accountId,
            'sd' => Constants::SDK_NAME,
            'sv' => $sdkVersion,
            'env' => $sdkKey
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
        $settingsService = $this->serviceContainer ? $this->serviceContainer->getSettingsService() : SettingsService::instance();
        $sdkKey = $settingsService->sdkKey;
        $accountId = $settingsService->accountId;

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

        $properties['url'] = Constants::HTTPS_PROTOCOL . $settingsService->hostname . UrlService::getEndpointWithCollectionPrefix(UrlEnum::EVENTS);
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
  public function getEventBasePayload($settings, $userId, $sessionId, $eventName, $visitorUserAgent = '', $ipAddress = '', $isUsageStatsEvent = false, $usageStatsAccountId = null, $shouldGenerateUuid = true) {
        $settingsService = $this->serviceContainer ? $this->serviceContainer->getSettingsService() : SettingsService::instance();
        $accountId = $isUsageStatsEvent ? $usageStatsAccountId : $settingsService->accountId;
        $uuid = $shouldGenerateUuid ? UuidUtil::getUUID($userId, $accountId) : $userId;
  

        try {
            $sdkVersion = ComposerUtil::getSdkVersion();
        } catch (Exception $e) {
            $sdkVersion = Constants::SDK_VERSION; // Use the constant as a fallback
        }

        $props = [
            'vwo_sdkName' => Constants::SDK_NAME,
            'vwo_sdkVersion' => $sdkVersion,
        ];

        if(!$isUsageStatsEvent){
            // set env key for standard sdk events
            $props['vwo_envKey'] = $settingsService->sdkKey;
        }
        $sessionId = $sessionId !== null ? $sessionId : FunctionUtil::getCurrentUnixTimestamp();

        $properties = [
            'd' => [
                'msgId' => "{$uuid}-" . FunctionUtil::getCurrentUnixTimestampInMillis(),
                'visId' => $uuid,
                'sessionId' => $sessionId,
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
                    'vwo_fs_environment' => $settingsService->sdkKey,
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
    public function getTrackUserPayloadData($settings, $eventName, $campaignId, $variationId, $context, $sessionId = 0 ) {
        $userId = $context->getId();
        $visitorUserAgent = $context->getUserAgent();
        $ipAddress = $context->getIpAddress();
        $postSegmentationVariables = $context->getPostSegmentationVariables();
        $customVariables = $context->getCustomVariables();

        $properties = $this->getEventBasePayload($settings, $userId, $context->getSessionId(), $eventName, $visitorUserAgent, $ipAddress, false, null);

        if($sessionId != 0) {
            $properties['d']['sessionId'] = $sessionId;
        }
        $properties = $this->getEventBasePayload($settings, $userId, $context->getSessionId(), $eventName, $visitorUserAgent, $ipAddress);

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
                $this->serviceContainer->getLoggerService()->error('INVALID_USER_AGENT_FOR_STANDARD_ATTRIBUTES', [
                    'an' => ApiEnum::GET_FLAG,
                    'uuid' => $context->getId(),
                    'sId' => $context->getSessionId()
                ]);
            }
        }
        
        
        $this->serviceContainer->getLogManager()->debug(
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
  public function getTrackGoalPayloadData($settings, $context, $eventName, $eventProperties) {
        $properties = $this->getEventBasePayload($settings, $context->getId(), $context->getSessionId(), $eventName, $context->getUserAgent(), $context->getIpAddress(), false, null);
        $properties['d']['event']['props']['isCustomEvent'] = true;
        $properties['d']['event']['props']['variation'] = 1;  // temporary value
        $properties['d']['event']['props']['id'] = 1;         // temporary value

        if($context->getSessionId() != 0) {
            $properties['d']['sessionId'] = $context->getSessionId();
        }

        if ($eventProperties && DataTypeUtil::isObject($eventProperties) && count($eventProperties) > 0) {
            foreach ($eventProperties as $prop => $value) {
                $properties['d']['event']['props'][$prop] = $value;
            }
        }

        $this->serviceContainer->getLogManager()->debug(
            "IMPRESSION_FOR_TRACK_GOAL: Impression built for {$eventName} event for Account ID:{$settings->getAccountId()}, User ID:{$context->getId()}"
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
    public function getAttributePayloadData($settings, $context, $eventName, $attributes) {
        $properties = $this->getEventBasePayload($settings, $context->getId(), $context->getSessionId(), $eventName, $context->getUserAgent(), $context->getIpAddress(), false, null);
  
        $properties['d']['event']['props']['isCustomEvent'] = true;
        $properties['d']['event']['props'][Constants::VWO_FS_ENVIRONMENT] = $settings->getSdkKey();
        
        if($context->getSessionId() != 0) {
            $properties['d']['sessionId'] = $context->getSessionId();
        }
        // Iterate over the attributes map and append to the visitor properties
        foreach ($attributes as $key => $value) {
            $properties['d']['visitor']['props'][$key] = $value;
        }
    
        $this->serviceContainer->getLogManager()->debug(
            "IMPRESSION_FOR_SYNC_VISITOR_PROP: Impression built for {$eventName} event for Account ID: {$settings->getAccountId()}, User ID: {$context->getId()}"
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
  public function sendPostApiRequest($properties, $payload, $userId, $eventProperties = [], $campaignInfo = []) {

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

        $settingsService = $this->serviceContainer ? $this->serviceContainer->getSettingsService() : SettingsService::instance();
        $networkManager = $this->serviceContainer ? $this->serviceContainer->getNetworkManager() : NetworkManager::instance();
        $logManager = $this->serviceContainer->getLogManager();

        $request = new RequestModel(
            $settingsService->hostname,
            'POST',
            UrlService::getEndpointWithCollectionPrefix(UrlEnum::EVENTS),
            $properties,
            $payload,
            $headers,
            $settingsService->protocol,
            $settingsService->port,
            $retryConfig
        );

        $request->setEventName($properties['en']);
        $request->setUuid($payload['d']['visId']);

        $apiName = null;
        $extraDataForMessage = null;

        if ($properties['en'] === EventEnum::VWO_VARIATION_SHOWN) {
            $apiName = ApiEnum::GET_FLAG;

            if (
                isset($campaignInfo['campaignType']) &&
                (
                    $campaignInfo['campaignType'] === CampaignTypeEnum::ROLLOUT ||
                    $campaignInfo['campaignType'] === CampaignTypeEnum::PERSONALIZE
                )
            ) {
                $extraDataForMessage =
                    'feature: ' . ($campaignInfo['featureKey'] ?? null) .
                    ', rule: ' . ($campaignInfo['variationName'] ?? null);
            } else {
                $extraDataForMessage =
                    'feature: ' . ($campaignInfo['featureKey'] ?? null) .
                    ', rule: ' . ($campaignInfo['campaignKey'] ?? null) .
                    ' and variation: ' . ($campaignInfo['variationName'] ?? null);
            }

            // Set campaignId if present in payload
            if (
                isset($payload['d']['event']['props']['id']) &&
                is_numeric($payload['d']['event']['props']['id'])
            ) {
                $request->setCampaignId((string) $payload['d']['event']['props']['id']);
            }

        } else if ($properties['en'] === EventEnum::VWO_SYNC_VISITOR_PROP) {

            $apiName = ApiEnum::SET_ATTRIBUTE;
            $extraDataForMessage = $apiName;

        } else if (
            $properties['en'] !== EventEnum::VWO_DEBUGGER_EVENT &&
            $properties['en'] !== EventEnum::VWO_SDK_INIT_EVENT
        ) {

            $apiName = ApiEnum::TRACK_EVENT;
            $extraDataForMessage = 'event: ' . ($properties['en'] ?? '');

            if (!empty($eventProperties)) {
                $request->setEventProperties($eventProperties);
            }
        }

        try {
            // Ensure NetworkManager has client attached if using singleton fallback
            if (!$this->serviceContainer && $networkManager) {
                $networkOptions = [
                    'isGatewayUrlNotSecure' => false,
                    'isProxyUrlNotSecure' => false
                ];
                $networkManager->attachClient(null, $networkOptions);
            }
            
            $response = $networkManager->post($request);
            
            if ($response->getStatusCode() !== 0 && $response->getTotalAttempts() > 0) {
                $debugEventProps = NetworkUtil::createNetworkAndRetryDebugEvent($response, $payload, $apiName, $extraDataForMessage);
                $debugEventProps["uuid"] = $request->getUuid();

                DebuggerServiceUtil::sendDebugEventToVWO($debugEventProps);
                $this->serviceContainer->getLoggerService()->info("NETWORK_CALL_SUCCESS_WITH_RETRIES", [
                    "extraData" => "POST " . UrlService::getEndpointWithCollectionPrefix(UrlEnum::EVENTS),
                    "attempts" => $response->getTotalAttempts(),
                    "err" => $response->getError()
                ]);
            }
            
            // Fire-and-forget (socket-based) case:
            //No HTTP response was received (statusCode is null) because we didn't wait for one.
            //In this case, treat the call as a success, not a failure.
            //If the response is null or the status code is null, then the call was successful.
            $accountId = $this->serviceContainer->getSettingsService()->accountId;
            $uuid = $request->getUuid();
            if($response->getStatusCode() == 0 && $response->getTotalAttempts() == 0) {
                $this->serviceContainer->getLoggerService()->info("NETWORK_CALL_SUCCESS", [
                    'event' => $properties['en'],
                    'endPoint' => UrlService::getEndpointWithCollectionPrefix(UrlEnum::EVENTS),
                    'accountId' => $accountId,
                    'userId' => $userId,
                    'uuid' => $uuid
                ]);
            }

            // Log error if status code is not 2xx (regardless of retries)
            if(($response->getStatusCode() < Constants::HTTP_SUCCESS_MIN || $response->getStatusCode() > Constants::HTTP_SUCCESS_MAX) && $response->getTotalAttempts() > 0) {
                $responseModel = new ResponseModel();
                $responseModel->setError(new Exception("Network request failed: response is null"));
                $responseModel->setStatusCode($response->getStatusCode());
                
                $debugEventProps = NetworkUtil::createNetworkAndRetryDebugEvent($responseModel, $payload, $apiName, $extraDataForMessage);
                $debugEventProps["uuid"] = $request->getUuid();

                DebuggerServiceUtil::sendDebugEventToVWO($debugEventProps);

                $this->serviceContainer->getLoggerService()->error("NETWORK_CALL_FAILED", [
                    'method' => 'POST',
                    'err' => $response->getError()
                ], false);
            }
        } catch (Exception $err) {
            $this->serviceContainer->getLoggerService()->error("NETWORK_CALL_FAILED", [
                'method' => 'POST',
                'err' => $err->getMessage()
            ], false);
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
        $settingsService = $this->serviceContainer ? $this->serviceContainer->getSettingsService() : SettingsService::instance();
        $networkManager = $this->serviceContainer ? $this->serviceContainer->getNetworkManager() : NetworkManager::instance();
        $loggerService = $this->serviceContainer->getLoggerService();
        
        $request = new RequestModel(
            $settingsService->hostname,
            'Get',
            UrlService::getEndpointWithCollectionPrefix($endpoint),
            $properties,
            null,
            null,
            $settingsService->protocol,
            $settingsService->port
        );
        try {
            // Ensure NetworkManager has client attached if using singleton fallback
            if (!$this->serviceContainer && $networkManager) {
                $networkOptions = [
                    'isGatewayUrlNotSecure' => false,
                    'isProxyUrlNotSecure' => false
                ];
                // Ensure singleton has client attached
                $networkManager->attachClient(null, $networkOptions);
            }
            
            $response = $networkManager->get($request);
            return $response; // Return the response model
        } catch (Exception $err) {
            $errorMessage = $err instanceof \Exception ? $err->getMessage() : 'Unknown error';
            $loggerService->error("Error occurred while sending GET request: $errorMessage");
            return null;
        }
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
        $settingsService = $this->serviceContainer ? $this->serviceContainer->getSettingsService() : SettingsService::instance();
        if($eventName == EventEnum::VWO_ERROR || $eventName == EventEnum::VWO_USAGE_STATS_EVENT || $eventName == EventEnum::VWO_DEBUGGER_EVENT) {
            $baseUrl = Constants::HOST_NAME;
            $protocol = Constants::HTTPS_PROTOCOL;
            $port = null;
        } else {
            $baseUrl = $settingsService->hostname;
            $settingsService = $this->serviceContainer ? $this->serviceContainer->getSettingsService() : SettingsService::instance();
            $protocol = $settingsService->protocol ?? Constants::HTTPS_PROTOCOL;
            $port = $settingsService->port ?? null;
        }
        try {
            $request = new RequestModel(
                $baseUrl,
                'POST',
                UrlService::getEndpointWithCollectionPrefix(UrlEnum::EVENTS),
                $properties,
                $payload,
                null,
                $protocol,
                $port,
                $retryConfig
            );
    
            // Perform the network POST request synchronously
            $networkManager = $this->serviceContainer ? $this->serviceContainer->getNetworkManager() : NetworkManager::instance();
            // Ensure NetworkManager has client attached if using singleton fallback
            if (!$this->serviceContainer && $networkManager) {
                $networkOptions = [
                    'isGatewayUrlNotSecure' => false,
                    'isProxyUrlNotSecure' => false
                ];
                // Ensure singleton has client attached
                $networkManager->attachClient(null, $networkOptions);
            }
            $response = $networkManager->post($request);
            return $response;
        } catch (Exception $e) {
            $this->serviceContainer->getLoggerService()->error('NETWORK_CALL_FAILED', [
                'method' => 'POST', 'error' => $e->getMessage()
            ]);
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
        $settingsService = $this->serviceContainer ? $this->serviceContainer->getSettingsService() : SettingsService::instance();
        $userId = $settingsService->accountId . '_' . $settingsService->sdkKey;
        $properties = $this->getEventBasePayload(null, $userId, FunctionUtil::getCurrentUnixTimestamp(), $eventName, null, null);

        // Set the required fields as specified
        $properties['d']['event']['props'][Constants::VWO_FS_ENVIRONMENT] = $settingsService->sdkKey;
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
        $settingsService = $this->serviceContainer ? $this->serviceContainer->getSettingsService() : SettingsService::instance();
        $userId = $settingsService->accountId . '_' . $settingsService->sdkKey;

        // Pass $usageStatsAccountId as the last argument (6th) to getEventBasePayload, with $isUsageStatsEvent = true
        $properties = $this->getEventBasePayload(
            null,
            $userId,
            FunctionUtil::getCurrentUnixTimestamp(),
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

        /**
     * Creates network and retry debug event properties
     *
     * @param ResponseModel $response The response model
     * @param array $payload The payload data
     * @param string $apiName The API name
     * @param string $extraData Extra data for the message
     * @return array Debug event properties
     */
    public static function createNetworkAndRetryDebugEvent($response, $payload, $apiName, $extraData) {
        try {
            // Set category, if call got success then category is retry, otherwise network
            $category = DebuggerCategoryEnum::RETRY;
            $msg_t = Constants::NETWORK_CALL_SUCCESS_WITH_RETRIES;
            $lt = LogLevelEnum::INFO;

            $msgTemplate = LoggerService::$infoMessages['NETWORK_CALL_SUCCESS_WITH_RETRIES'] ?? 'NETWORK_CALL_SUCCESS_WITH_RETRIES';

            if ($response->getStatusCode() !== Constants::HTTP_SUCCESS_MIN) {
                $category = DebuggerCategoryEnum::NETWORK;
                $msg_t = Constants::NETWORK_CALL_FAILURE_AFTER_MAX_RETRIES;

                $msgTemplate = LoggerService::$errorMessages['NETWORK_CALL_FAILURE_AFTER_MAX_RETRIES'] ?? 'NETWORK_CALL_FAILURE_AFTER_MAX_RETRIES';
                $lt = LogLevelEnum::ERROR;
            }

            $msg = LogMessageUtil::buildMessage(
                $msgTemplate,
                [
                    'extraData' => $extraData,
                    'attempts' => method_exists($response, 'getTotalAttempts') ? $response->getTotalAttempts() : -1,
                    'err' => $response->getError()->getMessage()
                ]
            );

            $debugEventProps = [
                'cg' => $category,
                'msg_t' => $msg_t,
                'msg' => $msg,
                'lt' => $lt
            ];

            if ($apiName) {
                $debugEventProps['an'] = $apiName;
            }

            // Extract sessionId from payload.d.sessionId
            if (isset($payload['d']['sessionId'])) {
                $debugEventProps['sId'] = $payload['d']['sessionId'];
            } else {
                $debugEventProps['sId'] = FunctionUtil::getCurrentUnixTimestamp();
            }

            return $debugEventProps;

        } catch (Exception $err) {
            return [
                'cg' => DebuggerCategoryEnum::NETWORK,
                'an' => $apiName,
                'msg_t' => 'NETWORK_CALL_FAILED',
                'msg' => LogMessageUtil::buildMessage(
                    LoggerService::$errorMessages['NETWORK_CALL_FAILED'] ?? 'NETWORK_CALL_FAILED',
                    [
                        'method' => $extraData,
                        'err' => $err->getMessage()
                    ]
                ),
                'lt' => LogLevelEnum::ERROR,
                'sId' => FunctionUtil::getCurrentUnixTimestamp()
            ];
        }
    }

    /**
     * Constructs the payload for debugger event.
     *
     * @param array $eventProps The properties for the event
     * @return array The constructed payload
     */
    public static function getDebuggerEventPayload($eventProps = []) {
        $uuid = '';
        $accountId = SettingsService::instance()->accountId;
        $sdkKey = SettingsService::instance()->sdkKey;
        
        if (!isset($eventProps['uuid'])) {
            $uuid = UuidUtil::getUUID($accountId . '_' . $sdkKey, $accountId);
            $eventProps['uuid'] = $uuid;
        } else {
            $uuid = $eventProps['uuid'];
        }

        $networkUtil = new NetworkUtil();
        $properties = $networkUtil->getEventBasePayload(
            null,
            $uuid,
            FunctionUtil::getCurrentUnixTimestamp(),
            EventEnum::VWO_DEBUGGER_EVENT,
            null,
            null,
            false,
            null,
            false
        );

        $properties['d']['event']['props'] = [];
        
        // add session id to the event props if not present
        if (isset($eventProps['sId'])) {
            $properties['d']['sessionId'] = $eventProps['sId'];
        } else {
            $eventProps['sId'] = $properties['d']['sessionId'];
        }
        
        $properties['d']['event']['props']['vwoMeta'] = array_merge($eventProps, [
            'a' => $accountId,
            'product' => Constants::FME,
            'sn' => Constants::SDK_NAME,
            'sv' => Constants::SDK_VERSION,
            'eventId' => UuidUtil::getRandomUUID($sdkKey),
        ]);

        return $properties;
    }

}

?>