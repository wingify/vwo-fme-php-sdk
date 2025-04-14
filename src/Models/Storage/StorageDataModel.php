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

namespace vwo\Models\Storage;

use vwo\Models\User\ContextModel;

class StorageDataModel
{
    private $featureKey;
    private $context;
    private $rolloutId;
    private $rolloutKey;
    private $rolloutVariationId;
    private $experimentId;
    private $experimentKey;
    private $experimentVariationId;

    public function modelFromDictionary(StorageDataModel $storageData): self
    {
        $this->featureKey = $storageData->getFeatureKey();
        $this->context = $storageData->getContext();
        $this->rolloutId = $storageData->getRolloutId();
        $this->rolloutKey = $storageData->getRolloutKey();
        $this->rolloutVariationId = $storageData->getRolloutVariationId();
        $this->experimentId = $storageData->getExperimentId();
        $this->experimentKey = $storageData->getExperimentKey();
        $this->experimentVariationId = $storageData->getExperimentVariationId();
        return $this;
    }

    public function getFeatureKey(): string
    {
        return $this->featureKey;
    }

    public function getContext(): ContextModel
    {
        return $this->context;
    }

    public function getRolloutId(): int
    {
        return $this->rolloutId;
    }

    public function getRolloutKey(): string
    {
        return $this->rolloutKey;
    }

    public function getRolloutVariationId(): int
    {
        return $this->rolloutVariationId;
    }

    public function getExperimentId(): int
    {
        return $this->experimentId;
    }

    public function getExperimentKey(): string
    {
        return $this->experimentKey;
    }

    public function getExperimentVariationId(): int
    {
        return $this->experimentVariationId;
    }

    public function setFeatureKey(string $featureKey)
    {
        $this->featureKey = $featureKey;
    }

    public function setContext(ContextModel $context)
    {
        $this->context = $context;
    }

    public function setRolloutId(int $rolloutId)
    {
        $this->rolloutId = $rolloutId;
    }

    public function setRolloutKey(string $rolloutKey)
    {
        $this->rolloutKey = $rolloutKey;
    }

    public function setRolloutVariationId(int $rolloutVariationId)
    {
        $this->rolloutVariationId = $rolloutVariationId;
    }

    public function setExperimentId(int $experimentId)
    {
        $this->experimentId = $experimentId;
    }

    public function setExperimentKey(string $experimentKey)
    {
        $this->experimentKey = $experimentKey;
    }

    public function setExperimentVariationId(int $experimentVariationId)
    {
        $this->experimentVariationId = $experimentVariationId;
    }
}
