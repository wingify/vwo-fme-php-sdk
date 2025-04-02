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
use vwo\Packages\Logger\Core\LogManager;
use vwo\Services\HooksService;
use vwo\Utils\DataTypeUtil;
use vwo\Api\TrackEvent;
use vwo\Api\GetFlag;
use vwo\Api\SetAttribute;
use vwo\Models\SettingsModel;
use vwo\Models\User\ContextModel;
use vwo\Utils\SettingsUtil;

interface IVWOClient {
    public function getFlag(string $featureKey, $context);
    public function trackEvent(string $eventName, $context, $eventProperties);
    public function setAttribute(string $attributeKey, string $attributeValue, $context);
}

class VWOClient implements IVWOClient {
    public $settings;
    private $storage;
    private $options;

    public function __construct(SettingsModel $settings, array $options) {
        $this->options = $options;
        $this->settings = $settings;
        $this->storage = new Storage();
        $this->initialize($options, $settings);
    }

    private function initialize(array $options, $settings) {
        $collectionPrefix = $this->settings->getCollectionPrefix();
        $gatewayServiceUrl = $options['gatewayService']['url'] ?? null;
        UrlService::init(compact('collectionPrefix', 'gatewayServiceUrl'));

        foreach ($this->settings->getCampaigns() as $campaign) {
            CampaignUtil::setVariationAllocation($campaign);
        }
        SettingsUtil::setSettingsAndAddCampaignsToRules($settings, $this);
        LogManager::instance()->info('VWO Client initialized');
    }

    public function getFlag(string $featureKey, $context) {
        $apiName = 'getFlag';

        $defaultReturnValue = new GetFlagResultUtil(
            false,
            [], // No variables
            []
        );

        try {
            $hookManager = new HooksService($this->options);

            LogManager::instance()->debug("API Called: $apiName");

            if (!DataTypeUtil::isString($featureKey) || $featureKey == null) {
                LogManager::instance()->error(
                    sprintf('FeatureKey passed to %s API is not of valid type.', $apiName));
                throw new \TypeError('TypeError: variableSpecifier should be a valid string');
            }

            if (!$this->settings) {
                LogManager::instance()->error(sprintf('settings are not valid. Got %s', gettype($this->settings)));
                throw new \Error('Invalid Settings');
            }

            if (!isset($context['id']) || empty($context['id'])) {
                LogManager::instance()->error('Context must contain a valid user ID.');
                throw new \Error('TypeError: Invalid context');
            }

            $contextModel = new ContextModel();
            $contextModel->modelFromDictionary($context);

            return (new GetFlag())->get($featureKey, $this->settings, $contextModel, $hookManager);
        } catch (\Throwable $error) {
            LogManager::instance()->error("API - $apiName failed to execute. Error: " . $error->getMessage());
            return $defaultReturnValue;
        }
    }

    public function trackEvent(string $eventName, $context, $eventProperties) {
        $apiName = 'trackEvent';

        try {
            $hookManager = new HooksService($this->options);

            LogManager::instance()->debug("API Called: $apiName");

            if (!DataTypeUtil::isString($eventName)) {
                LogManager::instance()->error("Event name passed to $apiName API is not a valid string.");
                throw new \TypeError('TypeError: eventName should be a valid string');
            }

            if (!DataTypeUtil::isArray($eventProperties)) {
                LogManager::instance()->error("Event properties passed to $apiName API are not valid.");
                throw new \TypeError('TypeError: eventProperties should be an array');
            }

            if (!$this->settings) {
                LogManager::instance()->error('Invalid settings detected.');
                throw new \Error('Invalid Settings');
            }

            if (!isset($context['id']) || empty($context['id'])) {
                LogManager::instance()->error('Context must contain a valid user ID.');
                throw new \Error('TypeError: Invalid context');
            }

            $contextModel = new ContextModel();
            $contextModel->modelFromDictionary($context);

            return (new TrackEvent())->track($this->settings, $eventName, $contextModel, $eventProperties, $hookManager);
        } catch (\Throwable $error) {
            LogManager::instance()->error("API - $apiName failed to execute. Error: " . $error->getMessage());
            return [$eventName => false];
        }
    }

    public function setAttribute(string $attributeKey, string $attributeValue, $context) {
        $apiName = 'setAttribute';

        try {
            LogManager::instance()->debug("API Called: $apiName");

            if (!DataTypeUtil::isString($attributeKey)) {
                LogManager::instance()->error("Attribute key passed to $apiName API is not valid.");
                throw new \TypeError('TypeError: attributeKey should be a valid string');
            }

            if (!DataTypeUtil::isString($attributeValue) && !DataTypeUtil::isNumber($attributeValue) && !DataTypeUtil::isBoolean($attributeValue)) {
                LogManager::instance()->error("Attribute value passed to $apiName API is not valid.");
                throw new \TypeError('TypeError: attributeValue should be a valid string, number, or boolean');
            }

            if (!isset($context['id']) || empty($context['id'])) {
                LogManager::instance()->error('Context must contain a valid user ID.');
                throw new \Error('TypeError: Invalid context');
            }

            $contextModel = new ContextModel();
            $contextModel->modelFromDictionary($context);

            (new SetAttribute())->setAttribute($this->settings, $attributeKey, $attributeValue, $contextModel);
        } catch (\Throwable $error) {
            LogManager::instance()->error("API - $apiName failed to execute. Error: " . $error->getMessage());
        }
    }
}
