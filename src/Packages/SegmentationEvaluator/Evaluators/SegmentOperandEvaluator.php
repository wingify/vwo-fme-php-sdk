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
use vwo\Packages\Logger\Core\LogManager;

class SegmentOperandEvaluator {

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
                LogManager::instance()->error('Invalid inList operand format');
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
                $res = GatewayServiceUtil::getFromGatewayService($queryParamsObj, UrlEnum::ATTRIBUTE_CHECK);
                if (!$res || $res === null || $res === 'false') {
                    return false;
                }
                return $res;
            } catch (\Exception $error) {
                LogManager::instance()->error('Error while fetching data:'. $error->getMessage());
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
            LogManager::instance()->info('To evaluate UserAgent segmentation, please provide userAgent in context');
            return false;
        }
        $tagValue = urldecode($context->getUserAgent());
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
                $result = $this->isValidNumericComparison($operandValue, $tagValue, function ($opValue, $tValue) {
                    return $opValue < $tValue;
                });
                break;
            case SegmentOperandValueEnum::GREATER_THAN_EQUAL_TO_VALUE:
                $result = $this->isValidNumericComparison($operandValue, $tagValue, function ($opValue, $tValue) {
                    return $opValue <= $tValue;
                });
                break;
            case SegmentOperandValueEnum::LESS_THAN_VALUE:
                $result = $this->isValidNumericComparison($operandValue, $tagValue, function ($opValue, $tValue) {
                    return $opValue > $tValue;
                });
                break;  
            case SegmentOperandValueEnum::LESS_THAN_EQUAL_TO_VALUE:
                $result = $this->isValidNumericComparison($operandValue, $tagValue, function ($opValue, $tValue) {
                    return $opValue >= $tValue;
                });
                break;   
            default:
                $result = false;
                break;
        }
    
        return $result;
    }

    // Function for numeric comparison
    private function isValidNumericComparison($operandValue, $tagValue, callable $comparison) {
        if ($tagValue !== null && is_numeric($operandValue) && is_numeric($tagValue)) {
            try {
                return $comparison(floatval($operandValue), floatval($tagValue));
            } catch (\Exception $e) {
                return false;
            }
        }
        return false;
    }
}

?>