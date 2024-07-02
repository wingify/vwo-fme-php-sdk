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

namespace vwo\Models;

use vwo\Models\MetricModel;
use vwo\Models\CampaignModel;
use vwo\Models\RuleModel;
use vwo\Models\ImpactCapmaignModel;
use vwo\Utils\FunctionUtil;

class FeatureModel
{
    private $metrics = [];
    private $id;
    private $key;
    private $name;
    private $type;
    private $rules = [];
    private $impactCampaign = null;
    private $rulesLinkedCampaign = [];

    public function modelFromDictionary($feature)
    {
        $this->id = isset($feature->id) ? $feature->id : null;
        $this->key = isset($feature->key) ? $feature->key : null;
        $this->name = isset($feature->name) ? $feature->name : null;
        $this->type = isset($feature->type) ? $feature->type : null;

        if (isset($feature->impactCampaign)) {
            $this->impactCampaign = (new ImpactCapmaignModel())->modelFromDictionary($feature->impactCampaign);
        }

        if (isset($feature->m) || isset($feature->metrics)) {
            $metricList = isset($feature->m) ? $feature->m : $feature->metrics;
            foreach ($metricList as $metric) {
                $this->metrics[] = (new MetricModel())->modelFromDictionary($metric);
            }
        }

        if (isset($feature->rules)) {
            $ruleList = $feature->rules;
            foreach ($ruleList as $rule) {
                $this->rules[] = (new RuleModel())->modelFromDictionary($rule);
            }
        }

        if (isset($feature->rulesLinkedCampaign)) {
            $linkedCampaignList = $feature->rulesLinkedCampaign;
            foreach ($linkedCampaignList as $linkedCampaign) {
                $this->rulesLinkedCampaign[] = (new CampaignModel())->modelFromDictionary($linkedCampaign);
            }
        }

        return $this;
    }

    public function setRulesLinkedCampaign($rulesLinkedCampaign)
    {
        $this->rulesLinkedCampaign = $rulesLinkedCampaign;
    }

    public function getRulesLinkedCampaign()
    {
        return $this->rulesLinkedCampaign;
    }

    public function setImpactCampaign($impactCampaign)
    {
        $this->impactCampaign = $impactCampaign;
    }

    public function getImpactCampaign()
    {
        return $this->impactCampaign;
    }

    public function setMetrics($metrics)
    {
        $this->metrics = $metrics;
    }

    public function getMetrics()
    {
        return $this->metrics;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function getRules()
    {
        return $this->rules;
    }
}
?>
