<?php

/**
 * Copyright 2024-202 Wingify Software Pvt. Ltd.
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

class CustomBucketingTest extends TestCase
{
    private static $MOCK_SETTINGS_FILE;
    private static $SETTINGS_WITH_SAME_SALT;

    public static function setUpBeforeClass(): void
    {
        self::$MOCK_SETTINGS_FILE = json_decode(json_encode([
            "version" => 1,
            "sdkKey" => "abcdef",
            "accountId" => 123456,
            "campaigns" => [
                [
                    "segments" => new \stdClass(),
                    "status" => "RUNNING",
                    "variations" => [
                        [
                            "weight" => 100,
                            "segments" => new \stdClass(),
                            "id" => 1,
                            "variables" => [
                                [
                                    "id" => 1,
                                    "type" => "string",
                                    "value" => "def",
                                    "key" => "kaus"
                                ]
                            ],
                            "name" => "Rollout-rule-1"
                        ]
                    ],
                    "type" => "FLAG_ROLLOUT",
                    "isAlwaysCheckSegment" => false,
                    "isForcedVariationEnabled" => false,
                    "name" => "featureOne : Rollout",
                    "key" => "featureOne_rolloutRule1",
                    "id" => 1
                ],
                [
                    "segments" => new \stdClass(),
                    "status" => "RUNNING",
                    "key" => "featureOne_testingRule1",
                    "type" => "FLAG_TESTING",
                    "isAlwaysCheckSegment" => false,
                    "name" => "featureOne : Testing rule 1",
                    "isForcedVariationEnabled" => true,
                    "variations" => [
                        [
                            "weight" => 50,
                            "segments" => new \stdClass(),
                            "id" => 1,
                            "variables" => [
                                [
                                    "id" => 1,
                                    "type" => "string",
                                    "value" => "def",
                                    "key" => "kaus"
                                ]
                            ],
                            "name" => "Default"
                        ],
                        [
                            "weight" => 50,
                            "segments" => new \stdClass(),
                            "id" => 2,
                            "variables" => [
                                [
                                    "id" => 1,
                                    "type" => "string",
                                    "value" => "var1",
                                    "key" => "kaus"
                                ]
                            ],
                            "name" => "Variation-1"
                        ],
                        [
                            "weight" => 0,
                            "segments" => [
                                "or" => [
                                    [
                                        "user" => "forcedWingify"
                                    ]
                                ]
                            ],
                            "id" => 3,
                            "variables" => [
                                [
                                    "id" => 1,
                                    "type" => "string",
                                    "value" => "var2",
                                    "key" => "kaus"
                                ]
                            ],
                            "name" => "Variation-2"
                        ],
                        [
                            "weight" => 0,
                            "segments" => new \stdClass(),
                            "id" => 4,
                            "variables" => [
                                [
                                    "id" => 1,
                                    "type" => "string",
                                    "value" => "var3",
                                    "key" => "kaus"
                                ]
                            ],
                            "name" => "Variation-3"
                        ]
                    ],
                    "id" => 2,
                    "percentTraffic" => 100
                ]
            ],
            "features" => [
                [
                    "impactCampaign" => new \stdClass(),
                    "rules" => [
                        [
                            "campaignId" => 1,
                            "type" => "FLAG_ROLLOUT",
                            "ruleKey" => "rolloutRule1",
                            "variationId" => 1
                        ],
                        [
                            "type" => "FLAG_TESTING",
                            "ruleKey" => "testingRule1",
                            "campaignId" => 2
                        ]
                    ],
                    "status" => "ON",
                    "key" => "featureOne",
                    "metrics" => [
                        [
                            "type" => "CUSTOM_GOAL",
                            "identifier" => "e1",
                            "id" => 1
                        ]
                    ],
                    "type" => "FEATURE_FLAG",
                    "name" => "featureOne",
                    "id" => 1
                ]
            ]
        ]));

        // Create SETTINGS_WITH_SAME_SALT
        $settingsSameSalt = json_decode(json_encode(self::$MOCK_SETTINGS_FILE), true);
        
        // Campaign 2 acts as reference. Let's create Campaign 3 with identical structure, same salt
        $campaign3 = $settingsSameSalt['campaigns'][1];
        $campaign3['id'] = 3;
        $campaign3['key'] = "featureTwo_testingRule1";
        $campaign3['name'] = "featureTwo : Testing rule 1";
        
        // Ensure both campaigns have identical salt
        $campaign3['salt'] = 'samesalt';
        $settingsSameSalt['campaigns'][1]['salt'] = 'samesalt';
        $settingsSameSalt['campaigns'][] = $campaign3;

        // Duplicate featureOne into featureTwo
        $featureTwo = $settingsSameSalt['features'][0];
        $featureTwo['id'] = 2;
        $featureTwo['key'] = 'featureTwo';
        $featureTwo['name'] = 'featureTwo';
        $featureTwo['rules'][1]['campaignId'] = 3; // point testing rule to campaign 3
        
        $settingsSameSalt['features'][] = $featureTwo;
        self::$SETTINGS_WITH_SAME_SALT = json_decode(json_encode($settingsSameSalt));
    }

    /**
     * Helper method to create a VWO client with the mock settings.
     */
    private function createVWOClient()
    {
        $vwoOptions = [
            'accountId' => '123456',
            'sdkKey' => 'abcdef',
        ];

        $vwoBuilder = new VWOBuilder($vwoOptions);
        $vwoBuilder->setLogger();
        $vwoBuilder->setSettings(self::$MOCK_SETTINGS_FILE);

        $options = [
            'sdkKey' => 'sdk-key',
            'accountId' => 'account-id',
            'vwoBuilder' => $vwoBuilder,
        ];

        return VWO::init($options);
    }

    /**
     * Case 1: Standard bucketing (no custom seed)
     * Scenario: Two different users ('KaustubhVWO', 'RandomUserVWO') with NO bucketing seed.
     * Expected: They should be bucketed into different variations based on their User IDs.
     */
    public function testShouldAssignDifferentVariationsToUsersWithDifferentUserIds()
    {
        $vwoClient = $this->createVWOClient();
        $this->assertNotNull($vwoClient);

        $user1Flag = $vwoClient->getFlag('featureOne', ['id' => 'WingifyVWO']);
        $user2Flag = $vwoClient->getFlag('featureOne', ['id' => 'RandomUserVWO']);

        // Users with different IDs should get different variations for this split
        $this->assertNotEquals($user1Flag->getVariables(), $user2Flag->getVariables());
    }

    /**
     * Case 2: Bucketing Seed Provided
     * Scenario: Two different users ('KaustubhVWO', 'RandomUserVWO') are provided with the SAME bucketingSeed.
     * Expected: Since the seed is identical, they MUST get the same variation.
     */
    public function testShouldAssignSameVariationToDifferentUsersWithSameBucketingSeed()
    {
        $vwoClient = $this->createVWOClient();
        $this->assertNotNull($vwoClient);

        $sameBucketingSeed = 'common-seed-123';

        $user1Flag = $vwoClient->getFlag('featureOne', [
            'id' => 'WingifyVWO',
            'bucketingSeed' => $sameBucketingSeed,
        ]);

        $user2Flag = $vwoClient->getFlag('featureOne', [
            'id' => 'RandomUserVWO',
            'bucketingSeed' => $sameBucketingSeed,
        ]);

        $this->assertEquals($user1Flag->getVariables(), $user2Flag->getVariables());
    }

    /**
     * Case 3: Different Seeds
     * Scenario: The SAME User ID is used, but with DIFFERENT bucketing seeds.
     * Expected: The SDK should bucket based on the seed. Since we use seeds known to produce
     * different results ('KaustubhVWO' vs 'RandomUserVWO'), the outcomes should differ.
     */
    public function testShouldAssignDifferentVariationsToUsersWithDifferentBucketingSeeds()
    {
        $vwoClient = $this->createVWOClient();
        $this->assertNotNull($vwoClient);

        // Same user ID, different seeds
        // Using the names as seeds to simulate the difference
        $user1Flag = $vwoClient->getFlag('featureOne', ['id' => 'sameId', 'bucketingSeed' => 'WingifyVWO']);
        $user2Flag = $vwoClient->getFlag('featureOne', ['id' => 'sameId', 'bucketingSeed' => 'RandomUserVWO']);

        $this->assertNotEquals($user1Flag->getVariables(), $user2Flag->getVariables());
    }

    /**
     * Case 4: Empty String Seed
     * Scenario: bucketingSeed is provided but it's an empty string.
     * Expected: Empty string should fall back to userId.
     * Different users should get different variations.
     */
    public function testShouldFallbackToUserIdWhenBucketingSeedIsEmptyString()
    {
        $vwoClient = $this->createVWOClient();
        $this->assertNotNull($vwoClient);

        // Empty string should be treated as no seed
        $user1Flag = $vwoClient->getFlag('featureOne', ['id' => 'WingifyVWO', 'bucketingSeed' => '']);
        $user2Flag = $vwoClient->getFlag('featureOne', ['id' => 'RandomUserVWO', 'bucketingSeed' => '']);

        // Should use userIds since empty strings are falsy
        $this->assertNotEquals($user1Flag->getVariables(), $user2Flag->getVariables());
    }

    
    //Case 5: No bucketing seed, custom salt present - 10 users, randomly distributed, but each user getting same variation in both flags
     
    public function testNoBucketingSeedCustomSaltPresent10UsersRandomlyDistributedButEachUserGettingSameVariationInBothFlags()
    {
        $vwoBuilder = new VWOBuilder([
            'accountId' => '123456',
            'sdkKey' => 'abcdef'
        ]);
        $vwoBuilder->setLogger();
        $vwoBuilder->setSettings(self::$SETTINGS_WITH_SAME_SALT);

        $vwoClient = VWO::init([
            'sdkKey' => 'sdk-key',
            'accountId' => 'account-id',
            'vwoBuilder' => $vwoBuilder
        ]);
        $this->assertNotNull($vwoClient);

        // loop for 10 users and check if both flags yield the exact same variation for the same user due to identical salt
        for ($i = 1; $i <= 10; $i++) {
            $userId = "user$i";
            $flag1 = $vwoClient->getFlag('featureOne', ['id' => $userId]);
            $flag2 = $vwoClient->getFlag('featureTwo', ['id' => $userId]);

            // Both flags should yield the exact same variation for the same user due to identical salt
            $this->assertEquals($flag1->getVariables(), $flag2->getVariables());
        }
    }

    
    //Case 6: Bucketing seed present, custom salt present - 10 users, all users getting same variation in both flags
    public function testBucketingSeedPresentSaltPresent10UsersAllUsersGettingSameVariationInBothFlags()
    {
        $vwoBuilder = new VWOBuilder([
            'accountId' => '123456',
            'sdkKey' => 'abcdef'
        ]);
        $vwoBuilder->setLogger();
        $vwoBuilder->setSettings(self::$SETTINGS_WITH_SAME_SALT);

        $vwoClient = VWO::init([
            'sdkKey' => 'sdk-key',
            'accountId' => 'account-id',
            'vwoBuilder' => $vwoBuilder
        ]);
        $this->assertNotNull($vwoClient);

        $commonBucketingSeed = 'common_seed_456';
        $variationsAssigned = [];

        // loop for 10 users and check if both flags yield the exact same variation for the same user due to identical salt
        for ($i = 1; $i <= 10; $i++) {
            $userId = "user$i";
            $flag1 = $vwoClient->getFlag('featureOne', ['id' => $userId, 'bucketingSeed' => $commonBucketingSeed]);
            $flag2 = $vwoClient->getFlag('featureTwo', ['id' => $userId, 'bucketingSeed' => $commonBucketingSeed]);

            // Both flags should yield the exact same variation
            $this->assertEquals($flag1->getVariables(), $flag2->getVariables());

            $variationsAssigned[json_encode($flag1->getVariables())] = true;
        }

        // Since the bucketing seed is the exact same for all 10 users, they MUST all get the same variation
        $this->assertCount(1, $variationsAssigned);
    }

    
    //Case 7: should return forced variation for whitelisted user without bucketing seed
    public function testShouldReturnForcedVariationForWhitelistedUserWithoutBucketingSeed()
    {
        $vwoClient = $this->createVWOClient();
        $this->assertNotNull($vwoClient);

        // Without bucketing seed, forcedWingify must get the forced variation (Variation-2, value: 'var2')
        $forcedUserFlag = $vwoClient->getFlag('featureOne', ['id' => 'forcedWingify']);
        // MOCK_SETTINGS defines forcedWingify pointing to Variation-2 with kaus => var2
        $this->assertEquals('var2', $forcedUserFlag->getVariable('kaus', ''));
    }

    
    //Case 8: should still return forced variation for whitelisted user when bucketing seed is present
    public function testShouldStillReturnForcedVariationForWhitelistedUserWhenBucketingSeedIsPresent()
    {
        $vwoClient = $this->createVWOClient();
        $this->assertNotNull($vwoClient);

        // Even with a bucketing seed, forcedWingify must still get the forced variation (Variation-2, value: 'var2')
        $forcedUserFlag = $vwoClient->getFlag('featureOne', [
            'id' => 'forcedWingify',
            'bucketingSeed' => 'some-seed-xyz'
        ]);

        $this->assertEquals('var2', $forcedUserFlag->getVariable('kaus', ''));
    }

    private function createVWOClientWithAliasingMock($resolvedUserIds)
    {
        // Create VWO client with aliasing enabled and mock network service
        $vwoOptions = [
            'accountId' => '123456',
            'sdkKey' => 'abcdef',
            'isAliasingEnabled' => true,
            'gatewayService' => ['url' => 'http://localhost:3000']
        ];

        $vwoBuilder = new VWOBuilder($vwoOptions);
        $vwoBuilder->setLogger();
        $vwoBuilder->setSettings(self::$MOCK_SETTINGS_FILE);

        $vwoClient = VWO::init([
            'sdkKey' => 'sdk-key',
            'accountId' => 'account-id',
            'vwoBuilder' => $vwoBuilder,
        ]);
        //mock service container
        $reflection = new \ReflectionClass($vwoClient);
        $property = $reflection->getProperty('serviceContainer');
        $property->setAccessible(true);
        $serviceContainer = $property->getValue($vwoClient);
        //mock network manager
        $mockNetworkManager = new class($resolvedUserIds) extends \vwo\Packages\NetworkLayer\Manager\NetworkManager {
            private $userMap;
            public function __construct($userMap) {
                $this->userMap = $userMap;
            }
            public function get($request) {
                $queryParams = $request->getQuery();
                $userIdArrayString = $queryParams['userId'] ?? '[]';
                $userIdArray = json_decode($userIdArrayString, true);
                $originalUserId = $userIdArray[0] ?? '';
                //resolve alias
                $resolvedId = $this->userMap[$originalUserId] ?? $originalUserId;
                //mock response
                $mockResponse = new \vwo\Packages\NetworkLayer\Models\ResponseModel();
                $mockResponse->setData([(object)['userId' => $resolvedId]]);
                return $mockResponse;
            }
        };

        $serviceContainer->setNetworkManager($mockNetworkManager);

        return $vwoClient;
    }

    /**
     * Case 9: with bucketing seed - two aliased users resolving to different userIds should get the SAME variation when same seed is used
     */
    public function testWithBucketingSeedTwoAliasedUsersResolvingToDifferentUserIdsShouldGetTheSameVariationWhenSameSeedIsUsed()
    {
        $vwoClient = $this->createVWOClientWithAliasingMock([
            'aliasUserA' => 'RandomUserVWO',
            'aliasUserB' => 'WingifyVWO'
        ]);

        $bucketingSeed = 'shared-seed-abc';
        //get flag for aliasUserA
        $flag1 = $vwoClient->getFlag('featureOne', [
            'id' => 'aliasUserA',
            'bucketingSeed' => $bucketingSeed
        ]);
        //get flag for aliasUserB
        $flag2 = $vwoClient->getFlag('featureOne', [
            'id' => 'aliasUserB',
            'bucketingSeed' => $bucketingSeed
        ]);

        // Even though aliasing resolved to two DIFFERENT userIds (RandomUserVWO vs WingifyVWO),
        // the SAME bucketing seed was used, so both must get the SAME variation
        $this->assertEquals($flag1->getVariables(), $flag2->getVariables());
    }

    
    // Case 10: without bucketing seed - two aliased users resolving to different userIds should get DIFFERENT variations
    public function testWithoutBucketingSeedTwoAliasedUsersResolvingToDifferentUserIdsShouldGetDifferentVariations()
    {
        $vwoClient = $this->createVWOClientWithAliasingMock([
            'aliasUserA' => 'RandomUserVWO',
            'aliasUserB' => 'WingifyVWO'
        ]);

        $flag1 = $vwoClient->getFlag('featureOne', ['id' => 'aliasUserA']);
        $flag2 = $vwoClient->getFlag('featureOne', ['id' => 'aliasUserB']);

        // Without bucketing seed, bucketing uses the resolved userId.
        // RandomUserVWO and WingifyVWO are known to get different variations.
        $this->assertNotEquals($flag1->getVariables(), $flag2->getVariables());
    }
}
