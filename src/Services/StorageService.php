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

namespace vwo\Services;

use vwo\Enums\StorageEnum;
use vwo\Packages\Storage\Storage;
use vwo\Packages\Logger\Core\LogManager;
use vwo\Enums\ApiEnum;

class StorageService
{
    private $storageData = [];

    /**
     * Retrieves data from storage based on the feature key and user ID.
     * @param mixed $featureKey The key to identify the feature data.
     * @param mixed $context The user context containing the ID.
     * @return mixed The data retrieved from storage or an error/storage status enum.
     */
    public function getDataInStorage($featureKey, $context, $serviceContainer)
    {
        $storageInstance = Storage::Instance()->getConnector();

        // Check if the storage instance is available
        if (is_null($storageInstance)) {
            return StorageEnum::STORAGE_UNDEFINED;
        } else {
            try {
                $data = $storageInstance->get($featureKey, $context->getId());
                if ($data !== null) {
                    return $data;
                } else {
                    $serviceContainer->getLogManager()->info("No data found in storage for feature key: " . $featureKey);
                    return StorageEnum::NO_DATA_FOUND;
                }
            } catch (\Exception $e) {
                $serviceContainer->getLoggerService()->error('ERROR_READING_STORED_DATA_IN_STORAGE', ['err' => $e->getMessage(), 'an' => ApiEnum::GET_FLAG]);
                return StorageEnum::NO_DATA_FOUND;
            }
        }
    }

    /**
     * Stores data in the storage.
     * @param array $data The data to be stored.
     * @return bool Returns true if data is successfully stored, otherwise false.
     */
    public function setDataInStorage($data, $serviceContainer)
    {
        $storageInstance = Storage::Instance()->getConnector();

        // Check if the storage instance is available
        if (is_null($storageInstance)) {
            return false;
        } else {
            try {
                return $storageInstance->set($data);
            } catch (\Exception $e) {
                return false;
            }
        }
    }
}