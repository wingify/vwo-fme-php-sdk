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

use vwo\Models\VariationModel;
use vwo\Models\MetricModel;
use vwo\Models\VariableModel;
use vwo\Utils\FunctionUtil;

class CampaignModel {
    public $id;
    public $segments;
    public $percentTraffic;
    public $isUserListEnabled;
    public $key;
    public $type;
    public $name;
    public $isForcedVariationEnabled;
    public $variations = [];
    public $metrics = [];
    public $variables = [];

    public function copy($campaignModel) {
        $this->metrics = $campaignModel['metrics'];
        $this->variations = $campaignModel['variations'];
        $this->variables = $campaignModel['variables'];
        $this->processCampaignKeys($campaignModel);
    }

    public function modelFromDictionary($campaign) {
        $this->processCampaignProperties($campaign);
        $this->processCampaignKeys($campaign);
        return $this;
    }

    public function processCampaignProperties($campaign) {
        $campaign = FunctionUtil::convertObjectToArray($campaign);
        if (isset($campaign['variables'])) {
            if (is_array($campaign['variables'])) {
                $this->variables = [];
            } else {
                foreach ($campaign['variables'] as $variable) {
                    $this->variables[] = (new VariableModel())->modelFromDictionary($variable);
                }
            }
        }
    
        if (isset($campaign['variations'])) {
            if (!is_array($campaign['variations'])) {
                $this->variations = [];
            } else {
                foreach ($campaign['variations'] as $variation) {
                    $this->variations[] = (new VariationModel())->modelFromDictionary($variation);
                }
            }
        }
    
        if (isset($campaign['metrics'])) {
            if (!is_array($campaign['metrics'])) {
                $this->metrics = [];
            } else {
                foreach ($campaign['metrics'] as $metric) {
                    $metricModel = new MetricModel();
                    // Convert object to array before passing to MetricModel constructor
                    //$metric = $this->convertObjectToArray($metric);
                    $this->metrics[] = $metricModel->modelFromDictionary($metric);
                }
            }
        }
    }    

    public function processCampaignKeys($campaign) {
        $campaign = FunctionUtil::convertObjectToArray($campaign);
        $this->id = isset($campaign['id']) ? $campaign['id'] : null;
        $this->percentTraffic = isset($campaign['percentTraffic']) ? $campaign['percentTraffic'] : null;
        $this->name = isset($campaign['name']) ? $campaign['name'] : null;
        $this->isForcedVariationEnabled = isset($campaign['isForcedVariationEnabled']) ? $campaign['isForcedVariationEnabled'] : null;
        $this->isUserListEnabled = isset($campaign['isUserListEnabled']) ? $campaign['isUserListEnabled'] : null;
        $this->segments = isset($campaign['segments']) ? $campaign['segments'] : null;
        $this->key = isset($campaign['key']) ? $campaign['key'] : null;
        $this->type = isset($campaign['type']) ? $campaign['type'] : null;
    }
    
    public function convertObjectToArray($object) {
        return json_decode(json_encode($object), true);
    }

    public function getId() {
        return $this->id;
    }

    public function getName() {
        return $this->name;
    }

    public function getSegments() {
        return $this->segments;
    }

    public function getTraffic() {
        return $this->percentTraffic;
    }

    public function getType() {
        return $this->type;
    }

    public function getIsForcedVariationEnabled() {
        return $this->isForcedVariationEnabled;
    }

    public function getIsUserListEnabled() {
        return $this->isUserListEnabled;
    }

    public function getKey() {
        return $this->key;
    }

    public function getMetrics() {
        return $this->metrics;
    }

    public function getVariations() {
        return $this->variations;
    }

    public function getVariables() {
        return $this->variables;
    }
}
?>
