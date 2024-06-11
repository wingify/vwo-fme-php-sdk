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

namespace vwo\Utils;
require 'vendor/autoload.php';

use Ramsey\Uuid\Uuid;

class UuidUtil
{
    const VWO_NAMESPACE_URL = 'https://vwo.com';

    public static function getRandomUUID($apiKey)
    {
        $namespace = self::generateUUID($apiKey, Uuid::NAMESPACE_DNS);
        $randomUUID = Uuid::uuid5($namespace, Uuid::uuid4()->toString());

        return $randomUUID->toString();
    }

    public static function getUUID($userId, $accountId)
    {
        // Cast userId and accountId to string
        $userId = (string)$userId;
        $accountId = (string)$accountId;

        $userIdNamespace = self::generateUUID($accountId, self::getVwoNamespace());
        $uuidForUserIdAccountId = self::generateUUID($userId, $userIdNamespace);

        $desiredUuid = strtoupper(str_replace('-', '', $uuidForUserIdAccountId->toString()));

        return $desiredUuid;
    }

    private static function generateUUID($name, $namespace)
    {
        if (!$name || !$namespace) {
            return null;
        }

        return Uuid::uuid5($namespace, $name);
    }

    private static function getVwoNamespace()
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, self::VWO_NAMESPACE_URL)->toString();
    }
}
