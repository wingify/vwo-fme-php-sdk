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

namespace vwo\Services;

class HooksService {
    private $callback;
    private $isCallBackFunction;
    private $decision;

    public function __construct(array $options = []) {
        $this->callback = isset($options['integrations']['callback']) ? $options['integrations']['callback'] : null;
        $this->isCallBackFunction = is_callable($this->callback);
        $this->decision = [];
    }

    /**
     * Executes the callback function with the provided properties.
     * 
     * @param array $properties The properties to be passed to the callback.
     */
    public function execute(array $properties): void {
        if ($this->isCallBackFunction) {
            call_user_func($this->callback, $properties);
        }
    }

    /**
     * Stores the provided properties in the decision object.
     * 
     * @param array $properties The properties to store.
     */
    public function set(array $properties): void {
        if ($this->isCallBackFunction) {
            $this->decision = $properties;
        }
    }

    /**
     * Retrieves the stored decision object.
     * 
     * @return array The stored decision object.
     */
    public function get(): array {
        return $this->decision;
    }
}

?>
