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

namespace vwo\Packages\Logger\Core;

use vwo\Packages\Logger\Enums\LogLevelEnum;
use vwo\Packages\Logger\LogMessageBuilder;

use Ramsey\Uuid\Uuid;


interface IlogTransport {
    public function addTransport($transport): void;
    public function shouldLog($transportLevel, $configLevel): bool;
}

class LogTransportManager extends Logger implements IlogTransport {
    public $transports;
    public $config;

    public function __construct($config) {
        $this->transports = [];
        $this->config = $config;
    }

    public function addTransport($transport): void {
        $this->transports[] = $transport;
    }

    public function shouldLog($transportLevel, $configLevel): bool {
        $transportLevel = $transportLevel ?: $configLevel ?: $this->config['level'];

        $logLevelNumberEnum = [
            'TRACE' => 0,
            'DEBUG' => 1,
            'INFO' => 2,
            'WARN' => 3,
            'ERROR' => 4
        ];

        $targetLevel = $logLevelNumberEnum[strtoupper($transportLevel)];
        $desiredLevel = $logLevelNumberEnum[strtoupper($configLevel ?: $this->config['level'])];

        return $targetLevel >= $desiredLevel;
    }

    public function trace($message): void {
        $this->log(LogLevelEnum::TRACE, $message);
    }

    public function debug($message): void {
        $this->log(LogLevelEnum::DEBUG, $message);
    }

    public function info($message): void {
        $this->log(LogLevelEnum::INFO, $message);
    }

    public function warn($message): void {
        $this->log(LogLevelEnum::WARN, $message);
    }

    public function error($message): void {
        $this->log(LogLevelEnum::ERROR, $message);
    }

    public function log($level, $message): void {
        foreach ($this->transports as $transport) {
            $logMessageBuilder = new LogMessageBuilder($this->config, $transport);
            $formattedMessage = $logMessageBuilder->formatMessage($level, $message);

            if ($this->shouldLog($level, $transport['level'] ?? null)) {
                if (isset($transport['logHandler']) && is_callable($transport['logHandler'])) {
                    $logHandler = $transport['logHandler'];
                    $logHandler($formattedMessage, $level);
                } else {
                    if (method_exists($transport, $level)) {
                        $transport->{$level}($formattedMessage, $level);
                    }
                }
            }
        }
    }
}

?>
