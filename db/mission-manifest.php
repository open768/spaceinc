<?php

//ORM is eloquent: https://github.com/illuminate/database
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

require_once cAppGlobals::$ckPhpInc . "/eloquentorm.php";

class cMissionColumns {
    const MISSION_ID = "mission_id";
    const ID = 'id';
    const SOL = 'sol';
    const RELATED_MISSION_NAME = "rmn";
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
        $sTableName = self::get_table_name();
        $sMissionTable = tblMissions::get_table_name();
        if ($sTableName !== $sMissionTable) {
            $poTable->integer(cMissionColumns::MISSION_ID)->index();
            $poTable->foreign(cMissionColumns::MISSION_ID)->references(cMissionColumns::ID)->on($sMissionTable);
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
        $lower = strtolower($psName);

        $sCacheKey = "$piMissionID:$lower";
        if (isset(self::$cache[$sCacheKey])) {
            $iRowID = self::$cache[$sCacheKey];
        } else {
            $oRow = self::where(self::NAME, $lower)->first();
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
        $sTableName = self::get_table_name();
        cDebug::extra_debug("creating table $sTableName");

        $poTable->increments(self::ID);
        $poTable->string(self::NAME);
        $poTable->unique([self::NAME]);

        self::add_mission_column($poTable);
    }

    public static function get_all_ids(int $piMission) {
        $oBuilder =  self::where(cMissionColumns::MISSION_ID, $piMission);
        $oCollection = cEloquentORM::pluck($oBuilder, tblID::ID);
        $aIDs = $oCollection->toArray();
        return $aIDs;
    }

    public static function get_matching_ids(int $piMission, array $paNames) {
        //cTracing::enter();

        // Convert sample type names to lowercase
        $aLowerNames = array_map('strtolower', $paNames);

        // Get the valid sample type names from the database
        $oBuilder = self::whereIn(tblID::NAME, $aLowerNames)
            ->where(cMissionColumns::MISSION_ID, $piMission);
        $oCollection = cEloquentORM::pluck($oBuilder, tblID::NAME);
        $aMatchedNames = $oCollection->toArray();

        // Check for invalid sample type names
        $aInvalidNames = array_diff($aLowerNames, $aMatchedNames);
        if (!empty($aInvalidNames)) {
            cTracing::leave();
            cDebug::error("Invalid names provided: " . implode(', ', $aInvalidNames));
            return;
        }

        // Get the IDs of the valid sample types
        $oBuilder = self::whereIn(tblID::NAME, $aMatchedNames)
            ->where(cMissionColumns::MISSION_ID, $piMission);
        $oCollection = cEloquentORM::pluck($oBuilder, tblID::ID);
        $aIDs = $oCollection->toArray();

        //cTracing::leave();
        return $aIDs;
    }
}

class tblMissions extends tblID {
}

class tblInstruments extends tblID {
    static function get_matching(int $piMission, string $psPattern) {
        cTracing::enter();

        $oBuilder = self::where(cMissionColumns::MISSION_ID, $piMission)
            ->where(self::NAME, 'LIKE', $psPattern);
        $oCollection = cEloquentORM::pluck($oBuilder, self::ID);
        $aMatchingIDs = $oCollection->toArray();
        cDebug::extra_debug("matching instruments:" . count($aMatchingIDs));
        return $aMatchingIDs;

        cTracing::leave();
    }
}

class tblSampleType extends tblID {
}

class cTableRelationships {
    const RELATION_INSTRUMENT = 'instrument';
    const RELATION_SAMPLE_TYPE = 'sampleType';
    const RELATION_MISSION = 'mission';
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

    const RELATED_INSTRUMENT_NAME = "rin";
    const RELATED_SAMPLE_TYPE_NAME = "rstn";

    //*******************************************************************************
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

    //*******************************************************************************
    // TODO: work in progress
    public static function get_all_data(int $piMission, int $piSol, ?int $piInstrument = null, ?eSpaceSampleTypes $piSampleTypeChooser = eSpaceSampleTypes::SAMPLE_NONTHUMBS, ?int $piThumbSampleType = null): Collection {
        cTracing::enter();

        /** @var Builder $oBuilder */
        $oBuilder =
            self::where(cMissionColumns::MISSION_ID, $piMission)
            ->where(cMissionColumns::SOL, $piSol);

        switch ($piSampleTypeChooser) {
            case eSpaceSampleTypes::SAMPLE_NONTHUMBS:
                $oBuilder = $oBuilder->whereNot(self::SAMPLE_TYPE_ID, $piThumbSampleType);
                break;
            case eSpaceSampleTypes::SAMPLE_THUMBS:
                $oBuilder = $oBuilder->where(self::SAMPLE_TYPE_ID, $piThumbSampleType);
        }
        if ($piInstrument !== null)
            $oBuilder = $oBuilder->where(self::INSTRUMENT_ID, $piInstrument);

        /** @var Collection $oCollection */
        $oCollection = cEloquentORM::get($oBuilder);
        cTracing::leave();
        return $oCollection;
    }

