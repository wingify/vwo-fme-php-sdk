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

namespace vwo\Packages\SegmentationEvaluator\Evaluators;

use vwo\Decorators\StorageDecorator;
use vwo\Models\SettingsModel;
use vwo\Models\User\ContextModel;
use vwo\Models\FeatureModel;
use vwo\Services\StorageService;
use vwo\Packages\Logger\Core\LogManager;
use vwo\Packages\SegmentationEvaluator\Enums\SegmentOperatorValueEnum;
use vwo\Packages\SegmentationEvaluator\Evaluators\SegmentOperandEvaluator;
use vwo\Utils\DataTypeUtil;

class SegmentEvaluator implements Segmentation
{
    public $context;
    public $settings;
    public $feature;

    public function __construct($settings = null, $context = null, $feature = null)
    {
        $this->settings = $settings;
        $this->context = $context;
        $this->feature = $feature;
    }

    public function isSegmentationValid($dsl, $properties): bool
    {
        $keyValue = $this->getKeyValue($dsl);
        $operator = $keyValue['key'];
        $subDsl = $keyValue['value'];

        switch ($operator) {
            case SegmentOperatorValueEnum::NOT:
                return !$this->isSegmentationValid($subDsl, $properties);
            case SegmentOperatorValueEnum::AND:
                return $this->every($subDsl, $properties);
            case SegmentOperatorValueEnum::OR:
                return $this->some($subDsl, $properties);
            case SegmentOperatorValueEnum::CUSTOM_VARIABLE:
                return (new SegmentOperandEvaluator())->evaluateCustomVariableDSL($subDsl, $properties);
            case SegmentOperatorValueEnum::USER:
                return (new SegmentOperandEvaluator())->evaluateUserDSL($subDsl, $properties);
            case SegmentOperatorValueEnum::UA:
                return (new SegmentOperandEvaluator())->evaluateUserAgentDSL($subDsl, $this->context);
            default:
                return false;
        }
    }

    public function some($dslNodes, $customVariables): bool
    {
        $uaParserMap = [];
        $keyCount = 0;
        $isUaParser = false;

        foreach ($dslNodes as $dsl) {
            foreach ($dsl as $key => $value) {
                if (in_array($key, [SegmentOperatorValueEnum::OPERATING_SYSTEM, SegmentOperatorValueEnum::BROWSER_AGENT, SegmentOperatorValueEnum::DEVICE_TYPE, SegmentOperatorValueEnum::DEVICE])) {
                    $isUaParser = true;
                    if (!isset($uaParserMap[$key])) {
                        $uaParserMap[$key] = [];
                    }
                    $valuesArray = is_array($value) ? $value : [$value];
                    foreach ($valuesArray as $val) {
                        if (is_string($val)) {
                            $uaParserMap[$key][] = $val;
                        }
                    }
                    $keyCount++;
                }

                if ($key === SegmentOperatorValueEnum::FEATURE_ID) {
                    $featureIdObject = $dsl->$key;

                    $featureIdKey = array_key_first(get_object_vars($featureIdObject));
                    $featureIdValue = $featureIdObject->$featureIdKey;

                    if (in_array($featureIdValue, ['on', 'off'])) {
                        $features = $this->settings->getFeatures();
                        $feature = null;
                        foreach ($features as $f) {
                            if ($f->getId() === (int)$featureIdKey) {
                                $feature = $f;
                                break;
                            }
                        }
                        if ($feature !== null) {
                            $featureKey = $feature->getKey();
                            $result = $this->checkInUserStorage($this->settings, $featureKey, $this->context);
                            if ($featureIdValue === 'off') {
                                return !$result;
                            }
                            return $result;
                        } else {
                            LogManager::instance()->error("Feature not found with featureIdKey: $featureIdKey");
                            return false;
                        }
                    }
                }
            }

            if ($isUaParser && $keyCount === count($dslNodes)) {
                try {
                    $uaParserResult = $this->checkUserAgentParser($uaParserMap);
                    return $uaParserResult;
                } catch (\Exception $err) {
                    LogManager::instance()->error($err->getMessage());
                }
            }

            if ($this->isSegmentationValid($dsl, $customVariables)) {
                return true;
            }
        }
        return false;
    }

