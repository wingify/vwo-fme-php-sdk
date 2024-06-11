<?php

/**
 * Copyright 2024 Wingify Software Pvt. Ltd.
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
use vwo\Models\SettingsModel;
use vwo\Services\UrlService;
use vwo\Utils\CampaignUtil;
use vwo\Packages\Storage\Storage;
use vwo\Utils\FunctionUtil;
use vwo\Packages\Logger\Core\LogManager;
use vwo\Services\HooksManager;
use vwo\Utils\DataTypeUtil;
use vwo\Api\GetFlag;
use vwo\Enums\LogMessages\DebugLogMessageEnum;
use vwo\Api\TrackEvent;
use vwo\Api\SetAttribute;

interface IVWOClient {
    public function getFlag(string $featureKey, $context);
    public function trackEvent(string $eventName, $context, array $eventProperties);
    public function setAttribute(string $attributeKey, string $attributeValue, $context);
}

class VWOClient implements IVWOClient {
    private $settings;
    private $storage;
    private $options;

    public function __construct(SettingsModel $settings, array $options) {
        $this->settings = $settings;
        $this->storage = new Storage();
        $this->initialize($options);
        $this->options = $options;
    }

    private function initialize(array $options) {
        $collectionPrefix = $this->settings->getCollectionPrefix();
        $VWOGatewayServiceUrl = $options['VWOGatewayService']['url'] ?? null;
        UrlService::init(compact('collectionPrefix', 'VWOGatewayServiceUrl'));

        foreach ($this->settings->getCampaigns() as $campaign) {
            CampaignUtil::setVariationAllocation($campaign);
        }
        FunctionUtil::addLinkedCampaignsToSettings($this->settings);
        LogManager::instance()->info('VWO Client initialized');
    }

    public function getFlag(string $featureKey, $context) {
        $apiName = 'getFlag';

        $defaultReturnValue = new GetFlagResultUtil(
            false,
            [] // No variables
        );
    

        try {
            $hookManager = new HooksManager($this->options);

            LogManager::instance()->debug(sprintf(DebugLogMessageEnum::API_CALLED, $apiName));

            if (!DataTypeUtil::isString($featureKey)) {
                LogManager::instance()->error(
                    sprintf('featureKey passed to %s API is not of valid type. Got %s', $apiName, gettype($featureKey))
                );
                throw new \TypeError('TypeError: variableSpecifier should be a string');
            }

            if (!$this->settings) {
                LogManager::instance()->error(sprintf('settings are not valid. Got %s', gettype($this->settings)));
                throw new \Error('Invalid Settings');
            }

            if (!isset($context['id'])) {
                LogManager::instance()->error('Context should be an object and must contain a mandatory key - id, which is User ID');
                throw new \Error('TypeError: Invalid context');
            }

            // Wrap the context in a 'user' key if it's not already
            $context = ['user' => $context];

            return (new GetFlag())->get($featureKey, $this->settings, $context, $hookManager);
        } catch (\Throwable $error) {
            LogManager::instance()->error(sprintf('API - %s failed to execute. Trace: %s', $apiName, $error->getMessage()));
            return $defaultReturnValue;
        }
    }

    public function trackEvent(string $eventName, $context, array $eventProperties = [])
    {
        $apiName = 'trackEvent';
        try {
            $hookManager = new HooksManager($this->options);

            LogManager::instance()->debug(sprintf(DebugLogMessageEnum::API_CALLED, $apiName));

            if (!DataTypeUtil::isString($eventName)) {
                LogManager::instance()->debug(sprintf('eventName passed to track API is not of valid type. Got %s', gettype($eventName)));
                throw new \TypeError('TypeError: eventName should be a string');
            }

            if (!$this->settings) {
                LogManager::instance()->debug(sprintf('settings are not valid. Got %s', gettype($this->settings)));
                throw new \Error('Invalid Settings');
            }

            if (!isset($context['id'])) {
                LogManager::instance()->error('Context should be an object and must contain a mandatory key - id, which is User ID');
                throw new \Error('TypeError: Invalid context');
            }

            // Wrap the context in a 'user' key 
            $context = ['user' => $context];

            return (new TrackEvent())->track($this->settings, $eventName, $eventProperties, $context, $hookManager);
        } catch (\Throwable $error) {
            LogManager::instance()->error(sprintf('API - %s failed to execute. Trace: %s', $apiName, $error->getMessage()));
            return [$eventName => false];
        }
    }

    public function setAttribute($attributeKey, $attributeValue, $context)
    {
        $apiName = 'setAttribute';
        try {
            LogManager::instance()->debug(sprintf(DebugLogMessageEnum::API_CALLED, $apiName));

            if (!DataTypeUtil::isString($attributeKey) || !DataTypeUtil::isString($attributeValue) || !DataTypeUtil::isString($context['id'] ?? null)) {
                LogManager::instance()->error('Paramters passed to setAttribute API are not valid. Please check');
                return;
            }

            if (!isset($context['id'])) {
                LogManager::instance()->error('Context should be an object and must contain a mandatory key - id, which is User ID');
                throw new \Error('TypeError: Invalid context');
            }

            (new SetAttribute())->setAttribute($this->settings, $attributeKey, $attributeValue, $context);
        } catch (\Throwable $error) {
            LogManager::instance()->error(sprintf('API - %s failed to execute. Trace: %s', $apiName, $error->getMessage()));
        }
    }
}
