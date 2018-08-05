<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


class CoreServiceProvider extends ServiceProvider
{

    protected $defer = true;

    protected static $changes = array();
    protected static $tablesLocked = array('migrations');

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    public static function start() {

        // Get database 
        $database = config('database.connections.mysql.database');

        // Get tables 
        $tablesExistings = DB::select('SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE `TABLE_SCHEMA` ="'.$database.'"');
        $tablesExistings = array_column($tablesExistings, 'TABLE_NAME');
        $tablesExistings = array_diff($tablesExistings, self::$tablesLocked);

        // Get files model
        $modelsFiles = Storage::files('core/models');

        // Browse files
        foreach ($modelsFiles as $modelFile) {
            // Read file
            $modelDefinition = Yaml::parse(Storage::get($modelFile));
            $tableName = $modelDefinition['table'];
            if (!Schema::hasTable($tableName)) {
                array_push(self::$changes, array(
                    'title'             => 'create_table',
                    'model_definition'  => $modelDefinition
                ));
            } else {
                // Check fields
                foreach ($modelDefinition['fields'] as $fieldName => $fieldDefinition) {
                    // Check if field exists
                    if (!Schema::hasColumn($tableName, $fieldName)) {
                        array_push(self::$changes, array(
                            'title'             => 'create_field',
                            'model_definition'  => $modelDefinition,
                            'field_name'        => $fieldName,
                            'field_definition'  => $fieldDefinition
                        ));
                    } else {
                        // Check if field exists and need update
                        if (self::fieldNeedUpdate($tableName, $fieldName, $fieldDefinition)) {
                            array_push(self::$changes, array(
                                'title'             => 'update_field',
                                'model_definition'  => $modelDefinition,
                                'field_name'        => $fieldName,
                                'field_definition'  => $fieldDefinition
                            ));
                        }
                    }
                }
            }
        }    
        return self::$changes;
    }

    public static function getQuestion($changeKey) {
        $change = self::$changes[$changeKey];
        $question = '';
        switch ($change['title']) {
            case 'create_table':
                $tableName = $change['model_definition']['table'];
                $question = 'Would you realy create table <fg=red>'.$tableName.'</> ?';
                break;
            case 'create_field':
                $tableName = $change['model_definition']['table'];
                $fieldName = $change['field_name'];
                $question = 'Would you realy create field <fg=red>'.$fieldName.'</> for table <fg=red>'.$tableName.'</> ?';
                break;
            case 'update_field':
                $tableName = $change['model_definition']['table'];
                $fieldName = $change['field_name'];
                $question = 'Would you realy update field <fg=red>'.$fieldName.'</> for table <fg=red>'.$tableName.'</> ?';
                break;
        }
        return $question;
    }


    public static function addChange($change) {
        array_push(self::$changes, $change);
    }

    public static function makeChange($changeKey) { 
        $change = self::$changes[$changeKey];
        switch ($change['title']) {
            case 'create_table':
                self::createTable($change['model_definition']);
                break;
            case 'create_field':
                self::updateField($change['model_definition']['table'], $change['field_name'], $change['field_definition'], false);
                break;
            case 'update_field':
                self::updateField($change['model_definition']['table'], $change['field_name'], $change['field_definition'], true);
                break;
        }
    }

    public static function fieldNeedUpdate($tableName, $fieldName, $fieldDefinition) {
        $needUpdate = false;
        $fieldTypeName = $fieldDefinition['type']['name'];
        $fieldTypeParams = isset($fieldDefinition['type']['params'])?$fieldDefinition['type']['params']:NULL;
        $dbField = DB::connection()->getDoctrineColumn($tableName, $fieldName);
        
        switch ($fieldTypeName) {
            case 'string':
                $dbFieldLength = $dbField->getLength();
                $dbFieldNullable = !$dbField->getNotnull();
                if ((isset($fieldTypeParams['length']) && $fieldTypeParams['length'] != $dbFieldLength) || (!isset($fieldTypeParams) && $dbFieldLength != 255)) {
                    return true;
                }
                if ((isset($fieldTypeParams['nullable']) && ($fieldTypeParams['nullable'] != $dbFieldNullable)) || (!isset($fieldTypeParams['nullable']) && $dbFieldNullable == true)) {
                    return true;    
                }
                break;

            case 'text':
                $dbFieldNullable = !$dbField->getNotnull();
                if ((isset($fieldTypeParams['nullable']) && ($fieldTypeParams['nullable'] != $dbFieldNullable)) || (!isset($fieldTypeParams['nullable']) && $dbFieldNullable == true)) {
                    return true;    
                }
                break;
            
            default:
                # code...
                break;
        }
        return $needUpdate;
    }

    public static function createTable($modelDefinition) {
        $tableName = $modelDefinition['table'];
        // Create table
        Schema::create($tableName, function (Blueprint $table) {
            $table->increments('id');
        });
        // Create fields
        if (Schema::hasTable($tableName)) {
            foreach ($modelDefinition['fields'] as $fieldName => $fieldDefinition) {
                self::updateField($tableName, $fieldName, $fieldDefinition, false);
            }
            self::updateField($tableName, 'create_at', array('type' => array('name' => 'dateTime')), false);
            self::updateField($tableName, 'update_at', array('type' => array('name' => 'dateTime')), false);
        }
    }

    public static function updateField($tableName, $fieldName, $fieldDefinition, $update=false) {
         Schema::table($tableName, function (Blueprint $table) use ($fieldName, $fieldDefinition, $update) {
            $fieldTypeDefiniton = $fieldDefinition['type'];
            
            // Read params
            $fieldTypeParams = array();
            if (isset($fieldTypeDefiniton['params']) && is_array($fieldTypeDefiniton['params'])) {
                $fieldTypeParams = $fieldTypeDefiniton['params'];
            }
            
            $fieldTypeName = $fieldTypeDefiniton['name'];
            switch ($fieldTypeName) {
                case 'string':
                    $field = $table->string(
                        $fieldName, 
                        isset($fieldTypeParams['length'])?$fieldTypeParams['length']:255
                    );
                    break;
                case 'text':
                case 'time':
                case 'dateTime':
                    $func = $fieldTypeName;
                    $field = $table->$func(
                        $fieldName
                    );
                    break;
            }
            // Commons specs
            $field->nullable(isset($fieldTypeParams['nullable']) && $fieldTypeParams['nullable'] == true);

            // Change ??
            if (isset($field) && $update) $field->change();
        });
    }
}
