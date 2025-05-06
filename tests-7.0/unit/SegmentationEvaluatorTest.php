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

namespace vwo;

use PHPUnit\Framework\TestCase;
use vwo\Packages\SegmentationEvaluator\Evaluators\SegmentEvaluator;

class SegmentationEvaluatorTest extends TestCase
{
    protected $testsData;
    protected $settings;

    protected function setUp()
    {
        // Initialize data
        $data = SettingsAndTestCases::get();
        $this->testsData = $data['SEGMENTATION_TESTS'];
        $this->settings = $data['BASIC_ROLLOUT_SETTINGS'];
    }

    public function testAndOperator()
    {
        $andOperatorDsl = json_decode(json_encode($this->testsData['and_operator']), false);
        
        foreach ($andOperatorDsl as $value) {
            $dsl = $value->dsl;
            
            $expectation = $value->expectation;
            
            $customVariables = $value->customVariables;
            
            $segmentEvaluator = new SegmentEvaluator();

            $preSegmentationResult = $segmentEvaluator->isSegmentationValid($dsl, $customVariables, $this->settings);

            $this->assertEquals($expectation, $preSegmentationResult);
            
        }
    }

    public function testCaseInsensitiveEqualityOperand()
    {
        $caseInsensitiveEqualityOperandDsl = json_decode(json_encode($this->testsData['case_insensitive_equality_operand']), false);
        
        foreach ($caseInsensitiveEqualityOperandDsl as $value) {
            $dsl = $value->dsl;
            
            $expectation = $value->expectation;
            
            $customVariables = $value->customVariables;
            
            $segmentEvaluator = new SegmentEvaluator();

            $preSegmentationResult = $segmentEvaluator->isSegmentationValid($dsl, $customVariables, $this->settings);

            $this->assertEquals($expectation, $preSegmentationResult);
            
        }
    }

    public function testComplexAndOrs()
    {
        $complexAndOrsDsl = json_decode(json_encode($this->testsData['complex_and_ors']), false);
        
        foreach ($complexAndOrsDsl as $value) {
            $dsl = $value->dsl;
            
            $expectation = $value->expectation;
            
            $customVariables = $value->customVariables;
            
            $segmentEvaluator = new SegmentEvaluator();

            $preSegmentationResult = $segmentEvaluator->isSegmentationValid($dsl, $customVariables, $this->settings);

            $this->assertEquals($expectation, $preSegmentationResult);
            
        }
    }

    public function testComplexDsl()
    {
        $complexDslKeys = ['complex_dsl_1', 'complex_dsl_2', 'complex_dsl_3', 'complex_dsl_4'];
        
        foreach ($complexDslKeys as $dslKey) {
            $complexDsl = json_decode(json_encode($this->testsData[$dslKey]), false);
            
            foreach ($complexDsl as $value) {
                $dsl = $value->dsl;

                $expectation = $value->expectation;
                
                $customVariables = $value->customVariables;
                
                $segmentEvaluator = new SegmentEvaluator();

                $preSegmentationResult = $segmentEvaluator->isSegmentationValid($dsl, $customVariables, $this->settings);

                $this->assertEquals($expectation, $preSegmentationResult);
                
            }
        }
    }

    public function testContainsOperand()
    {
        $containsOperandDsl = json_decode(json_encode($this->testsData['contains_operand']), false);
        
        foreach ($containsOperandDsl as $value) {
            $dsl = $value->dsl;
            
            $expectation = $value->expectation;
            
            $customVariables = $value->customVariables;
            
            $segmentEvaluator = new SegmentEvaluator();

            $preSegmentationResult = $segmentEvaluator->isSegmentationValid($dsl, $customVariables, $this->settings);

            $this->assertEquals($expectation, $preSegmentationResult);
            
        }
    }

    public function testEndsOperand()
    {
        $endsWithOperandDsl = json_decode(json_encode($this->testsData['ends_with_operand']), false);
        
        foreach ($endsWithOperandDsl as $value) {
            $dsl = $value->dsl;
            
            $expectation = $value->expectation;
            
            $customVariables = $value->customVariables;
            
            $segmentEvaluator = new SegmentEvaluator();

            $preSegmentationResult = $segmentEvaluator->isSegmentationValid($dsl, $customVariables, $this->settings);

            $this->assertEquals($expectation, $preSegmentationResult);
            
        }
    }

    public function testEqualityOperand()
    {
        $equalityOperandDsl = json_decode(json_encode($this->testsData['equality_operand']), false);
        
        foreach ($equalityOperandDsl as $value) {
            $dsl = $value->dsl;
            
            $expectation = $value->expectation;
            
            $customVariables = $value->customVariables;
            
            $segmentEvaluator = new SegmentEvaluator();

            $preSegmentationResult = $segmentEvaluator->isSegmentationValid($dsl, $customVariables, $this->settings);

            $this->assertEquals($expectation, $preSegmentationResult);
            
        }
    }

