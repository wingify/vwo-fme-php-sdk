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

namespace vwo\Packages\SegmentationEvaluator\Evaluators;

use vwo\Packages\SegmentationEvaluator\Enums\SegmentOperatorValueEnum;
use vwo\Packages\Logger\Core\LogManager;
use vwo\Utils\VWOGatewayServiceUtil;
use vwo\Enums\UrlEnum;
use vwo\Services\StorageService;
use vwo\Decorators\StorageDecorator;

class SegmentEvaluator implements Segmentation
{

    public function isSegmentationValid($dsl, $properties, $settings, $context = null)
    {
        $keyValue = $this->getKeyValue($dsl);
        $operator = $keyValue['key'];
        $subDsl = $keyValue['value'];

        if ($operator === SegmentOperatorValueEnum::NOT) {
            $result = $this->isSegmentationValid($subDsl, $properties, $settings, $context);
            return !$result;
        } elseif ($operator === SegmentOperatorValueEnum::AND) {
            return $this->every($subDsl, $properties, $settings, $context);
        } elseif ($operator === SegmentOperatorValueEnum::OR) {
            return $this->some($subDsl, $properties, $settings, $context);
        } elseif ($operator === SegmentOperatorValueEnum::CUSTOM_VARIABLE) {
            return (new SegmentOperandEvaluator())->evaluateCustomVariableDSL($subDsl, $properties);
        } elseif ($operator === SegmentOperatorValueEnum::USER) {
            return (new SegmentOperandEvaluator())->evaluateUserDSL($subDsl, $properties);
        } elseif ($operator === SegmentOperatorValueEnum::UA) {
            return (new SegmentOperandEvaluator())->evaluateUserAgentDSL($subDsl, $context);
        }
        return false;
    }

    public function some($dslNodes, $customVariables, $settings, $context): bool
    {
        $uaParserMap = [];
        $keyCount = 0;
        $isUaParser = false;

        foreach ($dslNodes as $dsl) {
            foreach ($dsl as $key => $value) {
                if ($key === SegmentOperatorValueEnum::OPERATING_SYSTEM || $key === SegmentOperatorValueEnum::BROWSER_AGENT || $key === SegmentOperatorValueEnum::DEVICE_TYPE) {
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
                    $featureIdKey = array_key_first($featureIdObject);
                    $featureIdValue = $featureIdObject->$featureIdKey;

                    if ($featureIdValue === 'on') {
                        $features = $settings->getFeatures();
                        $feature = null;
                        foreach ($features as $f) {
                            if ($f->getId() === (int)$featureIdKey) {
                                $feature = $f;
                                break;
                            }
                        }
                        if ($feature !== null) {
                            $featureKey = $feature->getKey();
                            $result = $this->checkInUserStorage($settings, $featureKey, $context);
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
                    if (!isset($context['userAgent']) || $context['userAgent'] === null) {
                        LogManager::instance()->error('To evaluate user agent related segments, please pass userAgent in context object');
                        return false;
                    }
                    $uaParserResult = $this->checkUserAgentParser($uaParserMap, $context['userAgent']);
                    return $uaParserResult;
                } catch (\Exception $err) {
                    LogManager::instance()->error($err->getMessage());
                }
            }

            if ($this->isSegmentationValid($dsl, $customVariables, $settings, $context)) {
                return true;
            }
        }
        return false;
    }

    public function every($dslNodes, $customVariables, $settings, $context): bool
    {
        $locationMap = [];
        foreach ($dslNodes as $dsl) {
            if (isset($dsl->{SegmentOperatorValueEnum::COUNTRY}) || isset($dsl->{SegmentOperatorValueEnum::REGION}) || isset($dsl->{SegmentOperatorValueEnum::CITY})) {
                $this->addLocationValuesToMap($dsl, $locationMap);
                if (count($locationMap) === count($dslNodes)) {
                    if (!isset($context['ipAddress']) || $context['ipAddress'] === null) {
                        LogManager::instance()->info('To evaluate location pre Segment, please pass ipAddress in context object');
                        return false;
                    }
                    $segmentResult = $this->checkLocationPreSegmentation($locationMap, $context['ipAddress']);
                    return $segmentResult;
                }
                continue;
            }
            $res = $this->isSegmentationValid($dsl, $customVariables, $settings, $context);
            if (!$res) {
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

    public function checkLocationPreSegmentation($locationMap, $ipAddress): bool
    {
        $queryParams = VWOGatewayServiceUtil::getQueryParamForLocationPreSegment($ipAddress);
        $userLocation = VWOGatewayServiceUtil::getFromVWOGatewayService($queryParams, UrlEnum::LOCATION_CHECK);
        if (!$userLocation || $userLocation === null || $userLocation === 'false') {
            return false;
        }
        return $this->valuesMatch($locationMap, $userLocation->location);
    }

    public function checkUserAgentParser($uaParserMap, $userAgent): bool
    {
        $queryParams = VWOGatewayServiceUtil::getQueryParamForUaParser($userAgent);
        $uaParser = VWOGatewayServiceUtil::getFromVWOGatewayService($queryParams, UrlEnum::UAPARSER);
        if (!$uaParser || $uaParser === null || $uaParser === 'false') {
            return false;
        }
        return $this->checkValuePresent($uaParserMap, $uaParser);
    }

    public function checkInUserStorage($settings, $featureKey, $user)
    {
        $storageService = new StorageService();
        $storedData = (new StorageDecorator())->getFeatureFromStorage($featureKey, $user, $storageService);

        if (count((array)$storedData) > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function checkValuePresent($expectedMap, $actualMap): bool
    {
        foreach ($actualMap as $key => $value) {
            if (array_key_exists($key, $expectedMap)) {
                $expectedValues = $expectedMap[$key];
                $actualValue = $value;

                if ($key === SegmentOperatorValueEnum::DEVICE_TYPE) {
                    $wildcardPatterns = array_filter($expectedValues, function ($val) {
                        return strpos($val, 'wildcard(') === 0 && substr($val, -1) === ')';
                    });

                    if (!empty($wildcardPatterns)) {
                        foreach ($wildcardPatterns as $pattern) {
                            $wildcardPattern = substr($pattern, 9, -1);
                            $regex = '/^' . str_replace('*', '.*', preg_quote($wildcardPattern)) . '$/';
                            if (preg_match($regex, $actualValue)) {
                                continue;
                            } else {
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
        // Check if the input is a valid object
        if (!is_object($dsl)) {
            return null;
        }

        $dslArray = (array) $dsl;

        // Check if the array is empty
        if (empty($dslArray)) {
            return null;
        }

        $keys = array_keys($dslArray);
        $key = $keys[0];
        $value = $dslArray[$key];

        return ['key' => $key, 'value' => $value];
    }

    public function isObject($value)
    {
        // Check if $value is an object
        return is_object($value);
    }
}

interface Segmentation
{
    public function isSegmentationValid($dsl, $properties, $settings, $context = null);
}
