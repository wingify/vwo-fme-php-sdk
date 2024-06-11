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

use vwo\Models\FeatureModel;
use vwo\Packages\Logger\Core\LogManager;
use vwo\Services\StorageService;
use vwo\Enums\StorageEnum;

interface IStorageDecorator
{
    public function getFeatureFromStorage(string $featureKey, array $user, StorageService $storageService);
    public function setDataInStorage(array $data, StorageService $storageService);
}

class StorageDecorator implements IStorageDecorator
{
    public function getFeatureFromStorage(string $featureKey, array $user, StorageService $storageService)
    {
        $campaignMap = $storageService->getDataInStorage($featureKey, $user);

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
                // Handle whitelisting logic here
                return null;
            default:
                return $campaignMap;
        }
    }

    public function setDataInStorage(array $data, StorageService $storageService)
    {
        $requiredFields = ['featureKey', 'user'];

        if (array_diff_key(array_flip($requiredFields), $data)) {
            LogManager::instance()->error("Missing required fields for storage: " . implode(', ', array_diff($requiredFields, array_keys($data))));
            return false;
        }

        // Extract data
        extract($data);

        $validationErrors = [];
        if (!$featureKey) {
            $validationErrors[] = "Feature key is not valid.";
        }
        if (!isset($user['id'])) {
            $validationErrors[] = "User ID is not valid.";
        }
        if ((isset($rolloutKey) && !isset($experimentKey) && !isset($rolloutVariationId)) ||
            (isset($experimentKey) && !isset($experimentVariationId))) {
            $validationErrors[] = "Invalid variation data for rules.";
        }

        if ($validationErrors) {
            LogManager::instance()->error(implode(', ', $validationErrors));
            return false;
        }

        // Assuming user ID is stored in a property named "id"
        $storageService->setDataInStorage([
            'featureKey' => $featureKey,
            'user' => $user['id'],
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
