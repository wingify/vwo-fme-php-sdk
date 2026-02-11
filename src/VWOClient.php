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

        $defaultReturnValue = new GetFlagResultUtil(
            false,
            [], // No variables
            []
        );

        //check if isDebuggerUsed is set in options
        $isDebuggerUsed = isset($this->options['isDebuggerUsed']);


        try {
            $logManager = $this->serviceContainer->getLogManager();
            $hookManager = $this->serviceContainer ? $this->serviceContainer->getHooksService() : new HooksService($this->options);

            $logManager->debug("API Called: $apiName");

            if (!DataTypeUtil::isString($featureKey) || $featureKey == null) {
                $logManager->error(
                    sprintf('FeatureKey passed to %s API is not of valid type.', $apiName));
                throw new \TypeError('TypeError: variableSpecifier should be a valid string');
            }

            if (!$this->settings) {
                $logManager->error(sprintf('settings are not valid. Got %s', gettype($this->settings)));
                throw new \Error('Invalid Settings');
            }

            if (!isset($context['id']) || empty($context['id'])) {
                $logManager->error('Context must contain a valid user ID.');
                throw new \Error('TypeError: Invalid context');
            }
            //Get userId using UserIdUtil if aliasing is enabled and gateway service is provided
            $userId = UserIdUtil::getUserId($context['id'], $this->isAliasingEnabled, $this->serviceContainer);
            $context['id'] = $userId;

            $contextModel = new ContextModel();
            $contextModel->modelFromDictionary($context);

            return (new GetFlag())->get($featureKey, $contextModel, $this->serviceContainer, $isDebuggerUsed);
        } catch (\Throwable $error) {
            $logManager = $this->serviceContainer->getLogManager();
            $logManager->error("API - $apiName failed to execute. Error: " . $error->getMessage());
            return $defaultReturnValue;
        }
    }

    public function trackEvent(string $eventName, $context, $eventProperties = []) {
        $apiName = 'trackEvent';
        //check if isDebuggerUsed is set in options
        $isDebuggerUsed = isset($this->options['isDebuggerUsed']);

        try {
            $logManager = $this->serviceContainer->getLogManager();
            $hookManager = $this->serviceContainer ? $this->serviceContainer->getHooksService() : new HooksService($this->options);

            $logManager->debug("API Called: $apiName");

            if (!DataTypeUtil::isString($eventName)) {
                $logManager->error("Event name passed to $apiName API is not a valid string.");
                throw new \TypeError('TypeError: eventName should be a valid string');
            }

            if (!DataTypeUtil::isArray($eventProperties)) {
                $logManager->error("Event properties passed to $apiName API are not valid.");
                throw new \TypeError('TypeError: eventProperties should be an array');
            }

            if (!$this->settings) {
                $logManager->error('Invalid settings detected.');
                throw new \Error('Invalid Settings');
            }

            if (!isset($context['id']) || empty($context['id'])) {
                $logManager->error('Context must contain a valid user ID.');
                throw new \Error('TypeError: Invalid context');
            }

            //Get userId using UserIdUtil if aliasing is enabled and gateway service is provided
            $userId = UserIdUtil::getUserId($context['id'], $this->isAliasingEnabled, $this->serviceContainer);
            $context['id'] = $userId;

            $contextModel = new ContextModel();
            $contextModel->modelFromDictionary($context);

            return (new TrackEvent())->track($this->settings, $eventName, $contextModel, $eventProperties, $hookManager, $isDebuggerUsed, $this->serviceContainer);
        } catch (\Throwable $error) {
            $logManager = $this->serviceContainer->getLogManager();
            $logManager->error("API - $apiName failed to execute. Error: " . $error->getMessage());
            return [$eventName => false];
        }
    }


    public function setAttribute($attributesOrAttributeValue = null, $attributeValueOrContext = null, $context = null) {

        $apiName = 'setAttribute';
        $isDebuggerUsed = isset($this->options['isDebuggerUsed']);
        
        try {
            $logManager = $this->serviceContainer->getLogManager();
            $logManager->debug("API Called: $apiName");

            if (DataTypeUtil::isString($attributesOrAttributeValue)) {
                // Validate attributeKey is a string
                if (!DataTypeUtil::isString($attributesOrAttributeValue)) {
                    $logManager->error("Attribute key passed to $apiName API is not valid.");
                    throw new \TypeError('TypeError: attributeKey should be a valid string');
                }

                // Validate attributeValue (the second argument) is valid
                if (!DataTypeUtil::isString($attributeValueOrContext) && 
                    !DataTypeUtil::isNumber($attributeValueOrContext) && 
                    !DataTypeUtil::isBoolean($attributeValueOrContext)) {
                    $logManager->error("Attribute value passed to $apiName API is not valid.");
                throw new \TypeError('TypeError: attributeValue should be a valid string, number, or boolean');
                }
    
                // Ensure context is valid
                if (!isset($context['id']) || empty($context['id'])) {
                    $logManager->error('Context must contain a valid user ID.');
                    throw new \Error('TypeError: Invalid context');
                }
    
                //Get userId using UserIdUtil if aliasing is enabled and gateway service is provided
                $userId = UserIdUtil::getUserId($context['id'], $this->isAliasingEnabled, $this->serviceContainer);
                $context['id'] = $userId;

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
                    $logManager->error("Attributes passed to $apiName API is not valid.");
                    throw new \TypeError('TypeError: attributes should be an array');
                }

                // Validate attributes is not empty
                if (empty($attributes)) {
                    $logManager->error("Key 'attributesMap' passed to setAttribute API is not of valid type. Got type: null or empty array, should be: a non-empty array.");
                    throw new \TypeError('TypeError: attributes should be a non-empty array');
                }
                    
                // Validate that each attribute value is of a supported type (string, number, or boolean)
                foreach ($attributes as $key => $value) {
                    if (!is_string($key)) {
                        $logManager->error("Attribute key in attributesMap is not valid. Got type: '" . gettype($key) . "', should be: string.");
                        throw new \TypeError("TypeError: attribute key '$key' should only be a string");
                    }

                    if (!DataTypeUtil::isString($value) && !DataTypeUtil::isNumber($value) && !DataTypeUtil::isBoolean($value)) {
                        $logManager->error("Attribute value for key '$key' is not valid.");
                        throw new \TypeError("TypeError: attributeValue for key '$key' should be a valid string, number, or boolean");
                    }
                }
                $context = $attributeValueOrContext;
                // Ensure context is valid
                if (!isset($context['id']) || empty($context['id'])) {
                    $logManager->error('Context must contain a valid user ID.');
                    throw new \Error('TypeError: Invalid context');
                }
                
                //Get userId using UserIdUtil if aliasing is enabled and gateway service is provided
                $userId = UserIdUtil::getUserId($context['id'], $this->isAliasingEnabled, $this->serviceContainer);
                $context['id'] = $userId;

                $contextModel = new ContextModel();
                $contextModel->modelFromDictionary($context);
    
                // Proceed with setting the attributes if validation is successful
                (new SetAttribute())->setAttribute($this->settings, $attributes, $contextModel, $isDebuggerUsed, $this->serviceContainer);
            }
        } catch (\Throwable $error) {
            $logManager = $this->serviceContainer->getLogManager();
            $logManager->error("API - $apiName failed to execute. Error: " . $error->getMessage());
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
            $logManager = $this->serviceContainer->getLogManager();
            $logManager->debug("API Called: $apiName");

            if (!$this->isAliasingEnabled) {
                $logManager->error('Aliasing is not enabled.');
                return false;
            }

            $settingsService = $this->serviceContainer ? $this->serviceContainer->getSettingsService() : SettingsService::instance();
            if (!$settingsService->isGatewayServiceProvided) {
                $logManager->error('Gateway URL is not provided.');
                return false;
            }

            if ($aliasId === null || $aliasId === '') {
                $logManager->error('TypeError: Invalid aliasId');
                throw new \TypeError('TypeError: Invalid aliasId');
            }

            if (is_array($aliasId)) {
                $logManager->error('TypeError: aliasId cannot be an array');
                throw new \TypeError('TypeError: aliasId cannot be an array');
            }

            // trim aliasId before going forward
            if (is_string($aliasId)) {
                $aliasId = trim($aliasId);
            }

            $userId = null;

            if (is_string($contextOrUserId)) {
                if ($contextOrUserId === null || $contextOrUserId === '') {
                    $logManager->error('Invalid userId passed to setAlias API.');
                    throw new \TypeError('TypeError: Invalid userId');
                }

                if ($contextOrUserId === $aliasId) {
                    $logManager->error('UserId and aliasId cannot be the same.');
                    return false;
                }

                if (is_array($contextOrUserId)) {
                    $logManager->error('TypeError: userId cannot be an array');
                    throw new \TypeError('TypeError: userId cannot be an array');
                }

                $contextOrUserId = trim($contextOrUserId);
                $userId = $contextOrUserId;
            } else {
                if (!is_array($contextOrUserId) || !isset($contextOrUserId['id']) || empty($contextOrUserId['id'])) {
                    $logManager->error('Invalid context passed to setAlias API.');
                    throw new \TypeError('TypeError: Invalid context');
                }

                if ($contextOrUserId['id'] === $aliasId) {
                    $logManager->error('UserId and aliasId cannot be the same.');
                    return false;
                }

                if(is_array($contextOrUserId['id'])) {
                    $logManager->error('TypeError: userId cannot be an array');
                    throw new \TypeError('TypeError: userId cannot be an array');
                }

                $contextOrUserId['id'] = trim($contextOrUserId['id']);
                $userId = $contextOrUserId['id'];
            }

            $result = AliasingUtil::setAlias($userId, $aliasId, $this->serviceContainer);
            return $result !== false;
        } catch (\Throwable $error) {
            $logManager = $this->serviceContainer->getLogManager();
            $logManager->error("API - $apiName failed to execute. Error: " . $error->getMessage());
            return false;
        }
    }
}
