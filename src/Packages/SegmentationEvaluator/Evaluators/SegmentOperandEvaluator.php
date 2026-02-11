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

use vwo\Utils\GatewayServiceUtil;
use vwo\Enums\UrlEnum;
use vwo\Packages\SegmentationEvaluator\Enums\SegmentOperandRegexEnum;
use vwo\Packages\SegmentationEvaluator\Enums\SegmentOperandValueEnum;
use vwo\Packages\SegmentationEvaluator\Enums\SegmentOperatorValueEnum;

class SegmentOperandEvaluator {
    public $serviceContainer;

    public function __construct($serviceContainer) {
        $this->serviceContainer = $serviceContainer;
    }

    public function evaluateCustomVariableDSL($dslOperandValue, $properties) {
        $keyValue = SegmentEvaluator::getKeyValue($dslOperandValue);
        $operandKey = $keyValue['key'];
        $operand = $keyValue['value'];

        if (is_null($properties)) {
            return false;
        } else {
            if (is_array($properties)) {
                $properties = (object) $properties;
            }

            if (!property_exists($properties, $operandKey)) {
                return false;
            }
        }

        if (preg_match(SegmentOperandRegexEnum::IN_LIST, $operand)) {
            preg_match(SegmentOperandRegexEnum::IN_LIST, $operand, $matches);
            if (!$matches || count($matches) < 2) {
                $this->serviceContainer->getLogManager()->error('Invalid inList operand format');
                return false;
            }

            $tagValue = $properties->$operandKey;
            $attributeValue = $this->preProcessTagValue($tagValue);

            $listId = $matches[1];
            $queryParamsObj = (object)[
                'attribute' => $attributeValue,
                'listId' => $listId
            ];

            try {
                $res = GatewayServiceUtil::getFromGatewayService($this->serviceContainer, $queryParamsObj, UrlEnum::ATTRIBUTE_CHECK);
                if (!$res || $res === null || $res === 'false') {
                    return false;
                }
                return $res;
            } catch (\Exception $error) {
                $this->serviceContainer->getLogManager()->error('Error while fetching data:'. $error->getMessage());
                return false;
            }
        } else {
            $tagValue = $properties->$operandKey;
            $tagValue = $this->preProcessTagValue($tagValue);
            $operandTypeAndValue = $this->preProcessOperandValue($operand);
            $processedValues = $this->processValues($operandTypeAndValue->operandValue, $tagValue);
            $tagValue = $processedValues->tagValue;
            return $this->extractResult($operandTypeAndValue->operandType, $processedValues->operandValue, $tagValue);
        }
    }

    public function evaluateUserDSL($dslOperandValue, $properties) {
        $properties = json_decode(json_encode($properties),true);
        $users = explode(',', $dslOperandValue);
        foreach ($users as $user) {
            if (trim($user) === $properties['_vwoUserId']) {
                return true;
            }
        }
        return false;
    }

    public function evaluateUserAgentDSL($dslOperandValue, $context) {
        $operand = $dslOperandValue;
        if (!$context->getUserAgent() || $context->getUserAgent() === null) {
            $this->serviceContainer->getLogManager()->info('To evaluate UserAgent segmentation, please provide userAgent in context');
            return false;
        }
        $tagValue = urldecode($context->getUserAgent());
        $operandTypeAndValue = $this->preProcessOperandValue($operand);
        $processedValues = $this->processValues($operandTypeAndValue->operandValue, $tagValue);
        $tagValue = $processedValues->tagValue;
        return $this->extractResult($operandTypeAndValue->operandType, $processedValues->operandValue, $tagValue);
    }

    /**
     * Evaluates a given string tag value against a DSL operand value.
     * 
     * @param string $dslOperandValue The DSL operand string (e.g., "contains(\"value\")").
     * @param object $context The context object containing the value to evaluate.
     * @param string $operandType The type of operand being evaluated (ip_address, browser_version, os_version).
     * @return bool True if tag value matches DSL operand criteria, false otherwise.
     */
    public function evaluateStringOperandDSL($dslOperandValue, $context, $operandType = null) {
        $operand = $dslOperandValue;

        // Determine the tag value based on operand type
        $tagValue = $this->getTagValueForOperandType($context, $operandType);

        if ($tagValue === null) {
            $this->logMissingContextError($operandType);
            return false;
        }
        
        $operandTypeAndValue = $this->preProcessOperandValue($operand);
        $processedValues = $this->processValues($operandTypeAndValue->operandValue, $tagValue);
        $tagValue = $processedValues->tagValue;
        return $this->extractResult($operandTypeAndValue->operandType, $processedValues->operandValue, $tagValue);
    }

    public function preProcessTagValue($tagValue) {
        if ($tagValue === null) {
            $tagValue = '';
        }
        if (is_bool($tagValue)) {
            $tagValue = $tagValue ? 'true' : 'false';
        }
        if ($tagValue !== null) {
            $tagValue = strval($tagValue);
        }
        return $tagValue;
    }

