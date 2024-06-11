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

namespace vwo\Services;

use vwo\Enums\StorageEnum;
use vwo\Packages\Storage\Storage;

class StorageService
{
    private $storageData = [];

    public function getDataInStorage($featureKey, $user)
    {
        $storageInstance = Storage::Instance()->getConnector();

        if (is_null($storageInstance)) {
            return StorageEnum::STORAGE_UNDEFINED;
        } else {
            $data = $storageInstance->get($featureKey, $user['id']);
            if ($data !== null) {
                return $data;
            } else {
                // TODO: Add logging here
                return StorageEnum::NO_DATA_FOUND;
            }
        }
    }

    public function setDataInStorage($data)
    {
        $storageInstance = Storage::Instance()->getConnector();

        if (is_null($storageInstance)) {
            return false;
        } else {
            return $storageInstance->set($data);
        }
    }
}

?>
