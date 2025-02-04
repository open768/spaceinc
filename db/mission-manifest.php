<?php

//ORM is eloquent: https://github.com/illuminate/database
use Illuminate\Database\Capsule\Manager as CapsuleManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

require_once cAppGlobals::$ckPhpInc . "/eloquentorm.php";

//#############################################################################################
class tblSols extends Model {
    static function create_table(Blueprint $poTable) {
        $poTable->integer('sol')->index();
        $poTable->date('last_updated');
        $poTable->integer('catalog_url');

        $poTable->unique(['sol']);
    }
}

//#############################################################################################
class tblID extends Model {
    static $cache = [];
    protected $fillable = ['name'];
    public $timestamps = false;

    static function get_id($psName) {
        cDebug::enter();

        $row_id = null;
        if (isset(self::$cache[$psName])) {
            cDebug::extra_debug("$psName is in cache");
            $row_id = self::$cache[$psName];
        } else {
            cDebug::extra_debug("$psName not in cache");
            $row = static::where('name', $psName)->first();
            if ($row !== null) {
                cDebug::extra_debug("$psName in database");
                $row_id = $row->id;
            } else {
                cDebug::extra_debug("$psName not in database");
                $oRow = new static();
                $oRow->name = $psName;
                $oRow->save();
                $row_id = $oRow->id;
            }
            self::$cache[$psName] = $row_id;
        }
        $classname = get_called_class();
        cDebug::extra_debug("$classname:  $psName => $row_id");

        cDebug::leave();
        return $row_id;
    }

    static function create_table(Blueprint $poTable) {
        cDebug::enter();
        $sTableName = get_called_class();
        cDebug::extra_debug("creating table $sTableName");

        $poTable->integer('id')->index();
        $poTable->string('name');
        $poTable->unique(['name']);

        if ($sTableName !== "tblMissions") {
            $poTable->integer('mission_id')->index();
            $poTable->foreign('mission_id')->references('id')->on('tblMissions');
        }
        cDebug::leave();
    }
}

class tblMissions extends tblID {
    protected $table = "tblMissions";
}
class tblInstruments extends tblID {
    protected $table = "tblInstruments";
}
class tblSampleType extends tblID {
    protected $table = "tblSampleType";
}

//#############################################################################################
//https://mars.nasa.gov/msl-raw-images/image/images_sol4413.json
class tblProducts extends Model {
    static function create_table(Blueprint $poTable) {
        //create table structure
        $poTable->integer('mission_id');
        $poTable->integer('id');
        $poTable->integer('sol')->index();
        $poTable->integer('instrument_id')->index();
        $poTable->integer('sample_type_id')->index();
        $poTable->integer('site')->index();
        $poTable->text('image_url');
        $poTable->text('product')->index();
        $poTable->dateTime('utc-date');
        $poTable->integer('drive')->index();

        //add relationships
        $poTable->foreign('mission_id')->references('id')->on('tblMissions');
        $poTable->foreign('instrument_id')->references('id')->on('tblInstruments');
        $poTable->unique(['sol', 'instrument_id', 'product', 'sample_type_id']);
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

        //check SOLS_TABLE_NAME table exists
        $aClasses = [
            tblSols::class,
            tblProducts::class,
            tblInstruments::class,
            tblSampleType::class,
            tblMissions::class
        ];

        foreach ($aClasses as $oModelClass) {
            $oInstance = (new $oModelClass);
            $sTableName = $oInstance->getTable();
            cEloquentORM::create_table($sTableName, function ($poTable) use ($oModelClass) {
                $oModelClass::create_table($poTable);
            });
        }

        cDebug::leave();
    }

    static function empty_manifest() {
        cDebug::enter();
        $oManager = self::$capsuleManager;
        $oManager->table("tblSols")->delete();
        $oManager->table("tblProducts")->delete();
        $oManager->table("tblInstruments")->delete();
        $oManager->table("tblSampleType")->delete();
        $oManager->table("tblMissions")->delete();
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
