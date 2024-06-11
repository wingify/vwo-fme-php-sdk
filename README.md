## VWO Feature Management and Experimentation SDK for PHP

[![Latest Stable Version](https://img.shields.io/packagist/v/vwo/vwo-fme-php-sdk.svg)](https://packagist.org/packages/vwo/vwo-fme-php-sdk) [![CI](https://github.com/wingify/vwo-fme-php-sdk/workflows/CI/badge.svg?branch=master)](https://github.com/wingify/vwo-fme-php-sdk/actions?query=workflow%3ACI)
 [![Coverage Status](https://coveralls.io/repos/github/wingify/vwo-fme-php-sdk/badge.svg?branch=master)](https://coveralls.io/github/wingify/vwo-fme-php-sdk?branch=master)[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](http://www.apache.org/licenses/LICENSE-2.0)

### Requirements

> PHP >= 7.4

### Installation

Install the latest version with

```bash
composer require vwo/vwo-fme-php-sdk
```

## Basic Usage

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
$variableValue = $getFlag->getVariable('stringVar', 'default-value');

// track event
$trackRes = $vwoClient->trackEvent('event-name', $userContext);

// set Attribute
$setAttribute = $vwoClient->setAttribute('attribute-name', 'attribute-value', $userContext);
```

## Changelog

Refer [CHANGELOG.md](https://github.com/wingify/vwo-fme-php-sdk/blob/master/CHANGELOG.md)

## Development and Test Cases

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

Copyright 2024 Wingify Software Pvt. Ltd.