    public function every($dslNodes, $customVariables): bool
    {
        $locationMap = [];
        foreach ($dslNodes as $dsl) {
            if (isset($dsl->{SegmentOperatorValueEnum::COUNTRY}) || isset($dsl->{SegmentOperatorValueEnum::REGION}) || isset($dsl->{SegmentOperatorValueEnum::CITY})) {
                $this->addLocationValuesToMap($dsl, $locationMap);
                if (count($locationMap) === count($dslNodes)) {
                    return $this->checkLocationPreSegmentation($locationMap);
                }
                continue;
            }
            if (!$this->isSegmentationValid($dsl, $customVariables)) {
                return false;
            }
        }
        return true;
    }

    public function addLocationValuesToMap($dsl, &$locationMap): void
    {
        if (isset($dsl->{SegmentOperatorValueEnum::COUNTRY})) {
            $locationMap[SegmentOperatorValueEnum::COUNTRY] = $dsl->{SegmentOperatorValueEnum::COUNTRY};
        }
        if (isset($dsl->{SegmentOperatorValueEnum::REGION})) {
            $locationMap[SegmentOperatorValueEnum::REGION] = $dsl->{SegmentOperatorValueEnum::REGION};
        }
        if (isset($dsl->{SegmentOperatorValueEnum::CITY})) {
            $locationMap[SegmentOperatorValueEnum::CITY] = $dsl->{SegmentOperatorValueEnum::CITY};
        }
    }

    public function checkLocationPreSegmentation($locationMap): bool
    {
        $ipAddress = $this->context->getIpAddress(); // Use the getter method

        if (empty($ipAddress)) {
            LogManager::instance()->info('To evaluate location pre-segmentation, please pass ipAddress in the context object');
            return false;
        }

        if (empty($this->context->getVwo()) || empty($this->context->getVwo()->getLocation())) {
            return false;
        }

        return $this->valuesMatch($locationMap, $this->context->getVwo()->getLocation());
    }

    public function checkUserAgentParser($uaParserMap): bool
    {
        $userAgent = $this->context->getUserAgent(); // Use the getter method

        if (empty($userAgent)) {
            LogManager::instance()->info('To evaluate user agent related segments, please pass userAgent in the context object');
            return false;
        }

        if (empty($this->context->getVwo()) || empty($this->context->getVwo()->getUaInfo())) {
            return false;
        }
        return $this->checkValuePresent($uaParserMap, $this->context->getVwo()->getUaInfo());
    }

    public function checkInUserStorage($settings, $featureKey, $user)
    {
        $storageService = new StorageService();
        $storedData = (new StorageDecorator())->getFeatureFromStorage($featureKey, $user, $storageService);

        return is_array($storedData) && count($storedData) > 0;
    }

    public function checkValuePresent($expectedMap, $actualMap): bool
    {
        foreach ($actualMap as $key => $value) {
            if (array_key_exists($key, $expectedMap)) {
                $expectedValues = array_map('strtolower', $expectedMap[$key]);
                $actualValue = strtolower($value);

                if ($key === SegmentOperatorValueEnum::DEVICE_TYPE) {
                    $wildcardPatterns = array_filter($expectedValues, function ($val) {
                        return strpos($val, 'wildcard(') === 0 && substr($val, -1) === ')';
                    });

                    if (!empty($wildcardPatterns)) {
                        foreach ($wildcardPatterns as $pattern) {
                            $wildcardPattern = substr($pattern, 9, -1);
                            $regex = '/^' . str_replace('*', '.*', preg_quote($wildcardPattern)) . '$/';
                            if (!preg_match($regex, $actualValue)) {
                                return false;
                            }
                        }
                    }
                } elseif (!in_array($actualValue, $expectedValues)) {
                    return false;
                }
            }
        }
        return true;
    }

    public function valuesMatch($expectedLocationMap, $userLocation): bool
    {
        foreach ($expectedLocationMap as $key => $value) {
            if (isset($userLocation->$key)) {
                $normalizedValue1 = $this->normalizeValue($value);
                $normalizedValue2 = $this->normalizeValue($userLocation->$key);
                if ($normalizedValue1 !== $normalizedValue2) {
                    return false;
                }
            } else {
                return false;
            }
        }
        return true;
    }

    public function normalizeValue($value)
    {
        if ($value === null || $value === 'undefined') {
            return null;
        }
        return trim($value, '"');
    }

    public static function getKeyValue($dsl)
    {
        if (!is_object($dsl)) {
            return null;
        }

        $dslArray = (array) $dsl;

        if (empty($dslArray)) {
            return null;
        }

        $keys = array_keys($dslArray);
        $key = $keys[0];
        $value = $dslArray[$key];

        return ['key' => $key, 'value' => $value];
    }
}

interface Segmentation
{
    public function isSegmentationValid($dsl, $properties);
}
?>