    //*******************************************************************************
    public static function get_sol_instruments(int $piMission, int $piSol) {
        cTracing::enter();
        $oBuilder = self::where(cMissionColumns::MISSION_ID, $piMission)
            ->where(cMissionColumns::SOL, $piSol)
            ->join(
                tblInstruments::get_table_name(),
                self::INSTRUMENT_ID,
                '=',
                tblInstruments::get_table_name() . '.' . cMissionColumns::ID
            )
            ->select(
                tblInstruments::get_table_name() . '.' . tblInstruments::ID . ' as id',
                tblInstruments::get_table_name() . '.' . tblInstruments::NAME . ' as name'
            )
            ->distinct()
            ->orderBy('name');

        cTracing::leave();
    }

    //*******************************************************************************
    public static function keep_instruments(int $piMission, array $paInstrNames) {
        cTracing::enter();

        //work out which IDs to remove
        cDebug::extra_debug("building lists");
        $aIDs = tblInstruments::get_matching_ids($piMission, $paInstrNames);
        $aAllIDs = tblInstruments::get_all_ids($piMission);
        $aDiff = array_diff($aAllIDs, $aIDs);

        if (count($aDiff) == 0) {
            cTracing::leave();
            cDebug::error("only instruments found were:" . implode(", ", $paInstrNames));
        }

        //delete the IDs from product table
        cDebug::extra_debug("deleting products with instruments");
        self::whereIn(self::INSTRUMENT_ID, $aDiff)
            ->where(cMissionColumns::MISSION_ID, $piMission)
            ->delete();

        //delete the IDs from instruments table
        cDebug::extra_debug("deleting instruments");
        tblInstruments::whereIn(tblID::ID, $aDiff)
            ->where(cMissionColumns::MISSION_ID, $piMission)
            ->delete();

        cTracing::leave();
    }


    /**
     * 
     * @param int $piMission mission ID
     * @return Builder 
     */
    public static function get_builder(int $piMission) {
        $oBuilder =
            self::where(cMissionColumns::MISSION_ID, $piMission)
            ->with(
                [
                    cTableRelationships::RELATION_INSTRUMENT,
                    cTableRelationships::RELATION_SAMPLE_TYPE,
                    cTableRelationships::RELATION_MISSION
                ]
            );
        return $oBuilder;
    }


    //*******************************************************************************
    //*******************************************************************************
    public function instrument() {
        return $this->belongsTo(tblInstruments::class, self::INSTRUMENT_ID);
    }
    public function mission() {
        return $this->belongsTo(tblMissions::class, cMissionColumns::MISSION_ID);
    }

    public function sampleType() {
        return $this->belongsTo(tblSampleType::class, self::SAMPLE_TYPE_ID);
    }

    public static function search_product(int $piMission, string $psSearch, array $paSampleTypeIDs) {
        cTracing::enter();

        $oBuilder = self::get_builder($piMission);
        $oBuilder = $oBuilder
            ->whereIn(self::SAMPLE_TYPE_ID, $paSampleTypeIDs)
            ->where(
                function (Builder $poQuery) use ($psSearch) {
                    $poQuery->where(self::PRODUCT, $psSearch)
                        ->orWhere(self::PRODUCT, 'LIKE', "%$psSearch%");
                }
            )
            ->with([cTableRelationships::RELATION_INSTRUMENT, cTableRelationships::RELATION_SAMPLE_TYPE, cTableRelationships::RELATION_MISSION])
            ->limit(1);


        $oCollection = cEloquentORM::get($oBuilder);

        cTracing::leave();
        return $oCollection;
    }
}

//#############################################################################################
class tblSolStatus extends tblModel {
    const LAST_INGESTED = "LAST_INGEST";

    static function create_table(Blueprint $poTable) {
        $poTable->integer(cMissionColumns::SOL)->index();
        $poTable->date(self::LAST_INGESTED);

        $poTable->unique([cMissionColumns::SOL]);
        self::add_mission_column($poTable);
    }

    static function get_last_updated(int $piMissionID, int $piSol): ?DateTime {
        $row = self::where(cMissionColumns::MISSION_ID, $piMissionID)
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

        self::updateOrCreate(
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
