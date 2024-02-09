<?php

namespace Jkbcoder\Schemator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SchematorCommand extends Command {
    protected $signature = 'schemator:generate  {--f|filament-options= : Filament options (generate, simple, deletes, view)} {--skip= : Tables to skip}  {--skip-default : Skip Laravel default tables} {--only=* : Specific tables to generate}';

    protected $description = 'Generate Eloquent models with relationships and optional Filament resources, supporting selective table generation and exclusion.';
    protected $createdMethods = [];

    public function handle() {

        $skipTables = $this->option('skip') ? explode(',', $this->option('skip')) : [];
        $skipDefault = $this->option('skip-default');

        $defaultTables = $skipDefault ? [
            'password_reset_tokens',
            'failed_jobs',
            'personal_access_tokens',
            'migrations'
        ] : [];

        $skipTables = array_merge($skipTables, $defaultTables);
        $onlyTables = $this->option('only');

        $filamentOptions = $this->option('filament-options');
        $filament = $filamentOptions !== null;

        $generate = str_contains($filamentOptions, 'g') || str_contains($filamentOptions, 'generate');
        $simple = str_contains($filamentOptions, 's') || str_contains($filamentOptions, 'simple');
        $softDeletes = str_contains($filamentOptions, 'd') || str_contains($filamentOptions, 'deletes');
        $view = str_contains($filamentOptions, 'v') || str_contains($filamentOptions, 'view');

        $this->info('Starting Schemator...');

        $tables = Schema::getConnection()->getDoctrineSchemaManager()->listTableNames();



        foreach ($tables as $table) {
            //Generate models only for specified tables
            if (!empty($onlyTables) && !in_array($table, $onlyTables)) {
                continue;
            }
            //for skipping tables
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


        if ($tableName === 'users' && $this->isFilamentInstalled()) {
            $modelTemplate = "<?php";
            $modelTemplate .= "\n\n/**\n * Created by Schemator Model.\n */\n";
            $modelTemplate .= "\nnamespace App\Models;\n\n";
            $modelTemplate .= "use Illuminate\Database\Eloquent\Factories\HasFactory;\n";
            $modelTemplate .= "use Illuminate\Foundation\Auth\User as Authenticatable;\n";
            $modelTemplate .= "use Illuminate\Notifications\Notifiable;\n";
            $modelTemplate .= "use Laravel\Sanctum\HasApiTokens;\n\n";
            $modelTemplate .= "class User extends Authenticatable\n{\n";
            $modelTemplate .= "use HasApiTokens, HasFactory, Notifiable;\n\n";
            $modelTemplate .= "protected \$table = '$tableName';\n    protected \$fillable = [$properties];\n\n";

        } else {
            $modelTemplate = "<?php";
            $modelTemplate .= "\n\n/**\n * Created by Schemator Model.\n */\n";
            $modelTemplate .= "\n\nnamespace App\Models;\n\nuse Illuminate\Database\Eloquent\Model;\n\n";
            $modelTemplate .= "class $className extends Model\n{\n  protected \$table = '$tableName';\n\n protected \$fillable = [$properties];\n\n";
        }



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
