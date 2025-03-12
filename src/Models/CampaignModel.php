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

use vwo\Models\VariationModel;
use vwo\Models\MetricModel;
use vwo\Models\VariableModel;
use vwo\Utils\FunctionUtil;

class CampaignModel
{
    private $id;
    private $segments;
    private $percentTraffic;
    private $isUserListEnabled;
    private $key;
    private $type;
    private $name;
    private $isForcedVariationEnabled;
    private $variations = [];
    private $metrics = [];
    private $variables = [];
    private $status;
    private $variationId;
    private $campaignId;
    private $weight;
    private $ruleKey;
    private $salt;

    public function copy($campaignModel)
    {
        $this->metrics = $campaignModel->metrics;
        $this->variations = $campaignModel->variations;
        $this->variables = $campaignModel->variables;
        $this->processCampaignKeys($campaignModel);
    }

    public function modelFromDictionary($campaign)
    {
        $this->processCampaignProperties($campaign);
        $this->processCampaignKeys($campaign);
        return $this;
    }

    public function processCampaignProperties($campaign)
    {
        if (isset($campaign->variables)) {
            $this->variables = [];
            foreach ($campaign->variables as $variable) {
                $this->variables[] = (new VariableModel())->modelFromDictionary($variable);
            }
        }

        if (isset($campaign->variations)) {
            $this->variations = [];
            foreach ($campaign->variations as $variation) {
                $this->variations[] = (new VariationModel())->modelFromDictionary($variation);
            }
        }

        if (isset($campaign->metrics)) {
            $this->metrics = [];
            foreach ($campaign->metrics as $metric) {
                $metricModel = new MetricModel();
                $this->metrics[] = $metricModel->modelFromDictionary($metric);
            }
        }
    }

    public function processCampaignKeys($campaign)
    {
        $this->id = isset($campaign->id) ? $campaign->id : null;
        $this->percentTraffic = isset($campaign->percentTraffic) ? $campaign->percentTraffic : null;
        $this->name = isset($campaign->name) ? $campaign->name : null;
        $this->isForcedVariationEnabled = isset($campaign->isForcedVariationEnabled) ? $campaign->isForcedVariationEnabled : null;
        $this->isUserListEnabled = isset($campaign->isUserListEnabled) ? $campaign->isUserListEnabled : null;
        $this->segments = isset($campaign->segments) ? $campaign->segments : null;
        $this->key = isset($campaign->key) ? $campaign->key : null;
        $this->type = isset($campaign->type) ? $campaign->type : null;
        $this->ruleKey = isset($campaign->ruleKey) ? $campaign->ruleKey : null;
        $this->salt = isset($campaign->salt) ? $campaign->salt : null;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getSegments()
    {
        return $this->segments;
    }

    public function getTraffic()
    {
        return $this->percentTraffic;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getIsForcedVariationEnabled()
    {
        return $this->isForcedVariationEnabled;
    }

    public function getIsUserListEnabled()
    {
        return $this->isUserListEnabled;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function getMetrics()
    {
        return $this->metrics;
    }

    public function getVariations()
    {
        return $this->variations;
    }

    public function getVariables()
    {
        return $this->variables;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getVariationId()
    {
        return $this->variationId;
    }

    public function getCampaignId()
    {
        return $this->campaignId;
    }

    public function getWeight()
    {
        return $this->weight;
    }

    public function getRuleKey() {
        return $this->ruleKey;
    }

    public function getSalt() {
        return $this->salt;
    }
    
    public function setId($id)
    {
        $this->id = $id;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setSegments($segments)
    {
        $this->segments = $segments;
    }

    public function setTraffic($percentTraffic)
    {
        $this->percentTraffic = $percentTraffic;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function setIsForcedVariationEnabled($isForcedVariationEnabled)
    {
        $this->isForcedVariationEnabled = $isForcedVariationEnabled;
    }

    public function setIsUserListEnabled($isUserListEnabled)
    {
        $this->isUserListEnabled = $isUserListEnabled;
    }

    public function setKey($key)
    {
        $this->key = $key;
    }

    public function setMetrics($metrics)
    {
        $this->metrics = $metrics;
    }

    public function setVariations($variations)
    {
        $this->variations = $variations;
    }

    public function setVariables($variables)
    {
        $this->variables = $variables;
    }
    public function setCampaignId($campaignId)
    {
        $this->campaignId = $campaignId;
    }
    public function setVariationId($variationId)
    {
        $this->variationId = $variationId;
    }
    public function setStatus($status)
    {
        $this->status = $status;
    }
    public function setWeight($weight)
    {
      $this->weight = $weight;
    }
    public function setRuleKey($ruleKey) 
    {    
        $this->ruleKey = $ruleKey;
    }
    public function setSalt($salt) 
    {
        $this->salt = $salt;
    }
}
