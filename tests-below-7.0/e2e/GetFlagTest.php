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

class GetFlagTest extends TestCase
{
    protected $testsData;
    protected $getFlagTests;

    protected function setUp()
    {
        // Initialize data
        $data = SettingsAndTestCases::get();
        $this->testsData = $data;
        $this->getFlagTests = $data['GETFLAG_TESTS'];
    }

    public function testGetFlagWithoutStorage()
    {
        $this->runTests($this->getFlagTests['GETFLAG_WITHOUT_STORAGE']);
    }

    public function testGetFlagWithMEGRandomAlgo()
    {
        $this->runTests($this->getFlagTests['GETFLAG_MEG_RANDOM']);
    }

    public function testGetFlagWithMEGAdvanceAlgo()
    {
        $this->runTests($this->getFlagTests['GETFLAG_MEG_ADVANCE']);
    }

    public function testGetFlagWithStorage()
    {
        $this->runTests($this->getFlagTests['GETFLAG_WITH_STORAGE'], new TestStorageService());
    }

    protected function runTests($tests, $storageMap = null)
    {
        foreach ($tests as $testData) {
            $this->runSingleTest($testData, $storageMap);
        }
    }

    protected function runSingleTest($testData, $storageMap = null)
    {
        $vwoOptions = [
            'accountId' => '123456',
            'sdkKey' => 'abcdef',
        ];

        if ($storageMap !== null) {
            $vwoOptions['storage'] = $storageMap;
        }

        $vwoBuilder = new VWOBuilder($vwoOptions);
        $vwoBuilder->setLogger();
        $settingsFile = $this->testsData[$testData['settings']];
        $vwoBuilder->setSettings($settingsFile);

        $options = [
            'sdkKey' => 'sdk-key',
            'accountId' => 'account-id',
            'vwoBuilder' => $vwoBuilder, // pass only for E2E tests
        ];

        $vwoClient = VWO::init($options);

        if ($storageMap !== null) {
            $storageData = $storageMap->get($testData['featureKey'], $testData['context']['id']);
            if ($storageData === null) {
                $this->assertNull($storageData);
            } else {
                $this->assertNull($storageData['rolloutKey']);
                $this->assertNull($storageData['rolloutVariationId']);
                $this->assertNull($storageData['experimentKey']);
                $this->assertNull($storageData['experimentVariationId']);
            }
        }

        $featureFlag = $vwoClient->getFlag($testData['featureKey'], $testData['context']);

        $this->assertEquals($testData['expectation']['isEnabled'], $featureFlag->isEnabled());
        $this->assertEquals($testData['expectation']['intVariable'], $featureFlag->getVariable('int', 1));
        $this->assertEquals($testData['expectation']['stringVariable'], $featureFlag->getVariable('string', 'VWO'));
        $this->assertEquals($testData['expectation']['floatVariable'], $featureFlag->getVariable('float', 1.1));
        $this->assertEquals($testData['expectation']['booleanVariable'], $featureFlag->getVariable('boolean', false));
        $this->assertEquals($testData['expectation']['jsonVariable'], json_decode(json_encode($featureFlag->getVariable('json', [])), true));

        if ($storageMap !== null) {
            $storageData = $storageMap->get($testData['featureKey'], $testData['context']['id']);
            if ($storageData !== null) {
                if (isset($testData['expectation']['storageData']['rolloutKey'])) {
                    $this->assertEquals($testData['expectation']['storageData']['rolloutKey'], $storageData['rolloutKey']);
                }
                if (isset($testData['expectation']['storageData']['rolloutVariationId'])) {
                    $this->assertEquals($testData['expectation']['storageData']['rolloutVariationId'], $storageData['rolloutVariationId']);
                }
                if (isset($testData['expectation']['storageData']['experimentKey'])) {
                    $this->assertEquals($testData['expectation']['storageData']['experimentKey'], $storageData['experimentKey']);
                }
                if (isset($testData['expectation']['storageData']['experimentVariationId'])) {
                    $this->assertEquals($testData['expectation']['storageData']['experimentVariationId'], $storageData['experimentVariationId']);
                }
            }
        }
    }
}
class TestStorageService
{
    private $map = [];

    public function get($featureKey, $userId)
    {
        $key = $featureKey . '_' . $userId;
        //echo 'Stored data: ' . $key . "\n";
        return isset($this->map[$key]) ? $this->map[$key] : null;
    }

    public function set($data)
    {
        $key = $data['featureKey'] . '_' . $data['user'];
        //echo 'Data to store: ' . json_encode($data) . "\n";

        $this->map[$key] = [
            'rolloutKey' => $data['rolloutKey'],
            'rolloutVariationId' => $data['rolloutVariationId'],
            'experimentKey' => $data['experimentKey'],
            'experimentVariationId' => $data['experimentVariationId']
        ];
        //dump("data in set", $this->map[$key]);
        return true;
    }
}