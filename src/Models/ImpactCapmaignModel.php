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

class ImpactCapmaignModel {
  private $campaignId;
  private $type;

  public function modelFromDictionary($impactCampaign) {
    $this->type = isset($impactCampaign->type) ? $impactCampaign->type : null;
    $this->campaignId = isset($impactCampaign->campaignId) ? $impactCampaign->campaignId : null;
    return $this;
  }

  public function getCampaignId() {
    return $this->campaignId;
  }

  public function getType() {
    return $this->type;
  }
}

?>
