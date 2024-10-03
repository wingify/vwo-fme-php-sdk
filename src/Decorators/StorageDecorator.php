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

namespace vwo\Decorators;

use vwo\Packages\Logger\Core\LogManager;
use vwo\Services\StorageService;
use vwo\Enums\StorageEnum;
use vwo\Models\FeatureModel;
use vwo\Models\VariationModel;
use vwo\Models\User\ContextModel;

interface IStorageDecorator
{
    public function getFeatureFromStorage($featureKey, $context, $storageService);
    public function setDataInStorage($data, $storageService);
}

class StorageDecorator implements IStorageDecorator
{
    public function getFeatureFromStorage($featureKey, $context, $storageService)
    {
        $campaignMap = $storageService->getDataInStorage($featureKey, $context);

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

    public function setDataInStorage($data, $storageService)
    {
        $featureKey = $data['featureKey'] ?? null;
        $context = $data['context'] ?? null;
        $rolloutId = $data['rolloutId'] ?? null;
        $rolloutKey = $data['rolloutKey'] ?? null;
        $rolloutVariationId = $data['rolloutVariationId'] ?? null;
        $experimentId = $data['experimentId'] ?? null;
        $experimentKey = $data['experimentKey'] ?? null;
        $experimentVariationId = $data['experimentVariationId'] ?? null;

        if (!$featureKey) {
            LogManager::instance()->error("Error storing data: featureKey is invalid.");
            return false;
        }

        if ($context->getId() == null) {
            LogManager::instance()->error("Error storing data: Context or Context.id is invalid.");
            return false;
        }

        if ($rolloutKey && !$experimentKey && !$rolloutVariationId) {
            LogManager::instance()->error("Error storing data: Variation (rolloutKey, experimentKey or rolloutVariationId) is invalid.");
            return false;
        }

        if ($experimentKey && !$experimentVariationId) {
            LogManager::instance()->error("Error storing data: Variation (experimentKey or experimentVariationId) is invalid.");
            return false;
        }

        $storageService->setDataInStorage([
            'featureKey' => $featureKey,
            'userId' => $context->getId(),
            'rolloutId' => isset($rolloutId) ? $rolloutId : null,
            'rolloutKey' => isset($rolloutKey) ? $rolloutKey : null,
            'rolloutVariationId' => isset($rolloutVariationId) ? $rolloutVariationId : null,
            'experimentId' => isset($experimentId) ? $experimentId : null,
            'experimentKey' => isset($experimentKey) ? $experimentKey : null,
            'experimentVariationId' => isset($experimentVariationId) ? $experimentVariationId : null,
        ]);

        return true;
    }
}
