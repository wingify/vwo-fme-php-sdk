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
use vwo\Enums\DebuggerCategoryEnum;
use vwo\Utils\DebuggerServiceUtil;

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
     * @param LogManager $logManager
     */
    public function __construct($logManager) {
        $this->logManager = $logManager;

        // Load the log messages from centralized repository
        $logMessages = LogMessages::get();
        
        self::$debugMessages = $logMessages['debugLogs'] ?? [];
        self::$infoMessages = $logMessages['infoLogs'] ?? [];
        self::$errorMessages = $logMessages['errorLogsV2'] ?? [];
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
    public function log($level, $key, $map = [], $shouldLogToVWO = true) {
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
                $message = LogMessageUtil::buildMessage($messageTemplate, $map);
                $logManager->error($message);
                if($shouldLogToVWO) {
                    self::errorLogToVWO($key, $map, $message);
                }
                break; 
        }
    }

    /**
     * Logs a direct message without using message keys
     * 
     * @param string $level The log level
     * @param string $message The message to log
     */
    public function logMessage($level, $message) {
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
    public function debug($key, $map = []) {
        $this->log(LogLevelEnum::DEBUG, $key, $map, false);
    }

    public function info($key, $map = []) {
        $this->log(LogLevelEnum::INFO, $key, $map, false);
    }

    public function trace($key, $map = []) {
        $this->log(LogLevelEnum::TRACE, $key, $map, false);
    }

    public function warn($key, $map = []) {
        $this->log(LogLevelEnum::WARN, $key, $map, false);
    }

    public function error($key, $map = [], $shouldLogToVWO = true) {
        $this->log(LogLevelEnum::ERROR, $key, $map, $shouldLogToVWO);
    }

    /**
     * Convenience methods for different log levels with direct messages
     */
    public function debugMessage($message) {
        $this->logMessage(LogLevelEnum::DEBUG, $message, false);
    }

    public function infoMessage($message) {
        $this->logMessage(LogLevelEnum::INFO, $message, false);
    }

    public function traceMessage($message) {
        $this->logMessage(LogLevelEnum::TRACE, $message, false);
    }

    public function warnMessage($message) {
        $this->logMessage(LogLevelEnum::WARN, $message, false);
    }

    public function errorMessage($message) {
        $this->logMessage(LogLevelEnum::ERROR, $message, false);
    }

    /**
     * This method is used to send an error event to VWO.
     * @param string $template The template of the message.
     * @param array $debugProps The map of the debug props.
     */
    private static function errorLogToVWO($template, $debugProps = [], $message = '') {
        // check if current environment is test then early return
        // in case of test environment, we don't want to send debug events to VWO
        if(getenv('APP_ENV') === 'test') {
            return;
        }
        $debugProps['msg_t'] = $template;
        $debugProps['cg'] = DebuggerCategoryEnum::ERROR;
        $debugProps['lt'] = LogLevelEnum::ERROR;
        $debugProps['msg'] = $message;

        // send debug event to VWO
        DebuggerServiceUtil::sendDebugEventToVWO($debugProps);
    }
} 