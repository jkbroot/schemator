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
php artisan schemator:generate [options]

```

Where [options] can be a combination of the following:

- f: Generate Filament resources (if Filament is installed).
- s: Use the --simple option for Filament resources.
- g: Use the --generate option for Filament resources.
- d: Include --soft-deletes in the models and resources.
- v: Use the --view option for Filament resources.


For example, to generate models and Filament resources with all options:
```
php artisan schemator:generate fsgdv

```


## Contributing
Contributions to Schemator are welcome. You can contribute in various ways:

- Submitting bug reports and feature requests.
- Writing code for new features or bug fixes.
- Improving documentation.

Please feel free to fork the repository and submit pull requests.

## License
Schemator is open-sourced software licensed under the MIT license.

