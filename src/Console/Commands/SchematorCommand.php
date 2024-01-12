<?php

namespace Jkbcoder\Schemator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

        foreach ($tables as $table) {
            $this->generateModel($table,$tables);

            if ($filament && $this->isFilamentInstalled()) {
                $modelName = ucfirst(Str::camel(Str::singular($table)));
                $this->generateFilamentResource($modelName, $simple, $generate, $softDeletes, $view);
            }
        }

        $this->info('Schemator execution completed.');
    }

    protected function generateModel($tableName, $allTables) {
        $className = $this->formatClassName($tableName);
        $columns = Schema::getColumnListing($tableName);
        $properties = implode(', ', array_map(fn($column) => "'$column'", $columns));

        $modelTemplate = "<?php";
        $modelTemplate .= "\n\n/**\n * Created by Schemator Model.\n */\n";
        $modelTemplate .= "\n\nnamespace App\Models;\n\nuse Illuminate\Database\Eloquent\Model;\n\n";
        $modelTemplate .= "class $className extends Model\n{\n    protected \$table = '$tableName';\n    protected \$fillable = [$properties];\n\n";

        $foreignKeys = $this->getForeignKeys($tableName);
        foreach ($foreignKeys as $foreignKey) {
            $foreignTableName = $foreignKey->getForeignTableName();
            $relatedTable = $this->formatRelationName($foreignTableName);
            $localColumnName = $foreignKey->getLocalColumns()[0];

            $modelTemplate .= "    public function $relatedTable()\n    {\n        return \$this->belongsTo(\App\Models\\" . ucfirst($relatedTable) . "::class, '$localColumnName');\n    }\n\n";
        }

        // Logic for hasMany relationships
        foreach ($allTables as $otherTable) {
            if ($otherTable == $tableName) continue;

            $otherTableForeignKeys = $this->getForeignKeys($otherTable);
            foreach ($otherTableForeignKeys as $foreignKey) {
                if ($foreignKey->getForeignTableName() == $tableName) {
                    $relatedTablePlural = $this->formatRelationName($otherTable, true);
                    $relatedTableSingular = $this->formatRelationName($otherTable);
                    $localColumnName = $foreignKey->getLocalColumns()[0];

                    $modelTemplate .= "    public function $relatedTablePlural()\n    {\n        return \$this->hasMany(\App\Models\\" . ucfirst($relatedTableSingular) . "::class, '$localColumnName');\n    }\n\n";
                }
            }
        }

        $modelTemplate .= "}";

        file_put_contents(app_path("Models/$className.php"), $modelTemplate);
        $this->info("Model generated for table: $tableName");
    }

    protected function getForeignKeys($table) {
        $conn = Schema::getConnection()->getDoctrineSchemaManager();
        $databaseName = env('DB_DATABASE');
        return $conn->listTableForeignKeys($table, $databaseName);
    }

    protected function isFilamentInstalled() {
        return class_exists(\Filament\FilamentServiceProvider::class);
    }

    protected function generateFilamentResource($modelName, $simple, $generate, $softDeletes, $view) {
        $commandOptions = [
            'name' => $modelName,
            '--simple' => $simple,
            '--generate' => $generate,
            '--soft-deletes' => $softDeletes,
            '--view' => $view,
        ];

        $this->call('make:filament-resource', $commandOptions);
        $this->info("Filament resource generated for model: $modelName");
    }

    protected function formatClassName($tableName) {
        return ucfirst(Str::camel(Str::singular($tableName)));
    }

    protected function formatRelationName($tableName, $isPlural = false) {
        return $isPlural ? Str::plural(Str::camel($tableName)) : Str::camel(Str::singular($tableName));
    }
}
