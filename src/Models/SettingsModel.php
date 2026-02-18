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

use vwo\Models\FeatureModel;
use vwo\Models\CampaignModel;
use vwo\Utils\FunctionUtil;

class SettingsModel {
  private $sdkKey;
  private $features = [];
  private $campaigns = [];
  private $campaignGroups = [];
  private $groups = [];
  private $accountId;
  private $version;
  private $collectionPrefix;
  private $isWebConnectivityEnabled;

  public function __construct($settings) {    
    $this->sdkKey = isset($settings->sK) ? $settings->sK : (isset($settings->sdkKey) ? $settings->sdkKey : null);
    $this->accountId = isset($settings->a) ? $settings->a : (isset($settings->accountId) ? $settings->accountId : null);
    $this->version = isset($settings->v) ? $settings->v : (isset($settings->version) ? $settings->version : null);
    $this->collectionPrefix = isset($settings->collectionPrefix) ? $settings->collectionPrefix : null;
    $this->isWebConnectivityEnabled = isset($settings->isWebConnectivityEnabled) ? $settings->isWebConnectivityEnabled : true;

    if (isset($settings->f) || isset($settings->features)) {
      $featureList = isset($settings->f) ? $settings->f : $settings->features;
      foreach ($featureList as $feature) {
        $this->features[] = (new FeatureModel())->modelFromDictionary($feature);
      }
    }
    
    if (isset($settings->c) || isset($settings->campaigns)) {
      $campaignList = isset($settings->c) ? $settings->c : $settings->campaigns;
      foreach ($campaignList as $campaign) {
        $this->campaigns[] = (new CampaignModel())->modelFromDictionary($campaign);
      }
    }
    
    $this->campaignGroups = isset($settings->cG) ? $settings->cG : (isset($settings->campaignGroups) ? $settings->campaignGroups : []);
    $this->groups = isset($settings->g) ? $settings->g : (isset($settings->groups) ? $settings->groups : []);
  }

  public function getFeatures() {
    return $this->features;
  }

  public function getCampaigns() {
    return $this->campaigns;
  }

  public function getSdkkey() {
    return $this->sdkKey;
  }

  public function getAccountId() {
    return $this->accountId;
  }

  public function getVersion() {
    return $this->version;
  }

  public function getCollectionPrefix() {
    return $this->collectionPrefix;
  }

  public function getCampaignGroups() {
    return $this->campaignGroups;
  }

  public function getGroups() {
    return $this->groups;
  }

  public function isWebConnectivityEnabled() {
    return $this->isWebConnectivityEnabled;
  }
  
  public function toArray(): array {
    return json_decode(json_encode($this), true);
  }
}
