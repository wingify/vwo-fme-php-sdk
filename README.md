# VWO Feature Management and Experimentation SDK for PHP

[![Latest Stable Version](https://img.shields.io/packagist/v/vwo/vwo-fme-php-sdk.svg)](https://packagist.org/packages/vwo/vwo-fme-php-sdk) [![CI](https://github.com/wingify/vwo-fme-php-sdk/workflows/CI/badge.svg?branch=master)](https://github.com/wingify/vwo-fme-php-sdk/actions?query=workflow%3ACI)
 [![Coverage Status](https://coveralls.io/repos/github/wingify/vwo-fme-php-sdk/badge.svg?branch=master)](https://coveralls.io/github/wingify/vwo-fme-php-sdk?branch=master)[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](http://www.apache.org/licenses/LICENSE-2.0)

## Overview

The **VWO Feature Management and Experimentation SDK** (VWO FME Php SDK) enables php developers to integrate feature flagging and experimentation into their applications. This SDK provides full control over feature rollout, A/B testing, and event tracking, allowing teams to manage features dynamically and gain insights into user behavior.

## Requirements

- **PHP 7.0 and onwards**

## Installation

Install the latest version with

```bash
composer require vwo/vwo-fme-php-sdk
```

## Basic Usage Example

The following example demonstrates initializing the SDK with a VWO account ID and SDK key, setting a user context, checking if a feature flag is enabled, and tracking a custom event.

```php
$vwoClient = VWO::init([
  'sdkKey' => 'vwo_sdk_key',
  'accountId' => 'vwo_account_id',
]);

// set user context
$userContext = [ 'id' => 'unique_user_id'];

// returns a flag object
$getFlag = $vwoClient->getFlag('feature_key', $userContext);

// check if flag is enabled
$isFlagEnabled = $getFlag['isEnabled'];

// get variable
$variableValue = $getFlag->getVariable('variable_key', 'default-value');

// track event
$trackRes = $vwoClient->trackEvent('event_name', $userContext);

// set Attribute
$attributes = [
  'attribute_key' => 'attribute_value'
];
$setAttribute = $vwoClient->setAttribute($attributes, $userContext);
```

## Advanced Configuration Options

To customize the SDK further, additional parameters can be passed to the `init()` API. Here's a table describing each option:

| **Parameter**                | **Description**                                                                                                                                             | **Required** | **Type**  | **Example**                     |
| ---------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------ | --------- | ------------------------------- |
| `sdkKey`                     | SDK key corresponding to the specific environment to initialize the VWO SDK Client. You can get this key from VWO Application.                              | Yes          | string    | `'32-alpha-numeric-sdk-key'`    |
| `accountId`                  | VWO Account ID for authentication.                                                                                                                          | Yes          | string    | `'123456'`                      |
| `pollInterval`               | Time interval for fetching updates from VWO servers (in milliseconds).                                                                                      | No           | integer   | `60000`                         |
| `gatewayService`             | An object representing configuration for integrating VWO Gateway Service.                                                                                   | No           | array     | see [Gateway](#gateway) section |
| `storage`                    | Custom storage connector for persisting user decisions and campaign data.                                                                                   | No           | array     | See [Storage](#storage) section |
| `logger`                     | Toggle log levels for more insights or for debugging purposes. You can also customize your own transport in order to have better control over log messages. | No           | array     | See [Logger](#logger) section   |
| `Integrations`         | Callback function for integrating with third-party analytics services.                                              | No           | object      | See [Integrations](#integrations) section |

Refer to the [official VWO documentation](https://developers.vwo.com/v2/docs/fme-php-install) for additional parameter details.

### User Context

The `context` array uniquely identifies users and is crucial for consistent feature rollouts. A typical `context` includes an `id` for identifying the user. It can also include other attributes that can be used for targeting and segmentation, such as `customVariables`, `userAgent` and `ipAddress`.

#### Parameters Table

The following table explains all the parameters in the `context` array:

| **Parameter**     | **Description**                                                            | **Required** | **Type** | **Example**                       |
| ----------------- | -------------------------------------------------------------------------- | ------------ | -------- | --------------------------------- |
| `id`              | Unique identifier for the user.                                            | Yes          | string   | `'unique_user_id'`                |
| `customVariables` | Custom attributes for targeting.                                           | No           | array    | `['age' => 25, 'location' => 'US']` |
| `userAgent`       | User agent string for identifying the user's browser and operating system. | No           | string   | `'Mozilla/5.0 ... Safari/537.36'` |
| `ipAddress`       | IP address of the user.                                                    | No           | string   | `'1.1.1.1'`                       |

#### Example

```php
$userContext = [
  'id' => 'unique_user_id',
  'customVariables' => ['age' => 25, 'location' => 'US'],
  'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
  'ipAddress' => '1.1.1.1',
];
```

### Basic Feature Flagging

Feature Flags serve as the foundation for all testing, personalization, and rollout rules within FME.
To implement a feature flag, first use the `getFlag` API to retrieve the flag configuration.
The `getFlag` API provides a simple way to check if a feature is enabled for a specific user and access its variables. It returns a feature flag object that contains methods for checking the feature's status and retrieving any associated variables.

| Parameter    | Description                                                      | Required | Type   | Example                     |
| ------------ | ---------------------------------------------------------------- | -------- | ------ | --------------------------- |
| `featureKey` | Unique identifier of the feature flag                            | Yes      | string | `'new_checkout'`            |
| `context`    | Array containing user identification and contextual information  | Yes      | array  | `['id' => 'user_123']`      |

Example usage:

```php
$featureFlag = $vwoClient->getFlag('feature_key', $userContext);
$isEnabled = $featureFlag->isEnabled();

if ($isEnabled) {
  echo 'Feature is enabled!';

  // Get and use feature variable with type safety
  $variableValue = $featureFlag->getVariable('feature_variable', 'default_value');
  echo 'Variable value:', $variableValue;
} else {
  echo 'Feature is not enabled!';
}
```

### Custom Event Tracking

Feature flags can be enhanced with connected metrics to track key performance indicators (KPIs) for your features. These metrics help measure the effectiveness of your testing rules by comparing control versus variation performance, and evaluate the impact of personalization and rollout campaigns. Use the `trackEvent` API to track custom events like conversions, user interactions, and other important metrics:

| Parameter         | Description                                                            | Required | Type   | Example                     |
| ----------------- | ---------------------------------------------------------------------- | -------- | ------ | --------------------------- |
| `eventName`       | Name of the event you want to track                                    | Yes      | string | `'purchase_completed'`      |
| `context`         | Array containing user identification and contextual information        | Yes      | array  | `['id' => 'user_123']`      |
| `eventProperties` | Additional properties/metadata associated with the event               | No       | array  | `['amount' => 49.99]`       |

Example usage:

```php
$vwoClient->trackEvent('event_name', $userContext, $eventProperties);
```

See [Tracking Conversions](https://developers.vwo.com/v2/docs/fme-php-metrics#usage) documentation for more information.

### Pushing Attributes

User attributes provide rich contextual information about users, enabling powerful personalization. The `setAttribute` method provides a simple way to associate these attributes with users in VWO for advanced segmentation. Here's what you need to know about the method parameters:

| Parameter        | Description                                                            | Required | Type                | Example                     |
| ---------------- | ---------------------------------------------------------------------- | -------- | ------------------- | --------------------------- |
| `attributeKey`   | The unique identifier/name of the attribute you want to set            | Yes      | string             | `'plan_type'`               |
| `attributeValue` | The value to be assigned to the attribute                              | Yes      | string/int/boolean | `'premium'`, `25`, `true`   |
| `context`        | Array containing user identification and contextual information        | Yes      | array              | `['id' => 'user_123']`      |

Example usage:

```php
$vwoClient->setAttribute('attribute_name', 'attribute_value', $userContext);
```

Or

```php
$attributes = [
  'attribute_name' => 'attribute_value'
];
$vwoClient->setAttribute($attributes, $userContext);
```

See [Pushing Attributes](https://developers.vwo.com/v2/docs/fme-php-attributes#usage) documentation for additional information.

### Polling Interval Adjustment

The `pollInterval` is an optional parameter that allows the SDK to automatically fetch and update settings from the VWO server at specified intervals. Setting this parameter ensures your application always uses the latest configuration.

```php
$vwoClient = VWO::init([
  'sdkKey' => '32-alpha-numeric-sdk-key',
  'accountId' => '123456',
  'pollInterval' => 60000,
]);
```

### Gateway

The VWO FME Gateway Service is an optional but powerful component that enhances VWO's Feature Management and Experimentation (FME) SDKs. It acts as a critical intermediary for pre-segmentation capabilities based on user location and user agent (UA). By deploying this service within your infrastructure, you benefit from minimal latency and strengthened security for all FME operations.

#### Why Use a Gateway?

The Gateway Service is required in the following scenarios:

- When using pre-segmentation features based on user location or user agent.
- For applications requiring advanced targeting capabilities.
- It's mandatory when using any thin-client SDK (e.g., Go).

#### How to Use the Gateway

The gateway can be customized by passing the `gatewayService` parameter in the `init` configuration.

```php
$vwoClient = VWO::init([
  'sdkKey' => '32-alpha-numeric-sdk-key',
  'accountId' => '123456',
  'gatewayService' => [
    'url' => 'https://custom.gateway.com',
  ],
]);
```

Refer to the [Gateway Documentation](https://developers.vwo.com/v2/docs/gateway-service) for further details.

### Storage

The SDK operates in a stateless mode by default, meaning each `getFlag` call triggers a fresh evaluation of the flag against the current user context.

To optimize performance and maintain consistency, you can implement a custom storage mechanism by passing a `storage` parameter during initialization. This allows you to persist feature flag decisions in your preferred database system (like Redis, MongoDB, or any other data store).

Key benefits of implementing storage:

- Improved performance by caching decisions
- Consistent user experience across sessions
- Reduced load on your application

The storage mechanism ensures that once a decision is made for a user, it remains consistent even if campaign settings are modified in the VWO Application. This is particularly useful for maintaining a stable user experience during A/B tests and feature rollouts.

```php
class StorageConnector {
   private $map = [];


   public function get($featureKey, $userId) {
    $key = $featureKey . '_' . $userId;
    return isset($this->map[$key]) ? $this->map[$key] : null;
   }


   public function set($data) {
    $key = $data['featureKey'] . '_' . $data['user'];
    // Implement your storage logic here to store the data in your preferred database system using $key
   }
}

// Initialize the StorageConnector
$storageConnector = new StorageConnector();

$vwoClient = VWO::init([
  'sdkKey' => '32-alpha-numeric-sdk-key',
  'accountId' => '123456',
  'storage' => $storageConnector,
]);
```

### Logger

VWO by default logs all `ERROR` level messages to your server console.
To gain more control over VWO's logging behaviour, you can use the `logger` parameter in the `init` configuration.

| **Parameter** | **Description**                        | **Required** | **Type** | **Example**           |
| ------------- | -------------------------------------- | ------------ | -------- | --------------------- |
| `level`       | Log level to control verbosity of logs | Yes          | string   | `'DEBUG'`             |
| `prefix`      | Custom prefix for log messages         | No           | string   | `'CUSTOM LOG PREFIX'` |
| `transport`   | Custom logger implementation           | No           | array    | See example below     |

#### Example 1: Set log level to control verbosity of logs

```php
$vwoClient1 = VWO::init([
  'sdkKey' => '32-alpha-numeric-sdk-key',
  'accountId' => '123456',
  'logger' => [
    'level' => 'DEBUG',
  ],
]);
```

#### Example 2: Add custom prefix to log messages for easier identification

```php
$vwoClient2 = VWO::init([
  'sdkKey' => '32-alpha-numeric-sdk-key',
  'accountId' => '123456',
  'logger' => [
    'level' => 'DEBUG',
    'prefix' => 'CUSTOM LOG PREFIX',
  ],
]);
```

#### Example 3: Implement custom transport to handle logs your way

The `transport` parameter allows you to implement custom logging behavior by providing your own logging functions. You can define handlers for different log levels (`debug`, `info`, `warn`, `error`, `trace`) to process log messages according to your needs.

For example, you could:

- Send logs to a third-party logging service
- Write logs to a file
- Format log messages differently
- Filter or transform log messages
- Route different log levels to different destinations

The transport object should implement handlers for the log levels you want to customize. Each handler receives the log message as a parameter.

For single transport you can use the `transport` parameter. For example:
```php
$vwoClient3 = VWO::init([
  'sdkKey' => '32-alpha-numeric-sdk-key',
  'logger' => [
    'transport' => [
        'level' => 'DEBUG',
        'logHandler' => function($msg, $level){
          echo "$msg $level";
        }
    ],
  ]
]);
```

For multiple transports you can use the `transports` parameter. For example:
```php
$vwoClient3 = VWO::init([
  'sdkKey' => '32-alpha-numeric-sdk-key',
  'logger' => [
    'transports' => [
        [
            'level' => 'DEBUG',
            'logHandler' => function($msg, $level){
              echo "$msg $level";
            }
        ],
        [
            'level' => 'INFO',
            'logHandler' => function($msg, $level){
              echo "$msg $level";
            }
        ]
    ]
  ]
]);
```

### Integrations
VWO FME SDKs provide seamless integration with third-party tools like analytics platforms, monitoring services, customer data platforms (CDPs), and messaging systems. This is achieved through a simple yet powerful callback mechanism that receives VWO-specific properties and can forward them to any third-party tool of your choice.

```php
function callback($properties) {
    // properties will contain all the required VWO specific information
    echo json_encode($properties);
}

$options = [
    'sdkKey' => '32-alpha-numeric-sdk-key', // SDK Key
    'accountId' => '12345', // VWO Account ID
    'integrations' => [
        'callback' => 'callback'
    ]
];

$vwoClient = VWO::init($options);
```

### Version History

The version history tracks changes, improvements, and bug fixes in each version. For a full history, see the [CHANGELOG.md](https://github.com/wingify/vwo-fme-php-sdk/blob/master/CHANGELOG.md).

## Development and Testing

1. Set development environment

```bash
composer run-script start
```

2. Run test cases

```bash
composer run-script test
```

### Contributing

Please go through our [contributing guidelines](https://github.com/wingify/vwo-fme-php-sdk/blob/master/CONTRIBUTING.md)

### Code of Conduct

[Code of Conduct](https://github.com/wingify/vwo-fme-php-sdk/blob/master/CODE_OF_CONDUCT.md)

### License

[Apache License, Version 2.0](https://github.com/wingify/vwo-fme-php-sdk/blob/master/LICENSE)

Copyright 2024-2025 Wingify Software Pvt. Ltd.
