<?php

namespace Jkbcoder\Schemator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SchematorCommand extends Command {
    protected $signature = 'schemator:generate {options?} {--skip= : Comma-separated list of tables to skip}';

    protected $description = 'Generate models with properties and relationships from the database schema';
    protected $createdMethods = [];

    public function handle() {
        $optionString = $this->argument('options');
        $skipTables = $this->option('skip') ? explode(',', $this->option('skip')) : [];


        $filament = str_contains($optionString, 'f');
        $simple = str_contains($optionString, 's');
        $generate = str_contains($optionString, 'g');
        $softDeletes = str_contains($optionString, 'd');
        $view = str_contains($optionString, 'v');

        $this->info('Starting Schemator...');

        $tables = Schema::getConnection()->getDoctrineSchemaManager()->listTableNames();

        foreach ($tables as $table) {
            //for skipping certain tables
            if (in_array($table, $skipTables)) {
                $this->info("Skipping table: $table");
                continue;
            }
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
        $modelTemplate .= "class $className extends Model\n{\n  protected \$table = '$tableName';\n    protected \$fillable = [$properties];\n\n";

        $foreignKeys = $this->getForeignKeys($tableName);
        foreach ($foreignKeys as $foreignKey) {
            $foreignTableName = $foreignKey->getForeignTableName();
            $relatedTable = $this->formatRelationName($foreignTableName);
            $localColumnName = $foreignKey->getLocalColumns()[0];

            $modelTemplate .= "    public function $relatedTable()\n {\n return \$this->belongsTo(\App\Models\\" . ucfirst($relatedTable) . "::class, '$localColumnName');\n    }\n\n";
        }

        // Logic for relationships
        foreach ($allTables as $otherTable) {
            if ($otherTable == $tableName) continue;

            $otherTableForeignKeys = $this->getForeignKeys($otherTable);
            foreach ($otherTableForeignKeys as $foreignKey) {

                //  for hasOne relationship
                if ($foreignKey->getForeignTableName() == $tableName) {
                    $relatedTablePlural = $this->formatRelationName($otherTable, true);
                    $relatedTableSingular = $this->formatRelationName($otherTable);
                    $localColumnName = $foreignKey->getLocalColumns()[0];

                    $modelTemplate .= "    public function $relatedTablePlural()\n    {\n        return \$this->hasMany(\App\Models\\" . ucfirst($relatedTableSingular) . "::class, '$localColumnName');\n    }\n\n";
                }

                //for hasOne relationship
                $hasOneForeignKey = $this->detectHasOneForeignKey($otherTable, $tableName);
                if ($hasOneForeignKey) {
                    $relatedTableSingular = $this->formatRelationName($otherTable);
                    $modelTemplate .= "    public function $relatedTableSingular()\n    {\n        return \$this->hasOne(\App\Models\\" . ucfirst($relatedTableSingular) . "::class, '$hasOneForeignKey');\n    }\n\n";
                }



                //for belongsToMany relationship
                $pivotTable = $this->detectPivotTable($tableName, $otherTable);
                $relatedTablePluralMethod = $this->formatRelationName($otherTable, true);
                $relatedTableModel = ucfirst($this->formatRelationName($otherTable));

                // Check if the method has not been created yet
                if ($pivotTable && !in_array($relatedTablePluralMethod, $this->createdMethods, true)) {
                    $modelTemplate .= "    public function $relatedTablePluralMethod()\n    {\n        return \$this->belongsToMany(\App\Models\\" . $relatedTableModel . "::class, '$pivotTable');\n    }\n\n";
                    $this->createdMethods[] = $relatedTablePluralMethod;
                }

                //for morphOne relationship
                $morphOneDetected = $this->detectMorphOne($otherTable, $tableName);
                if ($morphOneDetected) {
                    $relatedTableSingular = $this->formatRelationName($otherTable);
                    $morphName = $this->getMorphName($tableName);
                    $modelTemplate .= "    public function $relatedTableSingular()\n    {\n        return \$this->morphOne(\App\Models\\" . ucfirst($relatedTableSingular) . "::class, '$morphName');\n    }\n\n";
                }

                //for morphMany relationship

                $morphManyDetected = $this->detectMorphMany($otherTable, $tableName);
                if ($morphManyDetected) {
                    $relatedTablePlural = $this->formatRelationName($otherTable, true);
                    $morphName = $this->getMorphName($tableName);
                    $modelTemplate .= "    public function $relatedTablePlural()\n    {\n        return \$this->morphMany(\App\Models\\" . ucfirst($relatedTablePlural) . "::class, '$morphName');\n    }\n\n";
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

    protected function detectHasOneForeignKey($otherTable, $tableName) {
        $foreignKeys = $this->getForeignKeys($otherTable);

        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey->getForeignTableName() === $tableName) {
                if ($this->isColumnUnique($otherTable, $foreignKey->getLocalColumns()[0])) {
                    return $foreignKey->getLocalColumns()[0];
                }
            }
        }

        return null;
    }

    protected function isColumnUnique($table, $columnName) {
        $indexes = Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes($table);

        foreach ($indexes as $index) {
            if ($index->isUnique() && count($index->getColumns()) === 1 && $index->getColumns()[0] === $columnName) {
                return true;
            }
        }

        return false;
    }


    protected function detectPivotTable($table1, $table2) {
        $conn = Schema::getConnection()->getDoctrineSchemaManager();
        $allTables = $conn->listTableNames();
        $expectedPivotTableName = $this->formatPivotTableName($table1, $table2);

        if (in_array($expectedPivotTableName, $allTables, true)) {
            return $expectedPivotTableName;
        }

        return null;
    }

    protected function formatPivotTableName($table1, $table2) {
        $tables = [Str::singular($table1), Str::singular($table2)];
        sort($tables);

        return implode('_', $tables);
    }


    protected function detectMorphOne($otherTable, $tableName) {
        $columns = Schema::getColumnListing($otherTable);

        $morphIdColumn = Str::singular($tableName) . '_id';
        $morphTypeColumn = Str::singular($tableName) . '_type';

        if (in_array($morphIdColumn, $columns, true) && in_array($morphTypeColumn, $columns, true)) {
            return true;
        }

        return false;
    }

    protected function detectMorphMany($otherTable, $tableName) {
        $columns = Schema::getColumnListing($otherTable);

        $morphIdColumn = Str::singular($tableName) . '_id';
        $morphTypeColumn = Str::singular($tableName) . '_type';

        if (in_array($morphIdColumn, $columns, true) && in_array($morphTypeColumn, $columns, true)) {
            return true;
        }

        return false;
    }


}