    public function preProcessOperandValue($operand) {
        if (preg_match(SegmentOperandRegexEnum::LOWER_MATCH, $operand)) {
            $operandType = SegmentOperandValueEnum::LOWER_VALUE;
            $operandValue = $this->extractOperandValue($operand, SegmentOperandRegexEnum::LOWER_MATCH);
        } elseif (preg_match(SegmentOperandRegexEnum::WILDCARD_MATCH, $operand)) {
            $operandValue = $this->extractOperandValue($operand, SegmentOperandRegexEnum::WILDCARD_MATCH);
            $startingStar = preg_match(SegmentOperandRegexEnum::STARTING_STAR, $operandValue);
            $endingStar = preg_match(SegmentOperandRegexEnum::ENDING_STAR, $operandValue);
            if ($startingStar && $endingStar) {
                $operandType = SegmentOperandValueEnum::STARTING_ENDING_STAR_VALUE;
            } elseif ($startingStar) {
                $operandType = SegmentOperandValueEnum::STARTING_STAR_VALUE;
            } elseif ($endingStar) {
                $operandType = SegmentOperandValueEnum::ENDING_STAR_VALUE;
            }
            $operandValue = trim($operandValue, '*');
        } elseif (preg_match(SegmentOperandRegexEnum::REGEX_MATCH, $operand)) {
            $operandType = SegmentOperandValueEnum::REGEX_VALUE;
            $operandValue = $this->extractOperandValue($operand, SegmentOperandRegexEnum::REGEX_MATCH);
        } elseif (preg_match(SegmentOperandRegexEnum::GREATER_THAN, $operand)) {
            $operandType = SegmentOperandValueEnum::GREATER_THAN_VALUE;
            $operandValue = $this->extractOperandValue($operand, SegmentOperandRegexEnum::GREATER_THAN);
        } elseif (preg_match(SegmentOperandRegexEnum::GREATER_THAN_EQUAL_TO, $operand)) {
            $operandType = SegmentOperandValueEnum::GREATER_THAN_EQUAL_TO_VALUE;
            $operandValue = $this->extractOperandValue($operand, SegmentOperandRegexEnum::GREATER_THAN_EQUAL_TO);
        } elseif (preg_match(SegmentOperandRegexEnum::LESS_THAN, $operand)) {
            $operandType = SegmentOperandValueEnum::LESS_THAN_VALUE;
            $operandValue = $this->extractOperandValue($operand, SegmentOperandRegexEnum::LESS_THAN);
        } elseif (preg_match(SegmentOperandRegexEnum::LESS_THAN_EQUAL_TO, $operand)) {
            $operandType = SegmentOperandValueEnum::LESS_THAN_EQUAL_TO_VALUE;
            $operandValue = $this->extractOperandValue($operand, SegmentOperandRegexEnum::LESS_THAN_EQUAL_TO);
        } else {
            $operandType = SegmentOperandValueEnum::EQUAL_VALUE;
            $operandValue = $operand;
        }
        return (object)[
            'operandType' => $operandType,
            'operandValue' => $operandValue
        ];
    }

    public function extractOperandValue($operand, $regex) {
        preg_match($regex, $operand, $matches);
        return $matches[1];
    }

    public function processValues($operandValue, $tagValue) {
        $processedOperandValue = ($operandValue);
        $processedTagValue = ($tagValue);
        if (!$processedOperandValue || !$processedTagValue) {
            return (object)[
                'operandValue' => $operandValue,
                'tagValue' => $tagValue
            ];
        }
        return (object)[
            'operandValue' => strval($processedOperandValue),
            'tagValue' => strval($processedTagValue)
        ];
    }

