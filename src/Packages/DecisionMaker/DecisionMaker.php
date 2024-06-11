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

namespace vwo\Packages\DecisionMaker;

use lastguest\Murmur;
use Psr\Log\LoggerInterface;

class DecisionMaker {
    const SEED_VALUE = 1;
    const MAX_TRAFFIC_VALUE = 10000;

    public function generateBucketValue($hashValue, $maxValue, $multiplier = 1): int {
        $ratio = $hashValue / pow(2, 32);
        $multipliedValue = ($maxValue * $ratio + 1) * $multiplier;
        $value = floor($multipliedValue);

        return $value;
    }

    public function getBucketValueForUser($hashKey, $maxValue = 100): int {
        $hashValue = Murmur::hash3_int($hashKey, self::SEED_VALUE);
        $bucketValue = $this->generateBucketValue($hashValue, $maxValue);
        return $bucketValue;
    }

    public function calculateBucketValue($str, $multiplier = 1, $maxValue = 10000): int {
        $hashValue = $this->generateHashValue($str);

        return $this->generateBucketValue($hashValue, $maxValue, $multiplier);
    }

    public function generateHashValue($hashKey) {
        return Murmur::hash3_int($hashKey, self::SEED_VALUE);
    }
}

?>
