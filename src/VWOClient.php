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

namespace vwo;

use vwo\Utils\GetFlagResultUtil;
use vwo\Services\UrlService;
use vwo\Utils\CampaignUtil;
use vwo\Packages\Storage\Storage;
use vwo\Utils\FunctionUtil;
use vwo\Services\HooksService;
use vwo\Utils\DataTypeUtil;
use vwo\Api\TrackEvent;
use vwo\Api\GetFlag;
use vwo\Api\SetAttribute;
use vwo\Models\SettingsModel;
use vwo\Models\User\ContextModel;
use vwo\Utils\SettingsUtil;
use vwo\Utils\UserIdUtil;
use vwo\Services\SettingsService;
use vwo\Utils\AliasingUtil;
use vwo\Enums\ApiEnum;
use vwo\Services\ServiceContainer;
use vwo\Utils\UuidUtil;

interface IVWOClient {
    public function getFlag(string $featureKey, $context);

    public function trackEvent(string $eventName, $context, $eventProperties);
    public function setAttribute($attributesOrAttributeValue, $attributeValueOrContext, $context);
    public function setAlias($contextOrUserId, $aliasId);

}

class VWOClient implements IVWOClient {
    public $settings;
    private $storage;
    private $options;
    private $isAliasingEnabled;
    private $serviceContainer;

    public function __construct(SettingsModel $settings, array $options, ServiceContainer $serviceContainer = null) {
        $this->options = $options;
        $this->settings = $settings;
        $this->serviceContainer = $serviceContainer;
        $this->storage = $serviceContainer ? $serviceContainer->getStorage() : new Storage();
        $this->initialize($options, $settings);
        $this->isAliasingEnabled = $options['isAliasingEnabled'] ?? false;
    }

    private function initialize(array $options, $settings) {
        $collectionPrefix = $this->settings->getCollectionPrefix();
        $gatewayServiceUrl = $options['gatewayService']['url'] ?? null;
        $proxyUrl = $options['proxy']['url'] ?? null;
        
        $logManager = $this->serviceContainer->getLogManager();
        UrlService::init(compact('collectionPrefix', 'gatewayServiceUrl', 'proxyUrl'));

        foreach ($this->settings->getCampaigns() as $campaign) {
            CampaignUtil::setVariationAllocation($campaign, $logManager);
        }
        SettingsUtil::setSettingsAndAddCampaignsToRules($settings, $this, $this->serviceContainer->getLogManager());
        $this->serviceContainer->setSettings($this->settings);
        $this->serviceContainer->getLogManager()->info('VWO Client initialized');
    }