    public function testDecimalMismatch()
    {
        $decimalMismatchDsl = json_decode(json_encode($this->testsData['new_cases_for_decimal_mismatch']), false);
        
        foreach ($decimalMismatchDsl as $value) {
            $dsl = $value->dsl;
            
            $expectation = $value->expectation;
            
            $customVariables = $value->customVariables;
            
            $segmentEvaluator = new SegmentEvaluator();

            $preSegmentationResult = $segmentEvaluator->isSegmentationValid($dsl, $customVariables, $this->settings);

            $this->assertEquals($expectation, $preSegmentationResult);
            
        }
    }

    public function testNotOperator()
    {
        $notOperatorDsl = json_decode(json_encode($this->testsData['not_operator']), false);
        
        foreach ($notOperatorDsl as $value) {
            $dsl = $value->dsl;
            
            $expectation = $value->expectation;
            
            $customVariables = $value->customVariables;
            
            $segmentEvaluator = new SegmentEvaluator();

            $preSegmentationResult = $segmentEvaluator->isSegmentationValid($dsl, $customVariables, $this->settings);

            $this->assertEquals($expectation, $preSegmentationResult);
            
        }
    }

    public function testOrOperator()
    {
        $orOperatorDsl = json_decode(json_encode($this->testsData['or_operator']), false);
        
        foreach ($orOperatorDsl as $value) {
            $dsl = $value->dsl;
            
            $expectation = $value->expectation;
            
            $customVariables = $value->customVariables;
            
            $segmentEvaluator = new SegmentEvaluator();

            $preSegmentationResult = $segmentEvaluator->isSegmentationValid($dsl, $customVariables, $this->settings);
            $this->assertEquals($expectation, $preSegmentationResult);
            
        }
    }
    
    public function testRegex()
    {
        $regexDsl = json_decode(json_encode($this->testsData['regex']), false);
        
        foreach ($regexDsl as $value) {
            $dsl = $value->dsl;
            
            $expectation = $value->expectation;
            
            $customVariables = $value->customVariables;
            
            $segmentEvaluator = new SegmentEvaluator();
    
            $preSegmentationResult = $segmentEvaluator->isSegmentationValid($dsl, $customVariables, $this->settings);
    
            $this->assertEquals($expectation, $preSegmentationResult);
            
        }
    }
    
    public function testSimpleAndOrs()
    {
        $simpleAndOrsDsl = json_decode(json_encode($this->testsData['simple_and_ors']), false);
        
        foreach ($simpleAndOrsDsl as $value) {
            $dsl = $value->dsl;
            
            $expectation = $value->expectation;
            
            $customVariables = $value->customVariables;
            
            $segmentEvaluator = new SegmentEvaluator();
    
            $preSegmentationResult = $segmentEvaluator->isSegmentationValid($dsl, $customVariables, $this->settings);
    
            $this->assertEquals($expectation, $preSegmentationResult);
            
        }
    }
    
    public function testStartsWithOperand()
    {
        $startsWithOperandDsl = json_decode(json_encode($this->testsData['starts_with_operand']), false);
        
        foreach ($startsWithOperandDsl as $value) {
            $dsl = $value->dsl;
            
            $expectation = $value->expectation;
            
            $customVariables = $value->customVariables;
            
            $segmentEvaluator = new SegmentEvaluator();
    
            $preSegmentationResult = $segmentEvaluator->isSegmentationValid($dsl, $customVariables, $this->settings);
    
            $this->assertEquals($expectation, $preSegmentationResult);
            
        }
    }
    
    public function testSpecialCharacters()
    {
        $specialCharactersDsl = json_decode(json_encode($this->testsData['special_characters']), false);
        
        foreach ($specialCharactersDsl as $value) {
            $dsl = $value->dsl;         
            $expectation = $value->expectation;          
            $customVariables = $value->customVariables;          
            $segmentEvaluator = new SegmentEvaluator();  
            $preSegmentationResult = $segmentEvaluator->isSegmentationValid($dsl, $customVariables, $this->settings);
             
            $this->assertEquals($expectation, $preSegmentationResult);
            
        }
    }
    
    public function testUserOperandEvaluator()
    {
        $userOperandEvaluatorDsl = json_decode(json_encode($this->testsData['user_operand_evaluator']), false);
        
        foreach ($userOperandEvaluatorDsl as $value) {
            $dsl = $value->dsl;          
            $expectation = $value->expectation;        
            $customVariables = $value->customVariables;        
            $segmentEvaluator = new SegmentEvaluator();  

            $preSegmentationResult = $segmentEvaluator->isSegmentationValid($dsl, $customVariables, $this->settings);
    
            $this->assertEquals($expectation, $preSegmentationResult);
            
        }
    }
    
    public function testUserOperandEvaluatorWithCustomVariables()
    {
        $userOperandEvaluatorWithCustomVariablesDsl = json_decode(json_encode($this->testsData['user_operand_evaluator_with_customVariables']), false);
        
        foreach ($userOperandEvaluatorWithCustomVariablesDsl as $value) {
            $dsl = $value->dsl;
            
            $expectation = $value->expectation;
            
            $customVariables = $value->customVariables;
            
            $segmentEvaluator = new SegmentEvaluator();
    
            $preSegmentationResult = $segmentEvaluator->isSegmentationValid($dsl, $customVariables, $this->settings);
    
            $this->assertEquals($expectation, $preSegmentationResult);
            
        }
    }   
}