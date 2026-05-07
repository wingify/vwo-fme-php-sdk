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

namespace vwo\Models;

use vwo\Models\MetricModel;

class HoldoutModel
{
    private $id;
    private $segments = [];
    private $percentTraffic = 0;
    private $isGlobal = false;
    private $featureIds = [];
    private $metrics = [];
    private $isGatewayServiceRequired = false;
    private $name = '';

    /**
     * Build model from settings object.
     *
     * @param object|array $holdout
     * @return self
     */
    public function modelFromDictionary($holdout)
    {
        $holdout = is_array($holdout) ? (object) $holdout : $holdout;

        if (!$holdout) {
            return $this;
        }

        $this->id = isset($holdout->id) ? $holdout->id : null;
        $this->segments = isset($holdout->segments) ? $holdout->segments : null;
        $this->percentTraffic = isset($holdout->percentTraffic) ? (int) $holdout->percentTraffic : 0;
        $this->isGlobal = isset($holdout->isGlobal) ? (bool) $holdout->isGlobal : false;
        $this->featureIds = isset($holdout->featureIds) && is_array($holdout->featureIds) ? $holdout->featureIds : [];
        $this->name = isset($holdout->name) ? $holdout->name : '';

        if (isset($holdout->isGatewayServiceRequired)) {
            $this->isGatewayServiceRequired = (bool) $holdout->isGatewayServiceRequired;
        }

        $this->metrics = [];
        $metricList = null;
        if (isset($holdout->m)) {
            $metricList = $holdout->m;
        } elseif (isset($holdout->metrics)) {
            $metricList = $holdout->metrics;
        }

        if (is_array($metricList)) {
            foreach ($metricList as $metric) {
                $this->metrics[] = (new MetricModel())->modelFromDictionary($metric);
            }
        }

        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getIsGlobal()
    {
        return $this->isGlobal;
    }

    public function getFeatureIds()
    {
        return $this->featureIds;
    }

    public function getPercentTraffic()
    {
        return $this->percentTraffic;
    }

    public function getSegments()
    {
        return $this->segments;
    }

    public function getMetrics()
    {
        return $this->metrics;
    }

    public function getIsGatewayServiceRequired()
    {
        return $this->isGatewayServiceRequired;
    }

    public function setIsGatewayServiceRequired($isGatewayServiceRequired)
    {
        $this->isGatewayServiceRequired = (bool) $isGatewayServiceRequired;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }
}