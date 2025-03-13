<?php

//ORM is eloquent: https://github.com/illuminate/database
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Date;

require_once cAppGlobals::$ckPhpInc . "/eloquentorm.php";

class cMissionColumns {
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
            $poTable->integer(cMissionColumns::MISSION_ID)->index();
            $poTable->foreign(cMissionColumns::MISSION_ID)->references(cMissionColumns::ID)->on($sMissionTable);
        }
    }
}

//#############################################################################################
class tblSols extends tblModel {
    const LAST_UPDATED = 'last_updated';
    const CATALOG_URL = 'catalog_url';

    static function create_table(Blueprint $poTable) {
        $poTable->increments(cMissionColumns::ID);
        $poTable->integer(cMissionColumns::SOL)->index();
        $poTable->date(self::LAST_UPDATED);
        $poTable->integer(self::CATALOG_URL);

        $poTable->unique([cMissionColumns::SOL]);
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
        $lower = strtolower($psName);

        $sCacheKey = "$piMissionID:$lower";
        if (isset(self::$cache[$sCacheKey])) {
            $iRowID = self::$cache[$sCacheKey];
        } else {
            $oRow = static::where(self::NAME, $lower)->first();
            if ($oRow !== null)
                $iRowID = $oRow->id;
            else {
                $oRow = new static();
                $sThisTable = $oRow->getTable();
                $sMissionTable = tblMissions::get_table_name();

                if ($sThisTable !== $sMissionTable) {
                    $oRow->mission_id = $piMissionID;
                }
                $oRow->name = $lower;
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

    public static function get_all_ids(int $piMission) {
        $aIDs =  static::where(cMissionColumns::MISSION_ID, $piMission)
            ->pluck(tblID::ID)
            ->toArray();
        return $aIDs;
    }

    public static function get_ids(int $piMission, array $paNames) {
        //cTracing::enter();

        // Convert sample type names to lowercase
        $aLowerNames = array_map('strtolower', $paNames);

        // Get the valid sample type names from the database
        $aMatchedNames = static::whereIn(tblID::NAME, $aLowerNames)
            ->where(cMissionColumns::MISSION_ID, $piMission)
            ->pluck(tblID::NAME)
            ->toArray();

        // Check for invalid sample type names
        $aInvalidNames = array_diff($aLowerNames, $aMatchedNames);
        if (!empty($aInvalidNames)) {
            cTracing::leave();
            cDebug::error("Invalid names provided: " . implode(', ', $aInvalidNames));
            return;
        }

        // Get the IDs of the valid sample types
        $aIDs = static::whereIn(tblID::NAME, $aMatchedNames)
            ->where(cMissionColumns::MISSION_ID, $piMission)
            ->pluck(tblID::ID)
            ->toArray();

        //cTracing::leave();
        return $aIDs;
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
        $poTable->integer(cMissionColumns::MISSION_ID);
        $poTable->increments(cMissionColumns::ID);
        $poTable->integer(cMissionColumns::SOL)->index();
        $poTable->integer(self::INSTRUMENT_ID)->index();
        $poTable->integer(self::SAMPLE_TYPE_ID)->index();
        $poTable->integer(self::SITE)->index();
        $poTable->text(self::IMAGE_URL);
        $poTable->text(self::PRODUCT)->index();
        $poTable->dateTime(self::UTC_DATE);
        $poTable->integer(self::DRIVE)->index();

        //add relationships
        $poTable->foreign(cMissionColumns::MISSION_ID)->references(cMissionColumns::ID)->on(tblMissions::get_table_name());
        $poTable->foreign(self::INSTRUMENT_ID)->references(cMissionColumns::ID)->on(tblInstruments::get_table_name());
        $poTable->foreign(self::SAMPLE_TYPE_ID)->references(cMissionColumns::ID)->on(tblSampleType::get_table_name());
        $poTable->unique([cMissionColumns::SOL, self::INSTRUMENT_ID, self::PRODUCT, self::SAMPLE_TYPE_ID]);
    }

    static function get_all_data(int $piMission, int $piSol, ?string $psInstrument = null, eSpaceSampleTypes $piSampleType = eSpaceSampleTypes::SAMPLE_ALL): array {
        tblProducts::where(cMissionColumns::MISSION_ID);
    }

    static function remove_sample_types(int $piMission, array $pasample_types) {
        cTracing::enter();

        cDebug::extra_debug("building lists");
        $aIDs = tblSampleType::get_ids($piMission, $pasample_types);

        // remove the offending sample types from product table
        cDebug::extra_debug("removing from products table");
        tblProducts::whereIn(tblProducts::SAMPLE_TYPE_ID, $aIDs)
            ->where(cMissionColumns::MISSION_ID, $piMission)
            ->delete();

        // remove the offending sample types from sampletypes table
        cDebug::extra_debug("removing from sampletypes table");
        tblSampleType::whereIn(tblID::ID, $aIDs)
            ->where(cMissionColumns::MISSION_ID, $piMission)
            ->delete();

        cTracing::leave();
    }

    public static function keep_instruments(int $piMission, array $paInstrNames) {
        cTracing::enter();

        //work out which IDs to remove
        cDebug::extra_debug("building lists");
        $aIDs = tblInstruments::get_ids($piMission, $paInstrNames);
        $aAllIDs = tblInstruments::get_all_ids($piMission);
        $aDiff = array_diff($aAllIDs, $aIDs);

        if (count($aDiff) == 0) {
            cTracing::leave();
            cDebug::error("only instruments found were:" . implode(", ", $paInstrNames));
        }

        //delete the IDs from product table
        cDebug::extra_debug("deleting products with instruments");
        tblProducts::whereIn(tblProducts::INSTRUMENT_ID, $aDiff)
            ->where(cMissionColumns::MISSION_ID, $piMission)
            ->delete();

        //delete the IDs from instruments table
        cDebug::extra_debug("deleting instruments");
        tblInstruments::whereIn(tblID::ID, $aDiff)
            ->where(cMissionColumns::MISSION_ID, $piMission)
            ->delete();

        cTracing::leave();
    }
}

//#############################################################################################
class tblSolStatus extends tblModel {
    const LAST_INGESTED = "LAST_INGEST";

    static function create_table(Blueprint $poTable) {
        $poTable->integer(cMissionColumns::SOL)->index();
        $poTable->date(self::LAST_INGESTED);

        $poTable->unique([cMissionColumns::SOL]);
        static::add_mission_column($poTable);
    }

    static function get_last_updated(int $piMissionID, int $piSol): ?DateTime {
        $row = static::where(cMissionColumns::MISSION_ID, $piMissionID)
            ->where(cMissionColumns::SOL, $piSol)
            ->first();

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
                cMissionColumns::MISSION_ID => $piMissionID,
                cMissionColumns::SOL => $piSol
            ],
            [
                self::LAST_INGESTED => $sDate
            ]
        );
    }
}

//#############################################################################################
/** 
 * creates tables need to store manifest data
 */
class cMissionManifest {
    const DBNAME = "manifest_orm.db";

    static $bAddedConnection = false;
    static $models = [
        tblSols::class,
        tblProducts::class,
        tblInstruments::class,
        tblSampleType::class,
        tblMissions::class,
        tblSolStatus::class
    ];

    //**********************************************************************************************
    static function check_tables() {
        cTracing::enter();

        //check SOLS_TABLE_NAME table exists
        foreach (self::$models as $oModelClass) {
            $oInstance = (new $oModelClass);
            $sTableName = $oInstance->getTable();
            cEloquentORM::create_table(self::DBNAME, $sTableName, function ($poTable) use ($oModelClass) {
                $oModelClass::create_table($poTable);
            });
        }

        cTracing::leave();
    }

    static function empty_manifest() {
        cTracing::enter();
        foreach (self::$models as $oModelClass) {
            cDebug::write("truncating " . $oModelClass);
            $oModelClass::truncate();
        }
        cTracing::leave();
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
