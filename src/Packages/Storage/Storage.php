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

namespace vwo\Packages\Storage; // Replace YourNamespace with your actual namespace


class Storage {
    public static $instance;
    public $connector;
    public $storageType;

    public function attachConnector($connector) {
        $this->storageType = isset($connector->name) ? $connector->name : null;

        // For simplicity, directly assigning the passed connector
        $this->connector = $connector;

        return $this->connector;
    }

    public static function instance() {
        self::$instance = self::$instance ?: new Storage();
        return self::$instance;
    }

    public function getConfig() {
        return $this->connector->config;
    }

    public function getConnector() {
        return $this->connector;
    }
}

?>
