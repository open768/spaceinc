<?php

//ORM is eloquent: https://github.com/illuminate/database
use Illuminate\Database\Capsule\Manager as CapsuleManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class cEloquentORM {
    static function create_table(string $psTableName, Closure $pfnCreate) {
        $oManager = cMissionManifest::$capsuleManager;
        $oSchemaBuilder = $oManager->schema();

        cDebug::extra_debug("checking table exists  " . $psTableName);
        $bHasTable = $oSchemaBuilder->hasTable($psTableName);
        if (!$bHasTable) {
            //create table
            $oSchemaBuilder->create($psTableName, function ($table) use ($pfnCreate) {
                $pfnCreate($table);
            });
            cDebug::extra_debug("created table " . $psTableName);
        }
    }
}

//#############################################################################################
class tblSols extends Model {
    static function create_table(Blueprint $table) {
        $table->integer('sol')->index();
        $table->date('last_updated');
        $table->integer('catalog_url');

        $table->unique(['sol']);
    }
}

//#############################################################################################
class tblID extends Model {
    static function create_table(Blueprint $table) {
        $table->integer('id')->index();
        $table->string('name');
        $table->unique(['name']);
    }
}
class tblInstruments extends tblID {
}
class tblSampleType extends tblID {
}

//#############################################################################################
//https://mars.nasa.gov/msl-raw-images/image/images_sol4413.json
class tblProducts extends Model {
    static function create_table(Blueprint $table) {
        //create table structure
        $table->integer('id');
        $table->integer('sol')->index();
        $table->integer('instrument_id')->index();
        $table->integer('sample_type_id')->index();
        $table->integer('site')->index();
        $table->text('image_url');
        $table->text('product')->index();
        $table->dateTime('utc-date');
        $table->integer('drive')->index();

        //add relationships
        $table->foreign('instrument_id')->references('id')->on('tblInstruments');
        $table->unique(['sol', 'instrument_id', 'product', 'sample_type_id']);
    }
}

//#############################################################################################
class cMissionManifest {
    const DBNAME = "manifest_orm.db";

    static $capsuleManager = null;

    //**********************************************************************************************
    static function check_tables() {
        cDebug::enter();

        /** @var CapsuleManager $oManager*/
        $oManager = self::$capsuleManager;
        $oSchemaBuilder = $oManager->schema();

        //check SOLS_TABLE_NAME table exists
        cEloquentORM::create_table("tblSols", function ($table) {
            tblSols::create_table($table);
        });
        cEloquentORM::create_table("tblProducts", function ($table) {
            tblProducts::create_table($table);
        });
        cEloquentORM::create_table("tblInstruments", function ($table) {
            tblInstruments::create_table($table);
        });
        cEloquentORM::create_table("tblSampleType", function ($table) {
            tblSampleType::create_table($table);
        });

        cDebug::leave();
    }

    //**********************************************************************************************
    static function init() {
        //check that database class has been loaded - redundant as composer would throw an error if it wasnt
        $classname = "Illuminate\Database\Capsule\Manager";
        if (!class_exists($classname))
            cDebug::error("check composer - unable to find class $classname");
        cDebug::extra_debug("found class $classname");

        //check pdo extension is loaded
        if (!extension_loaded("pdo_sqlite"))
            cDebug::error("pdo_sqlite extension is not loaded");

        //create sqlite database if it does not exist
        $oDB = new cSqlLite(self::DBNAME);
        $sDBPath = $oDB->path;
        cDebug::write("DB path is $sDBPath");

        //connect the ORM to a SQL lite database
        if (self::$capsuleManager == null) {

            $oManager = new CapsuleManager();
            $oManager->addConnection([
                'driver' => 'sqlite',
                'database' => $sDBPath,
                'prefix' => ''
            ]);
            $oManager->setAsGlobal();       //should be optional but isnt
            $oManager->bootEloquent();
            self::$capsuleManager = $oManager;
            cDebug::extra_debug("started eloquent - db is $sDBPath");

            //create the table if it does not exist
            self::check_tables();
        }
    }
}
cMissionManifest::init();
