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

namespace vwo\Packages\Logger\Transports;

use vwo\Packages\Logger\Core\Logger;
use vwo\Packages\Logger\Enums\LogLevelEnum;

class ConsoleTransport implements Logger {
    private $config;
    private $level;

    public function __construct($config = []) {
        $this->config = $config;
        $this->level = $this->config['level'] ?? LogLevelEnum::ERROR;
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
        // Ensure the message is output to the console with a single newline
        file_put_contents("php://stdout", $message . PHP_EOL);
    }
}

?>
