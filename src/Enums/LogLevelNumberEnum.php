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

enum LogLevelNumberEnum: int {
    case TRACE = 0;
    case DEBUG = 1;
    case INFO = 2;
    case WARN = 3;
    case ERROR = 4;

    // You can add helper methods if needed, for example:
    public static function fromString(string $level): ?self {
        return match (strtoupper($level)) {
            'TRACE' => self::TRACE,
            'DEBUG' => self::DEBUG,
            'INFO' => self::INFO,
            'WARN' => self::WARN,
            'ERROR' => self::ERROR,
            default => null,
        };
    }
}
