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

namespace vwo\Packages\Logger;

use vwo\Enums\LogLevelEnum;

interface ILogMessageBuilder {
    public function formatMessage($level, $message): string;
    public function getFormattedLevel($level): string;
    public function getFormattedDateTime(): string;
}

class LogMessageBuilder implements ILogMessageBuilder {
    public $loggerConfig;
    public $transportConfig;
    public $prefix;
    public $dateTimeFormat;

    public function __construct($loggerConfig, $transportConfig) {
        $this->loggerConfig = $loggerConfig;
        $this->transportConfig = $transportConfig;

        $this->prefix = $transportConfig['prefix'] ?? $loggerConfig['prefix'] ?? '';
        $this->dateTimeFormat = $transportConfig['dateTimeFormat'] ?? $loggerConfig['dateTimeFormat'];
    }

    public function formatMessage($level, $message): string {
        return "{$this->getFormattedLevel($level)} {$this->prefix} {$this->getFormattedDateTime()} {$message}";
    }

    public function getFormattedLevel($level): string {
        $upperCaseLevel = strtoupper($level);
        $ansiColorEnum = [
            'BOLD' => "\x1b[1m",
            'CYAN' => "\x1b[36m",
            'GREEN' => "\x1b[32m",
            'LIGHTBLUE' => "\x1b[94m",
            'RED' => "\x1b[31m",
            'RESET' => "\x1b[0m",
            'WHITE' => "\x1b[30m",
            'YELLOW' => "\x1b[33m"
        ];

        $logLevelColorInfoEnum = [
            LogLevelEnum::TRACE => "{$ansiColorEnum['BOLD']}{$ansiColorEnum['WHITE']}{$upperCaseLevel}{$ansiColorEnum['RESET']}",
            LogLevelEnum::DEBUG => "{$ansiColorEnum['BOLD']}{$ansiColorEnum['LIGHTBLUE']}{$upperCaseLevel} {$ansiColorEnum['RESET']}",
            LogLevelEnum::INFO => "{$ansiColorEnum['BOLD']}{$ansiColorEnum['CYAN']}{$upperCaseLevel}  {$ansiColorEnum['RESET']}",
            LogLevelEnum::WARN => "{$ansiColorEnum['BOLD']}{$ansiColorEnum['YELLOW']}{$upperCaseLevel}  {$ansiColorEnum['RESET']}",
            LogLevelEnum::ERROR => "{$ansiColorEnum['BOLD']}{$ansiColorEnum['RED']}{$upperCaseLevel} {$ansiColorEnum['RESET']}"
        ];

        return $logLevelColorInfoEnum[$level] ?? "{$ansiColorEnum['BOLD']}{$ansiColorEnum['RED']}INVALID{$ansiColorEnum['RESET']}";
    }

    public function getFormattedDateTime(): string {
        if (is_callable($this->dateTimeFormat)) {
            return call_user_func($this->dateTimeFormat);
        } else {
            // Return a default date-time string if dateTimeFormat is not callable
            return date('Y-m-d H:i:s');
        }
    }
}
