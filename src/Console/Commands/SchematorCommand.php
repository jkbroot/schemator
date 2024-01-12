<?php

namespace Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Jkbcoder\Schemator\Generators\FilamentResourceGenerator;
use Jkbcoder\Schemator\Generators\ModelGenerator;


class SchematorCommand extends Command {
    protected $signature = 'schemator:generate {options?}';
    protected $description = 'Generate models with properties and relationships from the database schema';
    public function handle() {
        $optionString = $this->argument('options');

        $filament = str_contains($optionString, 'f');
        $simple = str_contains($optionString, 's');
        $generate = str_contains($optionString, 'g');
        $softDeletes = str_contains($optionString, 'd');
        $view = str_contains($optionString, 'v');

        $this->info('Starting Schemator...');

        $tables = Schema::getConnection()->getDoctrineSchemaManager()->listTableNames();

        $modelGenerator = new ModelGenerator();
        $filamentResourceGenerator = new FilamentResourceGenerator();

        foreach ($tables as $table) {
            $modelGenerator->generate($table, $tables);

            if ($filament && $filamentResourceGenerator->isFilamentInstalled()) {
                $modelName = ucfirst(Str::camel(Str::singular($table)));
                $filamentResourceGenerator->generate($modelName, $simple, $generate, $softDeletes, $view);
            }
        }

        $this->info('Schemator execution completed.');
    }



}
