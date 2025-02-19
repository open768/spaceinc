<?php

//ORM is eloquent: https://github.com/illuminate/database
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Date;

require_once cAppGlobals::$ckPhpInc . "/eloquentorm.php";

class cColumns {
    const MISSION_ID = "mission_id";
    const ID = 'id';
    const SOL = 'sol';
}

abstract class tblModel extends Model {
    public $timestamps = false;
    protected $connection = cMissionManifest::DBNAME;
    protected $guarded = [];

    static function get_table_name() {
        return (new static())->getTable();
    }
    public function getConnectionName() {
        return cMissionManifest::DBNAME;
    }

    abstract static function create_table(Blueprint $poTable);

    static function add_mission_column(Blueprint $poTable) {
        $sTableName = static::get_table_name();
        $sMissionTable = tblMissions::get_table_name();
        if ($sTableName !== $sMissionTable) {
            $poTable->integer(cColumns::MISSION_ID)->index();
            $poTable->foreign(cColumns::MISSION_ID)->references(cColumns::ID)->on($sMissionTable);
        }
    }
}

//#############################################################################################
class tblID extends tblModel {
    const ID = "id";
    const NAME = "name";

    static $cache = [];
    protected $fillable = [self::NAME];

    static function get_id($piMissionID, $psName) {
        $iRowID = null;
        $sCacheKey = "$piMissionID:$psName";
        if (isset(self::$cache[$sCacheKey])) {
            $iRowID = self::$cache[$sCacheKey];
        } else {
            $oRow = static::where(self::NAME, $psName)->first();
            if ($oRow !== null)
                $iRowID = $oRow->id;
            else {
                $oRow = new static();
                $sThisTable = $oRow->getTable();
                $sMissionTable = tblMissions::get_table_name();

                if ($sThisTable !== $sMissionTable) {
                    $oRow->mission_id = $piMissionID;
                }
                $oRow->name = $psName;
                $oRow->save();
                $iRowID = $oRow->id;
            }
            self::$cache[$sCacheKey] = $iRowID;
        }
        return $iRowID;
    }

    static function create_table(Blueprint $poTable) {
        $sTableName = static::get_table_name();
        cDebug::extra_debug("creating table $sTableName");

        $poTable->increments(self::ID);
        $poTable->string(self::NAME);
        $poTable->unique([self::NAME]);

        static::add_mission_column($poTable);
    }
}

class tblMissions extends tblID {
}

class tblInstruments extends tblID {
}
class tblSampleType extends tblID {
}

//#############################################################################################
//https://mars.nasa.gov/msl-raw-images/image/images_sol4413.json
class tblProducts extends tblModel {
    const INSTRUMENT_ID = 'instrument_id';
    const SAMPLE_TYPE_ID = 'sample_type_id';
    const SITE = 'site';
    const IMAGE_URL = 'image_url';
    const PRODUCT = 'product';
    const UTC_DATE = 'utc_date';
    const DRIVE = 'drive';

    static function create_table(Blueprint $poTable) {
        //create table structure
        $poTable->integer(cColumns::MISSION_ID);
        $poTable->increments(cColumns::ID);
        $poTable->integer(cColumns::SOL)->index();
        $poTable->integer(self::INSTRUMENT_ID)->index();
        $poTable->integer(self::SAMPLE_TYPE_ID)->index();
        $poTable->integer(self::SITE)->index();
        $poTable->text(self::IMAGE_URL);
        $poTable->text(self::PRODUCT)->index();
        $poTable->dateTime(self::UTC_DATE);
        $poTable->integer(self::DRIVE)->index();

        //add relationships
        $poTable->foreign(cColumns::MISSION_ID)->references(cColumns::ID)->on(tblMissions::get_table_name());
        $poTable->foreign(self::INSTRUMENT_ID)->references(cColumns::ID)->on(tblInstruments::get_table_name());
        $poTable->foreign(self::SAMPLE_TYPE_ID)->references(cColumns::ID)->on(tblSampleType::get_table_name());
        $poTable->unique([cColumns::SOL, self::INSTRUMENT_ID, self::PRODUCT, self::SAMPLE_TYPE_ID]);
    }

    static function reduce_url($psUrl) {
    }
}

//#############################################################################################
class tblSolStatus extends tblModel {
    const LAST_INGESTED = "LAST_INGEST";

    static function create_table(Blueprint $poTable) {
        $poTable->integer(cColumns::SOL)->index();
        $poTable->date(self::LAST_INGESTED);

        $poTable->unique([cColumns::SOL]);
        static::add_mission_column($poTable);
    }

    static function get_last_updated(int $piMissionID, int $piSol): ?DateTime {
        $row = self::get_sol($piMissionID, $piSol);

        if ($row) {
            $val = $row->{self::LAST_INGESTED};
            $dDate = new DateTime($val);
            return $dDate;
        }

        return null;
    }

    static function put_last_updated(int $piMissionID, int $piSol, $pdDate): void {
        $convertedDate = $pdDate;
        if (is_string($convertedDate)) {
            $convertedDate = new DateTime($pdDate);
        }

        $sDate = $convertedDate->format(cSqlLite::SQLITE_DATE_FORMAT);
        cDebug::write("mission:$piMissionID, sol:$piSol, date:" . $sDate);

        static::updateOrCreate(
            [
                cColumns::MISSION_ID => $piMissionID,
                cColumns::SOL => $piSol
            ],
            [
                self::LAST_INGESTED => $sDate
            ]
        );
    }

    static function get_sol(int $piMissionID, int $piSol) {
        $row = static::where(cColumns::MISSION_ID, $piMissionID)
            ->where(cColumns::SOL, $piSol)
            ->first();
        return $row;
    }

    static function is_sol_indexed(int $piMissionID, int $piSol): bool {
        $row = self::get_sol($piMissionID, $piSol);
        return ($row !== null);
    }
}

//#############################################################################################
class cMissionManifest {
    //https://mars.nasa.gov/msl-raw-images/image/images_sol4413.json
    const DBNAME = "manifest_orm.db";

    static $bAddedConnection = false;
    static $models = [
        tblProducts::class,
        tblInstruments::class,
        tblSampleType::class,
        tblMissions::class,
        tblSolStatus::class
    ];

    //**********************************************************************************************
    static function check_tables() {
        cDebug::enter();

        //check SOLS_TABLE_NAME table exists
        foreach (self::$models as $oModelClass) {
            $oInstance = (new $oModelClass);
            $sTableName = $oInstance->getTable();
            cEloquentORM::create_table(self::DBNAME, $sTableName, function ($poTable) use ($oModelClass) {
                $oModelClass::create_table($poTable);
            });
        }

        cDebug::leave();
    }

    static function empty_manifest() {
        cDebug::enter();
        foreach (self::$models as $oModelClass) {
            cDebug::write("truncating " . $oModelClass);
            $oModelClass::truncate();
        }
        cDebug::leave();
    }

    static function init() {
        if (!self::$bAddedConnection) {
            cEloquentORM::add_connection(self::DBNAME);
            self::$bAddedConnection = true;

            //check that tables have been created
            self::check_tables();
        }
    }
}
cMissionManifest::init();
