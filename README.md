# Rector output to composer patches

## Overview
When updating to a newer PHP version, you might encounter compatibility issues with third-party dependencies.
This tool transforms the output from Rector into composer patches to address these incompatibilities in third-party packages.

## Prerequisites
 - PHP 8.1 or higher

## Dependencies
composer.json includes:
```
    "symfony/console": "^v6.3.4"
```

## Installation Steps
Please follow the instructions:

- Run `composer require --dev labofgood/rector-output-to-composer-patches` 

## Usage Guide

- Install Rector and gather the output for a third-party package.
```bash
composer require rector/rector --dev
php /path/to/project/vendor/bin/rector process /path/to/project/vendor/vendor_name/package_name --dry-run --output-format=json --autoload-file /path/to/project/vendor/autoload.php  > /path/to/project/rector.json
```
- Run the following command to generate patches:
```bash
php bin/console rector:generate:composer-patches --file_path '/path/to/project/rector.json' --ticket ISSUE-123
```
- The patches will be generated in the `/path/to/project/patches` folder.

## Credits
 - Anton Abramchenko <anton.abramchenko@labofgood.com>

## Licensing
Copyright © 2023 Anton Abramchenko. All rights reserved.
This software is under the "3-Clause BSD License" license (see source). 

