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

namespace vwo\Packages\Logger\Core;

use vwo\Packages\Logger\Enums\LogLevelEnum;
use Ramsey\Uuid\Uuid;
use vwo\Packages\Logger\Core\LogTransportManager;
use vwo\Packages\Logger\Core\Logger;
use vwo\Utils\NetworkUtil as NetworkUtil;
use vwo\Enums\EventEnum;

interface ILogManager {
    public function handleTransports();
    public function addTransport($transport);
    public function addTransports($transports);
    public function trace($message);
    public function debug($message);
    public function info($message);
    public function warn($message);
    public function error($message);
}

class LogManager extends Logger implements ILogManager {
    private static $instance = null;
    private $transportManager;
    private $config;
    private $name = 'VWO Logger';
    private $requestId;
    private $level = LogLevelEnum::ERROR;
    private $prefix = 'VWO-SDK';
    private $dateTimeFormat;
    private $storedMessages; // array to store the messages that have been logged

    public function __construct($config = []) {
        $this->config = $config;
        $this->storedMessages = [];

        // Updated to use a closure for dateTimeFormat
        $this->dateTimeFormat = function() {
            return (new \DateTime())->format(\DateTime::ISO8601);
        };

        if (!isset(self::$instance)) {
            self::$instance = $this;

            $this->config['name'] = $config['name'] ?? $this->name;
            $this->config['requestId'] = $config['requestId'] ?? Uuid::uuid4()->toString();
            $this->config['level'] = $config['level'] ?? $this->level;
            $this->config['prefix'] = $config['prefix'] ?? $this->prefix;
            $this->config['dateTimeFormat'] = $config['dateTimeFormat'] ?? $this->dateTimeFormat;

            $this->transportManager = new LogTransportManager($this->config);

            $this->handleTransports();
        }
    }

    public static function instance(): LogManager {
        if (!self::$instance) {
            throw new \Exception("LogManager instance is not set. Make sure to initialize LogManager before calling instance().");
        }
        return self::$instance;
    }

    public function handleTransports() {
        $transports = $this->config['transports'] ?? null;

        if ($transports && count($transports)) {
            $this->addTransports($transports);
        } elseif (isset($this->config['transport']) && is_array($this->config['transport'])) {
            $this->addTransport($this->config['transport']);
        } else {
            $this->addTransport([
                'level' => $this->config['level'],
                'logHandler' => function ($message, $level) {
                    file_put_contents("php://stdout", $message . PHP_EOL);
                }
            ]);
        }
    }

    public function addTransport($transport) {
        $this->transportManager->addTransport($transport);
    }

    public function addTransports($transports) {
        foreach ($transports as $transport) {
            $this->addTransport($transport);
        }
    }

    public function trace($message) {
        $this->transportManager->log(LogLevelEnum::TRACE, $message);
    }

    public function debug($message) {
        $this->transportManager->log(LogLevelEnum::DEBUG, $message);
    }

    public function info($message) {
        $this->transportManager->log(LogLevelEnum::INFO, $message);
    }

    public function warn($message) {
        $this->transportManager->log(LogLevelEnum::WARN, $message);
    }

    public function error($message): void {
        // Log the error to the transport manager
        $this->transportManager->log(LogLevelEnum::ERROR, $message);
        
        // Skip logging if TEST_ENV is true
        if (getenv('TEST_ENV') === 'true') {
            return;
        }

        // Construct the message with SDK details
        $messageToSend = $message . '-' . getenv('SDK_NAME') . '-' . getenv('SDK_VERSION');

        // Check if the message has already been logged
        if (!isset($this->storedMessages[$messageToSend])) {
            // Add the message to the "set" (array as a set)
            $this->storedMessages[$messageToSend] = true;

            $networkUtil = new NetworkUtil();
            $properties = $networkUtil->getEventsBaseProperties(EventEnum::VWO_ERROR);

            // Create the payload for the messaging event
            $payload = $networkUtil->getMessagingEventPayload('error', $message, EventEnum::VWO_ERROR);

            // Send the constructed payload via POST request
            $networkUtil->sendEvent($properties, $payload, EventEnum::VWO_ERROR);
        }
    }
}
?>
