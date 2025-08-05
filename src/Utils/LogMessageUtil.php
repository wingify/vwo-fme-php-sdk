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

class LogMessageUtil {
    /**
     * Builds a message by replacing placeholders in the template with values from the map.
     * 
     * @param string $template The message template with placeholders like {key}
     * @param array $map Associative array of key-value pairs to replace placeholders
     * @return string The formatted message
     */
    public static function buildMessage($template, $map = []) {
        if (empty($map)) {
            return $template;
        }

        $message = $template;
        foreach ($map as $key => $value) {
            $placeholder = '{' . $key . '}';
            $message = str_replace($placeholder, $value, $message);
        }

        return $message;
    }
} 