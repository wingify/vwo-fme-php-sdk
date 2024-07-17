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


namespace vwo\Utils;

class GetFlagResultUtil
{
    private $isEnabled;
    private $variables;
    private $ruleStatus;

    public function __construct($isEnabled, $variables, $ruleStatus)
    {
        $this->isEnabled = $isEnabled;
        $this->variables = $variables;
        $this->ruleStatus = $ruleStatus;
    }

    public function isEnabled()
    {
        return $this->isEnabled;
    }

    public function getVariables()
    {
        return $this->variables;
    }

    public function getVariable($key, $defaultValue)
    {
        if (is_array($this->variables)) {
            foreach ($this->variables as $variable) {
                if ($variable->getKey() === $key) {
                    return $variable->getValue();
                }
            }
        }
        return $defaultValue;
    }

    public function getRuleStatus()
    {
        return $this->ruleStatus;
    }
}