    public function getFlag(string $featureKey, $context) {
        $apiName = 'getFlag';
        $sessionId = $context['sessionId'] ?? FunctionUtil::getCurrentUnixTimestamp();
        $uuid = null;
        try {
            $this->serviceContainer->getLogManager()->debug("API Called: $apiName");
            $uuid = $this->getUUIDFromContext($context, $apiName);
        } catch (\Throwable $error) {
            $logManager = $this->serviceContainer->getLogManager();
            $logManager->error("API - $apiName failed to execute. Error: " . $error->getMessage());
            return new GetFlagResultUtil(
                false,
                [], // No variables
                [],
                $sessionId,
                $uuid
            );
        }

        $defaultReturnValue = new GetFlagResultUtil(
            false,
            [], // No variables
            [],
            $sessionId,
            $uuid
        );

        //check if isDebuggerUsed is set in options
        $isDebuggerUsed = isset($this->options['isDebuggerUsed']);


        try {

            $loggerService = $this->serviceContainer->getLoggerService();
            $hookManager = $this->serviceContainer ? $this->serviceContainer->getHooksService() : new HooksService($this->options);

            if (!isset($context['id']) || empty($context['id'])) {
                $loggerService->error('INVALID_CONTEXT_PASSED', ['an' => $apiName, 'apiName' => $apiName, 'context' => $context], false);
                throw new \Error('TypeError: Invalid context');
            }
            // set uuid in context 
            $context['uuid'] = $uuid;

            if (!DataTypeUtil::isString($featureKey) || $featureKey == null) {
                $loggerService->error('INVALID_PARAM', ['an' => $apiName, 'apiName' => $apiName, 'key' => 'featureKey', 'type' => gettype($featureKey), 'correctType' => 'string'], false);
                throw new \TypeError('TypeError: variableSpecifier should be a valid string');
            }

            if (!$this->settings) {
                $loggerService->error('INVALID_SETTINGS_SCHEMA', ['an' => $apiName, 'apiName' => $apiName], false);
                throw new \Error('Invalid Settings');
            }
            //Get userId using UserIdUtil if aliasing is enabled and gateway service is provided
            $userId = UserIdUtil::getUserId($context['id'], $this->isAliasingEnabled, $this->serviceContainer);
            if($this->isAliasingEnabled) {
                if($userId != $context['id']) {
                    $loggerService->info('ALIAS_ENABLED', ['userId' => $userId]);
                } else {
                     $loggerService->error('GATEWAY_URL_ERROR', ['an' => $apiName, 'apiName' => $apiName, 'context' => $context]);
                }
            }
            $context['id'] = $userId;

            $contextModel = new ContextModel();
            $contextModel->modelFromDictionary($context);

            return (new GetFlag())->get($featureKey, $contextModel, $this->serviceContainer, $isDebuggerUsed);
        } catch (\Throwable $error) {
            $loggerService->error('EXECUTION_FAILED', ['an' => $apiName, 'apiName' => $apiName, 'err' => $error->getMessage()]);
            return $defaultReturnValue;
        }
    }

    public function trackEvent(string $eventName, $context, $eventProperties = []) {
        $apiName = 'trackEvent';
        //check if isDebuggerUsed is set in options
        $isDebuggerUsed = isset($this->options['isDebuggerUsed']);

        try {
            $loggerService = $this->serviceContainer->getLoggerService();
            $hookManager = $this->serviceContainer ? $this->serviceContainer->getHooksService() : new HooksService($this->options);

            $loggerService->debug("API Called: $apiName");
            if (!isset($context['id']) || empty($context['id'])) {
                $loggerService->error('INVALID_CONTEXT_PASSED', ['an' => ApiEnum::TRACK_EVENT, 'apiName' => $apiName], false);
                throw new \Error('TypeError: Invalid context');
            }
            // set uuid in context 
            $context['uuid'] = $this->getUUIDFromContext($context, $apiName);

            if (!DataTypeUtil::isString($eventName)) {
                $loggerService->error('INVALID_PARAM', ['key' => $eventName, 'an'=> ApiEnum::TRACK_EVENT, 'apiName' => $apiName, 'type' => gettype($eventName), 'correctType' => 'string'], false);
                throw new \TypeError('TypeError: eventName should be a valid string');
            }

            if (!DataTypeUtil::isArray($eventProperties)) {
                $loggerService->error('INVALID_PARAM', ['key' => $eventProperties, 'an'=> ApiEnum::TRACK_EVENT, 'apiName' => $apiName, 'type' => gettype($eventProperties), 'correctType' => 'array'], false);
                throw new \TypeError('TypeError: eventProperties should be an array');
            }

            if (!$this->settings) {
                $loggerService->error('INVALID_SETTINGS_SCHEMA', ['an' => ApiEnum::TRACK_EVENT, 'apiName' => $apiName], false);
                throw new \Error('Invalid Settings');
            }

            //Get userId using UserIdUtil if aliasing is enabled and gateway service is provided
            $userId = UserIdUtil::getUserId($context['id'], $this->isAliasingEnabled, $this->serviceContainer);
            if($this->isAliasingEnabled) {
                if($userId != $context['id']) {
                    $loggerService->info('ALIAS_ENABLED', ['userId' => $userId]);
                } else {
                     $loggerService->error('GATEWAY_URL_ERROR', ['an' => ApiEnum::TRACK_EVENT, 'apiName' => $apiName]);
                }
            }
            $context['id'] = $userId;

            $contextModel = new ContextModel();
            $contextModel->modelFromDictionary($context);

            return (new TrackEvent())->track($this->settings, $eventName, $contextModel, $eventProperties, $hookManager, $isDebuggerUsed, $this->serviceContainer);
        } catch (\Throwable $error) {
            $loggerService->error('EXECUTION_FAILED', ['an' => ApiEnum::TRACK_EVENT, 'apiName' => $apiName, 'err' => $error->getMessage()]);
            return [$eventName => false];
        }
    }


