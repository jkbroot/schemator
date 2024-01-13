# Schemator for Laravel

Schemator is a Laravel package designed to automate the process of generating Eloquent models and Filament resources based on your database schema. It simplifies the initial setup of models in Laravel projects by auto-generating them with properties and relationships.

## Features

- Automatically generates Eloquent models for each database table.
- Supports a wide array of relationships including `belongsTo`, `hasMany`, `hasOne`, `belongsToMany`, `morphOne`, and `morphMany`.
- Generates Filament resources if Filament is installed, with support for various options like `--simple`, `--generate`, `--soft-deletes`, and `--view`.
- Embeds a comment in each model indicating creation by Schemator for clarity and tracking.

## Requirements

- Laravel 8 or newer
- PHP 7.3 or newer
- FilamentPHP (optional for resource generation)

## Installation

To install Schemator, run the following command in your Laravel project:

```bash
composer require 0jkb/Schemator
```

After installation, you can use the Artisan command provided by Schemator.

Usage

To generate models and optionally Filament resources, run:
```
php artisan schemator:generate -f [options] [--skip={table1,table2,...}]

```

- -f | filament-options = to accept a string of Filament options such as g, s, d, v.
- --skip= for specifying tables to skip.
- --skip-default as a flag to skip Laravel's default tables.
- --only= to generate models for specific tables.

To generate models only without any additional options or generating Filament resources, you can use the schemator:generate command without specifying any options:
```
php artisan schemator:generate
```

Example for Generating Filament Resources and Using the Simple Option:
```
php artisan schemator:generate -f s
```
This command will generate Filament resources for each table and apply the --simple option to them.

Example for Generating Filament Resources and Using the Generate Option:
```
php artisan schemator:generate -f g
```

Example to generate models and Filament resources with all options:

```
php artisan schemator:generate -f sgdv

```

Example for full feature usage, skipping specific tables:

```
php artisan schemator:generate -f sgdv --skip=users,logs
```

Example for generating models for specific tables:
```
php artisan schemator:generate -f sgdv --only=users,posts
```
###### This command will generate models only for the 'users' and 'posts' tables.



## Contributing
Contributions to Schemator are welcome. You can contribute in various ways:

- Submitting bug reports and feature requests.
- Writing code for new features or bug fixes.
- Improving documentation.

Please feel free to fork the repository and submit pull requests.

## License
Schemator is open-sourced software licensed under the MIT license.

