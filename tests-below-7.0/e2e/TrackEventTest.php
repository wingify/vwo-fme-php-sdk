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
use vwo\VWOBuilder;
use vwo\VWO;
use vwo\SettingsAndTestCases;

class TrackEventTest extends TestCase
{
    protected $options;

    protected function setUp()
    {
        $vwoBuilder = new VWOBuilder([
            'accountId' => '123456',
            'sdkKey' => 'abcdef'
        ]);
        $vwoBuilder->setLogger();
        $vwoBuilder->setSettings(SettingsAndTestCases::get()['BASIC_ROLLOUT_SETTINGS']);

        $this->options = [
            'sdkKey' => 'sdk-key',
            'accountId' => 'account-id',
            'vwoBuilder' => $vwoBuilder, // pass only for E2E tests
        ];
    }


    public function testTrackEventSuccessfully()
    {
        $vwoClient = VWO::init($this->options);

        // Mock input data
        $eventName = 'custom1';
        $eventProperties = ['key' => 'value'];
        $context = ['id' => '123'];

        // Call the trackEvent method
        $result = $vwoClient->trackEvent($eventName, $context, $eventProperties);

        // Assert that the method resolves with the correct data
        $this->assertEquals([$eventName => true], $result);
    }

    public function testTrackEventWithoutMetric()
    {
        $vwoClient = VWO::init($this->options);

        // Mock input data
        $eventName = 'testEvent';
        $eventProperties = ['key' => 'value'];
        $context = ['id' => '123'];

        // Call the trackEvent method
        $result = $vwoClient->trackEvent($eventName, $context, $eventProperties);

        // Assert that the method resolves with the correct data
        $this->assertEquals([$eventName => false], $result);
    }

    public function testTrackEventWithInvalidEventName()
    {
        $vwoClient = VWO::init($this->options);

        // Mock input data with invalid eventName
        $eventName = 123; // Invalid eventName
        $eventProperties = ['key' => 'value'];
        $context = ['id' => '123'];

        // Call the trackEvent method
        $result = $vwoClient->trackEvent($eventName, $context, $eventProperties);

        // Assert that the method resolves with the correct data
        $this->assertEquals([$eventName => false], $result);
    }

    public function testTrackEventWithInvalidEventProperties()
    {
        $vwoClient = VWO::init($this->options);

        // Mock input data with invalid eventProperties
        $eventName = 'testEvent';
        $eventProperties = 'invalid'; // Invalid eventProperties
        $context = ['id' => '123'];

        // Call the trackEvent method
        $result = $vwoClient->trackEvent($eventName, $context, $eventProperties);

        // Assert that the method resolves with the correct data
        $this->assertEquals([$eventName => false], $result);
    }

    public function testTrackEventWithInvalidContext()
    {
        $vwoClient = VWO::init($this->options);

        // Mock input data with invalid context
        $eventName = 'testEvent';
        $eventProperties = ['key' => 'value'];
        $context = []; // Invalid context without userId

        // Call the trackEvent method
        $result = $vwoClient->trackEvent($eventName, $context, $eventProperties);

        // Assert that the method resolves with the correct data
        $this->assertEquals([$eventName => false], $result);
    }
}

?>
