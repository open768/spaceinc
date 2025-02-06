<?php

//ORM is eloquent: https://github.com/illuminate/database
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

require_once cAppGlobals::$ckPhpInc . "/eloquentorm.php";

class cColumns {
    const MISSION_ID = "mission_id";
    const ID = 'id';
    const SOL = 'sol';
}

class tblModel extends Model {
    public $timestamps = false;
    static function get_table_name() {
        return (new static())->getTable();
    }
}

//#############################################################################################
class tblSols extends tblModel {
    const LAST_UPDATED = 'last_updated';
    const CATALOG_URL = 'catalog_url';

    static function create_table(Blueprint $poTable) {
        $poTable->increments(cColumns::ID);
        $poTable->integer(cColumns::SOL)->index();
        $poTable->date(self::LAST_UPDATED);
        $poTable->integer(self::CATALOG_URL);

        $poTable->unique([cColumns::SOL]);
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

        $sMissionTable = tblMissions::get_table_name();
        if ($sTableName !== $sMissionTable) {
            $poTable->integer(cColumns::MISSION_ID)->index();
            $poTable->foreign(cColumns::MISSION_ID)->references(cColumns::ID)->on($sMissionTable);
        }
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
    const UPDATED_AT = 'updated_at';
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
        $poTable->dateTime(self::UPDATED_AT);
        $poTable->integer(self::DRIVE)->index();

        //add relationships
        $poTable->foreign(cColumns::MISSION_ID)->references(cColumns::ID)->on(tblMissions::get_table_name());
        $poTable->foreign(self::INSTRUMENT_ID)->references(cColumns::ID)->on(tblInstruments::get_table_name());
        $poTable->unique([cColumns::SOL, self::INSTRUMENT_ID, self::PRODUCT, self::SAMPLE_TYPE_ID]);
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
        tblSols::truncate();
        tblProducts::truncate();
        tblInstruments::truncate();
        tblSampleType::truncate();
        tblMissions::truncate();
    }

    static function init() {
        if (self::$capsuleManager == null) {
            $oCapsule = cEloquentORM::init_db(self::DBNAME);
            self::$capsuleManager = $oCapsule;
        }
        //create the table if it does not exist
        self::check_tables();
    }

    static function add_to_index($poItem) {
        // Convert sampletype and instrument to integer lookups
        $iSampleTypeID = tblSampleType::get_id(null, $poItem->sampletype);
        $iInstrumentID = tblInstruments::get_id(null, $poItem->instrument);

        // Create a new row in tblProducts
        $oProduct = new tblProducts();
        $oProduct->{cColumns::MISSION_ID} = $poItem->mission_id;
        $oProduct->{cColumns::SOL} = $poItem->sol;
        $oProduct->{tblProducts::INSTRUMENT_ID} = $iInstrumentID;
        $oProduct->{tblProducts::SAMPLE_TYPE_ID} = $iSampleTypeID;
        $oProduct->{tblProducts::SITE} = $poItem->site;
        $oProduct->{tblProducts::IMAGE_URL} = $poItem->image_url;
        $oProduct->{tblProducts::PRODUCT} = $poItem->product;
        $oProduct->{tblProducts::UTC_DATE} = $poItem->utc_date;
        $oProduct->{tblProducts::DRIVE} = $poItem->drive;
        $oProduct->save();
    }
}
cMissionManifest::init();
