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

use vwo\LogMessages;

class LogMessagesEnum {
    
    /**
     * Debug log message keys
     */
    public static $debugMessages = [];
    
    /**
     * Info log message keys  
     */
    public static $infoMessages = [];
    
    /**
     * Error log message keys
     */
    public static $errorMessages = [];
    
    /**
     * Warning log message keys
     */
    public static $warningMessages = [];
    
    /**
     * Trace log message keys
     */
    public static $traceMessages = [];
    
    /**
     * Initialize the message arrays
     */
    public static function init() {
        if (empty(self::$debugMessages)) {
            $logMessages = LogMessages::get();
            self::$debugMessages = $logMessages['debugLogs'] ?? [];
            self::$infoMessages = $logMessages['infoLogs'] ?? [];
            self::$errorMessages = $logMessages['errorLogs'] ?? [];
            self::$warningMessages = $logMessages['warnLogs'] ?? [];
            self::$traceMessages = $logMessages['traceLogs'] ?? [];
        }
    }
    
    /**
     * Get debug messages array
     */
    public static function getDebugMessages(): array {
        self::init();
        return self::$debugMessages;
    }
    
    /**
     * Get info messages array
     */
    public static function getInfoMessages(): array {
        self::init();
        return self::$infoMessages;
    }
    
    /**
     * Get error messages array
     */
    public static function getErrorMessages(): array {
        self::init();
        return self::$errorMessages;
    }
    
    /**
     * Get warning messages array
     */
    public static function getWarningMessages(): array {
        self::init();
        return self::$warningMessages;
    }
    
    /**
     * Get trace messages array
     */
    public static function getTraceMessages(): array {
        self::init();
        return self::$traceMessages;
    }
}