    public function setAttribute($attributesOrAttributeValue = null, $attributeValueOrContext = null, $context = null) {

        $apiName = 'setAttribute';
        $isDebuggerUsed = isset($this->options['isDebuggerUsed']);
        
        try {
            $loggerService = $this->serviceContainer->getLoggerService();
            $loggerService->debug("API Called: $apiName");
            
            if (DataTypeUtil::isString($attributesOrAttributeValue)) {
                // Validate attributeKey is a string
                if (!DataTypeUtil::isString($attributesOrAttributeValue)) {
                    $loggerService->error("INVALID_PARAM", ['key' => $attributesOrAttributeValue, 'an'=> ApiEnum::SET_ATTRIBUTE, 'apiName' => $apiName, 'type' => gettype($attributesOrAttributeValue), 'correctType' => 'string'], false);
                    throw new \TypeError('TypeError: attributeKey should be a valid string');
                }

                // Validate attributeValue (the second argument) is valid
                if (!DataTypeUtil::isString($attributeValueOrContext) && 
                    !DataTypeUtil::isNumber($attributeValueOrContext) && 
                    !DataTypeUtil::isBoolean($attributeValueOrContext)) {
                    $loggerService->error("INVALID_PARAM", ['key' => $attributeValueOrContext, 'an'=> ApiEnum::SET_ATTRIBUTE, 'apiName' => $apiName, 'type' => gettype($attributeValueOrContext), 'correctType' => 'string'], false);
                throw new \TypeError('TypeError: attributeValue should be a valid string, number, or boolean');
                }
                 // Ensure context is valid
                if (!isset($context['id']) || empty($context['id'])) {
                    $loggerService->error('INVALID_CONTEXT_PASSED', ['an' => ApiEnum::SET_ATTRIBUTE, 'apiName' => $apiName], false);
                    throw new \Error('TypeError: Invalid context');
                }
    
                //Get userId using UserIdUtil if aliasing is enabled and gateway service is provided
                $userId = UserIdUtil::getUserId($context['id'], $this->isAliasingEnabled, $this->serviceContainer);
                if($this->isAliasingEnabled) {
                    if($userId != $context['id']) {
                        $loggerService->info('ALIAS_ENABLED', ['userId' => $userId]);
                    } else {
                        $loggerService->error('GATEWAY_URL_ERROR', ['an' => ApiEnum::SET_ATTRIBUTE, 'apiName' => $apiName]);
                    }
                }
                $context['id'] = $userId;
                // set uuid in context 
                $context['uuid'] = $this->getUUIDFromContext($context, $apiName);

                $contextModel = new ContextModel();
                $contextModel->modelFromDictionary($context);
    
                // Create the attributes map from key-value
                $attributes = [$attributesOrAttributeValue => $attributeValueOrContext];
                (new SetAttribute())->setAttribute($this->settings, $attributes, $contextModel, $isDebuggerUsed, $this->serviceContainer);
    
            } else {
                // Case where attributeKey is an array (multiple attributes)
                $attributes = $attributesOrAttributeValue;
    
                // Validate attributes is an array
                if (!DataTypeUtil::isArray($attributes)) {
                    $loggerService->error("INVALID_PARAM", ['key' => $attributes, 'an'=> ApiEnum::SET_ATTRIBUTE, 'apiName' => $apiName, 'type' => gettype($attributes), 'correctType' => 'array'], false);
                    throw new \TypeError('TypeError: attributes should be an array');
                }

                // Validate attributes is not empty
                if (empty($attributes)) {
                    $loggerService->error("INVALID_PARAM", ['key' => $attributes, 'an'=> ApiEnum::SET_ATTRIBUTE, 'apiName' => $apiName, 'type' => gettype($attributes), 'correctType' => 'array'], false);
                    throw new \TypeError('TypeError: attributes should be a non-empty array');
                }
                    
                // Validate that each attribute value is of a supported type (string, number, or boolean)
                foreach ($attributes as $key => $value) {
                    if (!is_string($key)) {
                        $loggerService->error("INVALID_PARAM", ['key' => $key, 'an'=> ApiEnum::SET_ATTRIBUTE, 'apiName' => $apiName, 'type' => gettype($key), 'correctType' => 'string'], false);
                        throw new \TypeError("TypeError: attribute key '$key' should only be a string");
                    }

                    if (!DataTypeUtil::isString($value) && !DataTypeUtil::isNumber($value) && !DataTypeUtil::isBoolean($value)) {
                        $loggerService->error("INVALID_PARAM", ['key' => $value, 'an'=> ApiEnum::SET_ATTRIBUTE, 'apiName' => $apiName, 'type' => gettype($value), 'correctType' => 'string'], false);
                        throw new \TypeError("TypeError: attributeValue for key '$key' should be a valid string, number, or boolean");
                    }
                }
                $context = $attributeValueOrContext;
                // Ensure context is valid
                if (!isset($context['id']) || empty($context['id'])) {
                    $loggerService->error('INVALID_CONTEXT_PASSED', ['an' => ApiEnum::SET_ATTRIBUTE, 'apiName' => $apiName], false);
                    throw new \Error('TypeError: Invalid context');
                }
                
                //Get userId using UserIdUtil if aliasing is enabled and gateway service is provided
                $userId = UserIdUtil::getUserId($context['id'], $this->isAliasingEnabled, $this->serviceContainer);
                if($this->isAliasingEnabled) {
                    if($userId != $context['id']) {
                        $loggerService->info('ALIAS_ENABLED', ['userId' => $userId]);
                    } else {
                        $loggerService->error('GATEWAY_URL_ERROR', ['an' => ApiEnum::SET_ATTRIBUTE, 'apiName' => $apiName]);
                    }
                }
                $context['id'] = $userId;
                // set uuid in context 
                $context['uuid'] = $this->getUUIDFromContext($context, $apiName);

                $contextModel = new ContextModel();
                $contextModel->modelFromDictionary($context);
    
                // Proceed with setting the attributes if validation is successful
                (new SetAttribute())->setAttribute($this->settings, $attributes, $contextModel, $isDebuggerUsed, $this->serviceContainer);
            }
        } catch (\Throwable $error) {
            $loggerService->error('EXECUTION_FAILED', ['an' => ApiEnum::SET_ATTRIBUTE, 'apiName' => $apiName, 'err' => $error->getMessage()]);
        }
    }

