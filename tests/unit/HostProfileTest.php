<?php

/**
 * Copyright 2024-2026 Wingify Software Pvt. Ltd.
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
use wingify\Constants\Constants;
use wingify\Enums\HostProfileEnum;

class HostProfileTest extends TestCase
{
    public function testVwoBuilderDefaultsToVwoHostProfile()
    {
        $builder = new VWOBuilder([
            'sdkKey' => 'test-key',
            'accountId' => '12345',
        ]);
        $builder->setLogger()->setSettingsService();

        $service = $builder->getSettingsService();
        $this->assertEquals(HostProfileEnum::VWO, $service->hostProfile);
        $this->assertEquals(Constants::LEGACY_HOST, $service->getSettingsHostname());
        $this->assertEquals(Constants::LEGACY_HOST, $service->getEventsHostname());
    }

    public function testVwoBuilderGatewayOverridesHosts()
    {
        $builder = new VWOBuilder([
            'sdkKey' => 'test-key',
            'accountId' => '12345',
            'gatewayService' => [
                'url' => 'https://gateway.example.com:8443',
            ],
        ]);
        $builder->setLogger()->setSettingsService();

        $service = $builder->getSettingsService();
        $this->assertEquals('gateway.example.com', $service->getSettingsHostname());
        $this->assertEquals('gateway.example.com', $service->getEventsHostname());
        $this->assertTrue($service->usesCustomerHostOverride());
    }

    public function testVwoBuilderProxyOverridesHosts()
    {
        $builder = new VWOBuilder([
            'sdkKey' => 'test-key',
            'accountId' => '12345',
            'proxy' => [
                'url' => 'https://proxy.customer.com',
            ],
        ]);
        $builder->setLogger()->setSettingsService();

        $service = $builder->getSettingsService();
        $this->assertEquals('proxy.customer.com', $service->getSettingsHostname());
        $this->assertEquals('proxy.customer.com', $service->getEventsHostname());
        $this->assertTrue($service->isProxyUrlProvided);
    }
}
