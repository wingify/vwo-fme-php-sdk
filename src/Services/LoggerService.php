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

namespace vwo\Services;

use vwo\Packages\Logger\Core\LogManager;
use vwo\Enums\LogLevelEnum;
use vwo\Utils\LogMessageUtil;
use vwo\LogMessages;

class LoggerService {
    public static $debugMessages = [];
    public static $errorMessages = [];
    public static $infoMessages = [];
    public static $warningMessages = [];
    public static $traceMessages = [];
    private $logManager;
    /**
     * Constructor initializes LogManager and loads message files
     * 
     * @param array $config Configuration for the logger
     */
    public function __construct($config = []) {
        $this->logManager = LogManager::instance();

        // Load the log messages from centralized repository
        $logMessages = LogMessages::get();
        
        self::$debugMessages = $logMessages['debugLogs'] ?? [];
        self::$infoMessages = $logMessages['infoLogs'] ?? [];
        self::$errorMessages = $logMessages['errorLogs'] ?? [];
        self::$warningMessages = $logMessages['warnLogs'] ?? [];
        self::$traceMessages = $logMessages['traceLogs'] ?? [];
    }

    /**
     * Logs a message using a key and parameters map
     * 
     * @param string $level The log level
     * @param string $key The message key to look up
     * @param array $map Associative array of parameters to replace in the message
     */
    public static function log($level, $key, $map = []) {
        $logManager = $this->logManager;
        $messageTemplate = '';

        switch ($level) {
            case LogLevelEnum::DEBUG:
                $messageTemplate = isset(self::$debugMessages[$key]) ? self::$debugMessages[$key] : $key;
                $logManager->debug(LogMessageUtil::buildMessage($messageTemplate, $map));
                break;
            case LogLevelEnum::INFO:
                $messageTemplate = isset(self::$infoMessages[$key]) ? self::$infoMessages[$key] : $key;
                $logManager->info(LogMessageUtil::buildMessage($messageTemplate, $map));
                break;
            case LogLevelEnum::TRACE:
                $messageTemplate = isset(self::$traceMessages[$key]) ? self::$traceMessages[$key] : $key;
                $logManager->trace(LogMessageUtil::buildMessage($messageTemplate, $map));
                break;
            case LogLevelEnum::WARN:
                $messageTemplate = isset(self::$warningMessages[$key]) ? self::$warningMessages[$key] : $key;
                $logManager->warn(LogMessageUtil::buildMessage($messageTemplate, $map));
                break;
            default:
                $messageTemplate = isset(self::$errorMessages[$key]) ? self::$errorMessages[$key] : $key; 
                $logManager->error(LogMessageUtil::buildMessage($messageTemplate, $map));
                break;
        }
    }

    /**
     * Logs a direct message without using message keys
     * 
     * @param string $level The log level
     * @param string $message The message to log
     */
    public static function logMessage($level, $message) {
        $logManager = $this->logManager;
        
        switch ($level) {
            case LogLevelEnum::DEBUG:
                $logManager->debug($message);
                break;
            case LogLevelEnum::INFO:
                $logManager->info($message);
                break;
            case LogLevelEnum::TRACE:
                $logManager->trace($message);
                break;
            case LogLevelEnum::WARN:
                $logManager->warn($message);
                break;
            default:
                $logManager->error($message);
                break;
        }
    }

    /**
     * Convenience methods for different log levels with key and parameters
     */
    public static function debug($key, $map = []) {
        self::log(LogLevelEnum::DEBUG, $key, $map);
    }

    public static function info($key, $map = []) {
        self::log(LogLevelEnum::INFO, $key, $map);
    }

    public static function trace($key, $map = []) {
        self::log(LogLevelEnum::TRACE, $key, $map);
    }

    public static function warn($key, $map = []) {
        self::log(LogLevelEnum::WARN, $key, $map);
    }

    public static function error($key, $map = []) {
        self::log(LogLevelEnum::ERROR, $key, $map);
    }

    /**
     * Convenience methods for different log levels with direct messages
     */
    public static function debugMessage($message) {
        self::logMessage(LogLevelEnum::DEBUG, $message);
    }

    public static function infoMessage($message) {
        self::logMessage(LogLevelEnum::INFO, $message);
    }

    public static function traceMessage($message) {
        self::logMessage(LogLevelEnum::TRACE, $message);
    }

    public static function warnMessage($message) {
        self::logMessage(LogLevelEnum::WARN, $message);
    }

    public static function errorMessage($message) {
        self::logMessage(LogLevelEnum::ERROR, $message);
    }
} 