    /**
     * Sets alias for a given user ID
     * @param mixed $contextOrUserId The context containing user ID or the user ID directly
     * @param string $aliasId The alias identifier to set
     * @return bool Returns true if successful, false otherwise
     */
    public function setAlias($contextOrUserId, $aliasId) {
        $apiName = ApiEnum::SET_ALIAS;

        try {
            $loggerService = $this->serviceContainer->getLoggerService();
            $loggerService->debug("API Called: $apiName");

            if (!$this->isAliasingEnabled) {
                $loggerService->error('ALIAS_CALLED_BUT_NOT_PASSED', ['an' => ApiEnum::SET_ALIAS, 'apiName' => $apiName]);
                return false;
            }

            $settingsService = $this->serviceContainer ? $this->serviceContainer->getSettingsService() : SettingsService::instance();
            if (!$settingsService->isGatewayServiceProvided) {
                $loggerService->error('INVALID_GATEWAY_URL', ['an' => ApiEnum::SET_ALIAS, 'apiName' => $apiName]);
                return false;
            }

            if ($aliasId === null || $aliasId === '') {
                $loggerService->error('INVALID_PARAM', ['key' => $aliasId, 'an'=> ApiEnum::SET_ALIAS, 'apiName' => $apiName, 'type' => gettype($aliasId), 'correctType' => 'string'], false);
                throw new \TypeError('TypeError: Invalid aliasId');
            }

            if (is_array($aliasId)) {
                $loggerService->error('INVALID_PARAM', ['key' => $aliasId, 'an'=> ApiEnum::SET_ALIAS, 'apiName' => $apiName, 'type' => gettype($aliasId), 'correctType' => 'string'], false);
                throw new \TypeError('TypeError: aliasId cannot be an array');
            }

            // trim aliasId before going forward
            if (is_string($aliasId)) {
                $aliasId = trim($aliasId);
            }

            $userId = null;

            if (is_string($contextOrUserId)) {
                if ($contextOrUserId === null || $contextOrUserId === '') {
                    $loggerService->error('INVALID_PARAM', ['key' => $contextOrUserId, 'an'=> ApiEnum::SET_ALIAS, 'apiName' => $apiName, 'type' => gettype($contextOrUserId), 'correctType' => 'string'], false);
                    throw new \TypeError('TypeError: Invalid userId');
                }

                if ($contextOrUserId === $aliasId) {
                    throw new \TypeError('UserId and aliasId cannot be the same.');
                }

                if (is_array($contextOrUserId)) {
                    throw new \TypeError('TypeError: userId cannot be an array');
                }

                $contextOrUserId = trim($contextOrUserId);
                $userId = $contextOrUserId;
            } else {
                if (!is_array($contextOrUserId) || !isset($contextOrUserId['id']) || empty($contextOrUserId['id'])) {
                    $loggerService->error('INVALID_CONTEXT_PASSED', ['an' => ApiEnum::SET_ALIAS, 'apiName' => $apiName], false);
                    throw new \TypeError('TypeError: Invalid context');
                }

                if ($contextOrUserId['id'] === $aliasId) {
                    throw new \TypeError('UserId and aliasId cannot be the same.');
                }

                if(is_array($contextOrUserId['id'])) {
                    throw new \TypeError('TypeError: userId cannot be an array');
                }

                $contextOrUserId['id'] = trim($contextOrUserId['id']);
                $userId = $contextOrUserId['id'];
            }

            $result = AliasingUtil::setAlias($userId, $aliasId, $this->serviceContainer);
            return $result !== false;
        } catch (\Throwable $error) {
            $loggerService->error('EXECUTION_FAILED', ['an' => ApiEnum::SET_ALIAS, 'apiName' => $apiName, 'err' => $error->getMessage()]);
            return false;
        }
    }
    /**
     * Gets the UUID from the context
     * @param array $context The context
     * @param string $apiName The name of the API
     * @return string The UUID
     */
    private function getUUIDFromContext($context, $apiName) {
        if ($this->settings->isWebConnectivityEnabled() != false) {
            // if web connectivity is enabled, check if context ID is a valid web UUID
            if (UuidUtil::isWebUuid($context['id'])) {
                // if context ID is a valid web UUID, set it as uuid
                $this->serviceContainer->getLoggerService()->debug("WEB_UUID_FOUND", array_merge([
                    'apiName' => $apiName,
                    'uuid' => $context['id'],
                ]));
                return $context['id'];
            } else {
                // if context["useIdForWeb"] is true and context ID is not a valid web UUID, throw error
                if (isset($context['useIdForWeb']) && $context['useIdForWeb'] == true) {
                    throw new \TypeError('UUID passed in context.id is not a valid UUID');
                }
                // if context?.useIdForWeb is false, fallback to server‑side UUID derivation
                return UuidUtil::getUUID($context['id'], $this->settings->getAccountId());
            }
        } else {
            // if web connectivity is disabled, fallback to server‑side UUID derivation
            return UuidUtil::getUUID($context['id'], $this->settings->getAccountId());
        }
    }
}
