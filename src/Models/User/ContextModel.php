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

namespace vwo\Models\User;

class ContextModel
{
    private $id;
    private $userAgent;
    private $ipAddress;
    private $customVariables = [];
    private $variationTargetingVariables = [];
    private $_vwo;

    public function modelFromDictionary($context)
    {
        $this->id = isset($context['id']) ? $context['id'] : null;
        $this->userAgent = isset($context['userAgent']) ? $context['userAgent'] : null;
        $this->ipAddress = isset($context['ipAddress']) ? $context['ipAddress'] : null;

        if (isset($context['customVariables'])) {
            $this->customVariables = $context['customVariables'];
        }

        if (isset($context['variationTargetingVariables'])) {
            $this->variationTargetingVariables = $context['variationTargetingVariables'];
        }

        if (isset($context['_vwo'])) {
            $this->_vwo = $context['_vwo'];
        }

        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getUserAgent()
    {
        return $this->userAgent;
    }

    public function getIpAddress()
    {
        return $this->ipAddress;
    }

    public function getCustomVariables()
    {
        return $this->customVariables;
    }

    public function getVariationTargetingVariables()
    {
        return $this->variationTargetingVariables;
    }

    public function getVwo()
    {
        return $this->_vwo;
    }

    public function setCustomVariables($customVariables)
    {
        $this->customVariables = $customVariables;
    }

    public function setVariationTargetingVariables($variationTargetingVariables)
    {
        $this->variationTargetingVariables = $variationTargetingVariables;
    }

    public function setVwo($vwo)
    {
        $this->_vwo = $vwo;
    }
}

?>
