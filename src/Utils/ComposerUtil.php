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

use Exception;

class ComposerUtil
{
    private static $composerData = null;

    private static function loadComposerData()
    {
        if (self::$composerData === null) {
            $composerJsonPath = __DIR__ . '/../../composer.json'; // Adjust the path if necessary

            if (!file_exists($composerJsonPath)) {
                throw new Exception("composer.json file not found at path: " . $composerJsonPath);
            }

            $composerJsonContent = file_get_contents($composerJsonPath);
            self::$composerData = json_decode($composerJsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Error decoding composer.json: " . json_last_error_msg());
            }
        }
    }

    public static function getSdkVersion()
    {
        try {
            self::loadComposerData();
            return self::$composerData['version'] ?? null;
        } catch (Exception $e) {
            throw new Exception("Error loading composer.json: " . $e->getMessage());
        }
    }
}
