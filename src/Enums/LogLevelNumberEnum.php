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

namespace vwo\Enums;

class LogLevelNumberEnum
{
    const TRACE = 0;
    const DEBUG = 1;
    const INFO = 2;
    const WARN = 3;
    const ERROR = 4;

    public static function fromString(string $level)
    {
        $level = strtoupper($level);
        switch ($level) {
            case 'TRACE':
                return self::TRACE;
            case 'DEBUG':
                return self::DEBUG;
            case 'INFO':
                return self::INFO;
            case 'WARN':
                return self::WARN;
            case 'ERROR':
                return self::ERROR;
            default:
                return null;
        }
    }
}
