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

namespace vwo\Decorators;

use vwo\Services\StorageService;
use vwo\Enums\StorageEnum;
use vwo\Models\FeatureModel;
use vwo\Models\VariationModel;
use vwo\Models\User\ContextModel;
use vwo\Services\ServiceContainer;
use vwo\Services\LoggerService;
use vwo\Packages\Logger\Enums\LogLevelEnum;
use vwo\Enums\ApiEnum;

interface IStorageDecorator
{
    public function getFeatureFromStorage($featureKey, $context, $storageService, ServiceContainer $serviceContainer = null);
    public function setDataInStorage($data, $storageService, ServiceContainer $serviceContainer = null);
}

class StorageDecorator implements IStorageDecorator
{
    public function getFeatureFromStorage($featureKey, $context, $storageService, ServiceContainer $serviceContainer = null)
    {
        $campaignMap = $storageService->getDataInStorage($featureKey, $context, $serviceContainer);

        switch ($campaignMap) {
            case StorageEnum::STORAGE_UNDEFINED:
            case StorageEnum::NO_DATA_FOUND:
                return null;
            case StorageEnum::INCORRECT_DATA:
                return StorageEnum::INCORRECT_DATA;
            case StorageEnum::CAMPAIGN_PAUSED:
            case StorageEnum::VARIATION_NOT_FOUND:
                return null;
            case StorageEnum::WHITELISTED_VARIATION:
                return null;
            case StorageEnum::INCORRECT_DATA:
                return StorageEnum::INCORRECT_DATA;
            default:
                return $campaignMap;
        }
    }

    public function setDataInStorage($data, $storageService, ServiceContainer $serviceContainer = null)
    {
        $featureKey = $data['featureKey'] ?? null;
        $featureId = $data['featureId'] ?? null;
        $context = $data['context'] ?? null;
        $rolloutId = $data['rolloutId'] ?? null;
        $rolloutKey = $data['rolloutKey'] ?? null;
        $rolloutVariationId = $data['rolloutVariationId'] ?? null;
        $experimentId = $data['experimentId'] ?? null;
        $experimentKey = $data['experimentKey'] ?? null;
        $experimentVariationId = $data['experimentVariationId'] ?? null;

        $loggerService = $serviceContainer->getLoggerService();

        if (!$featureKey) {
            $loggerService->error("ERROR_STORING_DATA_IN_STORAGE", ["featureKey" => $featureKey, 'an' => ApiEnum::GET_FLAG, 'uuid' => $context->getUUID(), 'sId' => $context->getSessionId()]);
            return false;
        }

        if ($context->getId() == null) {
            $loggerService->error("ERROR_STORING_DATA_IN_STORAGE", ["context" => $context, 'an' => ApiEnum::GET_FLAG, 'uuid' => $context->getUUID(), 'sId' => $context->getSessionId()]);
            return false;
        }

        if ($rolloutKey && !$experimentKey && !$rolloutVariationId) {
            $loggerService->error("ERROR_STORING_DATA_IN_STORAGE", ["rolloutKey" => $rolloutKey, "experimentKey" => $experimentKey, "rolloutVariationId" => $rolloutVariationId, 'an' => ApiEnum::GET_FLAG, 'uuid' => $context->getUUID(), 'sId' => $context->getSessionId()]);
            return false;
        }

        if ($experimentKey && !$experimentVariationId) {
            $loggerService->error("ERROR_STORING_DATA_IN_STORAGE", ["experimentKey" => $experimentKey, "experimentVariationId" => $experimentVariationId, 'an' => ApiEnum::GET_FLAG, 'uuid' => $context->getUUID(), 'sId' => $context->getSessionId()]);
            return false;
        }

        $storageData = [
            'featureKey' => $featureKey,
            'userId' => $context->getId(),
            'rolloutId' => isset($rolloutId) ? $rolloutId : null,
            'rolloutKey' => isset($rolloutKey) ? $rolloutKey : null,
            'rolloutVariationId' => isset($rolloutVariationId) ? $rolloutVariationId : null,
            'experimentId' => isset($experimentId) ? $experimentId : null,
            'experimentKey' => isset($experimentKey) ? $experimentKey : null,
            'experimentVariationId' => isset($experimentVariationId) ? $experimentVariationId : null,
        ];

        // Add featureId if provided
        if ($featureId !== null) {
            $storageData['featureId'] = $featureId;
        }

        $storageService->setDataInStorage($storageData, $serviceContainer);

        return true;
    }
}