    public function extractResult($operandType, $operandValue, $tagValue) {
        $result = false;

        $normalizedOperandValue = is_numeric($operandValue) ? rtrim(rtrim($operandValue, '0'), '.') : $operandValue;
        $normalizedTagValue = is_numeric($tagValue) ? rtrim(rtrim($tagValue, '0'), '.') : $tagValue;
    
        switch ($operandType) {
            case SegmentOperandValueEnum::LOWER_VALUE:
                if ($tagValue !== null) {
                    $result = strtolower($normalizedOperandValue) === strtolower($normalizedTagValue);
                }
                break;
            case SegmentOperandValueEnum::STARTING_ENDING_STAR_VALUE:
                if ($tagValue !== null) {
                    $result = strpos($normalizedTagValue, $normalizedOperandValue) !== false;
                }
                break;
            case SegmentOperandValueEnum::STARTING_STAR_VALUE:
                if ($tagValue !== null) {
                    $result = substr($normalizedTagValue, -strlen($normalizedOperandValue)) === $normalizedOperandValue;
                }
                break;
            case SegmentOperandValueEnum::ENDING_STAR_VALUE:
                if ($tagValue !== null) {
                    $result = substr($normalizedTagValue, 0, strlen($normalizedOperandValue)) === $normalizedOperandValue;
                }
                break;
            case SegmentOperandValueEnum::REGEX_VALUE:
                if (@preg_match('/' . $operandValue . '/', '') === false) {
                    $result = false;
                } else {
                    $result = preg_match('/' . $operandValue . '/', $tagValue);
                }
                break; 
            case SegmentOperandValueEnum::EQUAL_VALUE:
                if (is_numeric($operandValue) && is_numeric($tagValue)) {
                    $result = (float)$operandValue === (float)$tagValue;
                } else {
                    $result = $normalizedOperandValue === $normalizedTagValue;
                }
                break;
            case SegmentOperandValueEnum::GREATER_THAN_VALUE:
                $result = $this->compareValues($tagValue, strval($operandValue)) > 0;
                break;
            case SegmentOperandValueEnum::GREATER_THAN_EQUAL_TO_VALUE:
                $result = $this->compareValues($tagValue, strval($operandValue)) >= 0;
                break;
            case SegmentOperandValueEnum::LESS_THAN_VALUE:
                $result = $this->compareValues($tagValue, strval($operandValue)) < 0;
                break;  
            case SegmentOperandValueEnum::LESS_THAN_EQUAL_TO_VALUE:
                $result = $this->compareValues($tagValue, strval($operandValue)) <= 0;
                break;   
            default:
                $result = $tagValue === strval($operandValue);
                break;
        }
    
        return $result;
    }



    /**
     * Compares two values.
     * Supports formats like "1.2.3", "1.0", "2.1.4.5", etc.
     * 
     * @param string $value1 First value
     * @param string $value2 Second value
     * @return int -1 if value1 < value2, 0 if equal, 1 if value1 > value2
     */
    private function compareValues($value1, $value2) {
        // Split values by dots and convert to integers
        $parts1 = array_map(function($part) { 
            return is_numeric($part) ? intval($part) : 0; 
        }, explode('.', $value1));
        
        $parts2 = array_map(function($part) { 
            return is_numeric($part) ? intval($part) : 0; 
        }, explode('.', $value2));

        // Find the maximum length to handle different value formats
        $maxLength = max(count($parts1), count($parts2));

        for ($i = 0; $i < $maxLength; $i++) {
            $part1 = $i < count($parts1) ? $parts1[$i] : 0;
            $part2 = $i < count($parts2) ? $parts2[$i] : 0;

            if ($part1 < $part2) {
                return -1;
            } else if ($part1 > $part2) {
                return 1;
            }
        }
        return 0; // Values are equal
    }

    /**
     * Gets the appropriate tag value based on the operand type.
     * 
     * @param object $context The context object.
     * @param string $operandType The type of operand.
     * @return string|null The tag value or null if not available.
     */
    private function getTagValueForOperandType($context, $operandType) {
        switch ($operandType) {
            case SegmentOperatorValueEnum::IP:
                return $context->getIpAddress();
            case SegmentOperatorValueEnum::BROWSER_VERSION:
                return $this->getBrowserVersionFromContext($context);
            default:
                // Default works for OS version
                return $this->getOsVersionFromContext($context);
        }
    }

    /**
     * Gets browser version from context.
     * 
     * @param object $context The context object.
     * @return string|null The browser version or null if not available.
     */
    private function getBrowserVersionFromContext($context) {
        if (empty($context->getVwo()) || empty($context->getVwo()->getUaInfo())) {
            return null;
        }
        
        $uaInfo = $context->getVwo()->getUaInfo();
        return isset($uaInfo->browser_version) ? $uaInfo->browser_version : null;
    }

    /**
     * Gets OS version from context.
     * 
     * @param object $context The context object.
     * @return string|null The OS version or null if not available.
     */
    private function getOsVersionFromContext($context) {
        if (empty($context->getVwo()) || empty($context->getVwo()->getUaInfo())) {
            return null;
        }
        
        $uaInfo = $context->getVwo()->getUaInfo();
        return isset($uaInfo->os_version) ? $uaInfo->os_version : null;
    }

    /**
     * Logs appropriate error message for missing context.
     * 
     * @param string $operandType The type of operand.
     */
    private function logMissingContextError($operandType) {
        switch ($operandType) {
            case SegmentOperatorValueEnum::IP:
                $this->serviceContainer->getLogManager()->info('To evaluate IP segmentation, please provide ipAddress in context');
                break;
            case SegmentOperatorValueEnum::BROWSER_VERSION:
                $this->serviceContainer->getLogManager()->info('To evaluate browser version segmentation, please provide userAgent in context');
                break;
            default:
                $this->serviceContainer->getLogManager()->info('To evaluate OS version segmentation, please provide userAgent in context');
                break;
        }
    }
}

?>