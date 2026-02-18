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

namespace vwo\Utils;

use Ramsey\Uuid\Uuid;

class UuidUtil
{
    const VWO_NAMESPACE_URL = 'https://vwo.com';

    /**
     * Generates a random UUID based on an API key.
     * 
     * @param string $apiKey The API key used to generate a namespace for the UUID.
     * @return string A random UUID string.
     */
    public static function getRandomUUID($apiKey)
    {
        // Generate a namespace based on the API key using DNS namespace
        $namespace = self::generateUUID($apiKey, Uuid::NAMESPACE_DNS);
        // Generate a random UUID using the namespace derived from the API key
        $randomUUID = Uuid::uuid5($namespace, Uuid::uuid4()->toString());

        return $randomUUID->toString();
    }

    /**
     * Generates a UUID for a user based on their userId and accountId.
     * 
     * @param string $userId The user's ID.
     * @param string $accountId The account ID associated with the user.
     * @return string A UUID string formatted without dashes and in uppercase.
     */
    public static function getUUID($userId, $accountId)
    {
        // Convert userId and accountId to strings to ensure proper type
        $userId = (string)$userId;
        $accountId = (string)$accountId;

        // Generate a namespace UUID based on the accountId
        $userIdNamespace = self::generateUUID($accountId, self::getVwoNamespace());
        // Generate a UUID based on the userId and the previously generated namespace
        $uuidForUserIdAccountId = self::generateUUID($userId, $userIdNamespace);

        // Remove all dashes from the UUID and convert it to uppercase
        $desiredUuid = strtoupper(str_replace('-', '', $uuidForUserIdAccountId->toString()));

        return $desiredUuid;
    }

    /**
     * Helper function to generate a UUID v5 based on a name and a namespace.
     * 
     * @param string $name The name from which to generate the UUID.
     * @param string $namespace The namespace used to generate the UUID.
     * @return \Ramsey\Uuid\UuidInterface|null A UUID object or null if inputs are invalid.
     */
    private static function generateUUID($name, $namespace)
    {
        if (!$name || !$namespace) {
            return null;
        }

        // Generate and return the UUID v5
        return Uuid::uuid5($namespace, $name);
    }

    /**
     * Generates the VWO namespace UUID based on a constant URL.
     * 
     * @return string The VWO namespace UUID string.
     */
    private static function getVwoNamespace()
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, self::VWO_NAMESPACE_URL)->toString();
    }
    /**
     * Validates whether the given string is a web-generated UUID.
     * Performs a basic check that an incoming context.id looks like a web-generated ID:
     *   D or J + 32 hex chars = 33 chars total.
     *
     * @param string $id The context ID string to validate (e.g. from context.id).
     * @return bool True if id matches the web-generated UUID format (D or J followed by 32 hex chars); false otherwise.
     */
    public static function isWebUuid($id)
    {
        if (!is_string($id)) {
            return false;
        }
        return preg_match('/^[DJ][0-9A-Fa-f]{32}$/', $id) === 1;
    }
}
