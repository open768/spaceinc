<?php

/**************************************************************************
Copyright (C) Chicken Katsu 2013 -2024

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk
// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
 **************************************************************************/

require_once cAppGlobals::$spaceInc . "/missions/rover.php";
require_once cAppGlobals::$spaceInc . "/curiosity/instrument.php";

//#################################################################################
class cCuriosityManifestIndexStatus {
    const INDEXING_STATUS_KEY = "indexing:status";
    const INDEXING_LASTSOL_KEY = "indexing:lastsol";
    const LAST_SOL_PREFIX = "indexing:lastsol:";
    const STATUS_NOT_STARTED = -1;
    const STATUS_COMPLETE = "complete";

    /**  @var cObjstoreDB $oDB */
    private static $oDB = null;

    //*****************************************************************************
    static function init_db() {
        if (self::$oDB === null)
            self::$oDB = new cOBjStoreDB(cSpaceRealms::ROVER_MANIFEST, cSpaceTables::ROVER_MANIFEST);
    }

    //*****************************************************************************
    static function clear_status() {
        cDebug::extra_debug("deleting status");
        $oDB = self::$oDB;
        $oDB->kill(self::INDEXING_STATUS_KEY);
        $oDB->kill(self::INDEXING_LASTSOL_KEY);
    }

    static function get_status() {
        $oDB = self::$oDB;
        $sStatus = $oDB->get(self::INDEXING_STATUS_KEY);
        return $sStatus;
    }
    static function put_status($psStatus) {
        $oDB = self::$oDB;
        $oDB->put(self::INDEXING_STATUS_KEY, $psStatus, true);
    }

    static function get_last_indexed_sol() {
        $oDB = self::$oDB;
        $sLastSol = $oDB->get(self::INDEXING_LASTSOL_KEY);
        return $sLastSol;
    }
    static function put_last_indexed_sol(string $psSol) {
        $oDB = self::$oDB;
        $oDB->put(self::INDEXING_LASTSOL_KEY, $psSol, true);
    }

    //-----------------------------------------------------------
    static function get_sol_last_updated(string $psSol) {
        $oDB = self::$oDB;
        $sKey = self::LAST_SOL_PREFIX . $psSol;
        $sLastUpdated = $oDB->get($sKey);
        return $sLastUpdated;
    }

    static function put_sol_last_updated($psSol, $psDate) {
        $oDB = self::$oDB;
        $sKey = self::LAST_SOL_PREFIX . $psSol;
        $oDB->put($sKey, $psDate, true);
    }

    static function kill_sol_last_updated(string $psSol) {
        if (cCommon::is_string_empty($psSol))
            cDebug::error("sol must be provided");
        $sKey = self::LAST_SOL_PREFIX . $psSol;
        $oDB = self::$oDB;
        $oDB->kill($sKey);
    }
}
cCuriosityManifestIndexStatus::init_db();

class cManifestSolData {
    public string $sol;
    public array  $data = [];
}

//#################################################################################
class cCuriosityManifestIndex {
    const DB_FILENAME = "curiositymanifest.db";
    const MANIFEST_TABLE = "manifest";


    const COL_MISSION = "M";
    const COL_SOL = "SO";
    const COL_INSTR = "I";
    const COL_PRODUCT = "P";
    const COL_IMAGE_URL = "U";
    const COL_SAMPLE_TYPE = "SA";
    const COL_DATE_ADDED = "DA";

    const SAMPLE_THUMB = "thumbnail";
    const SAMPLE_ALL = 1;
    const SAMPLE_THUMBS = 2;
    const SAMPLE_NONTHUMBS = 3;


    /**  @var cSQLLite $oSQLDB */
    private static $oSQLDB = null;
    private static $cached_all_data = null;


    //*****************************************************************************
    //* DB stuff
    //*****************************************************************************
    static function init_db() {
        //-------------- open SQLlite DB
        /** @var cSQLLite $oSqLDB  */
        $oSqLDB = self::$oSQLDB;
        if ($oSqLDB == null) {
            cDebug::extra_debug("opening cSqlLite database");
            $oSqLDB = new cSqlLite(self::DB_FILENAME);
            self::$oSQLDB = $oSqLDB;
        }
        self::pr_create_table();
    }
    static function get_db() {
        return self::$oSQLDB;
    }

    //*****************************************************************************
    static function replace_sql_params($psSQL) {
        $sSQL = str_replace(":table", self::MANIFEST_TABLE, $psSQL);
        $sSQL = str_replace(":mission_col", self::COL_MISSION, $sSQL);
        $sSQL = str_replace(":sol_col", self::COL_SOL, $sSQL);
        $sSQL = str_replace(":sample_col", self::COL_SAMPLE_TYPE, $sSQL);
        $sSQL = str_replace(":instr_col", self::COL_INSTR, $sSQL);
        $sSQL = str_replace(":product_col", self::COL_PRODUCT, $sSQL);
        $sSQL = str_replace(":url_col", self::COL_IMAGE_URL, $sSQL);
        $sSQL = str_replace(":date_col", self::COL_DATE_ADDED, $sSQL);

        return $sSQL;
    }

    //*****************************************************************************
    private static function pr_create_table() {
        $oSqLDB = self::$oSQLDB;
        //-------------- check table
        $bTableExists = $oSqLDB->table_exists(self::MANIFEST_TABLE);
        if ($bTableExists)
            return;
        else
            cCuriosityManifestIndexStatus::clear_status();

        //-------------create TABLE
        cDebug::extra_debug("table doesnt exist " . self::MANIFEST_TABLE);
        $sSQL =
            "CREATE TABLE `:table` ( " .
            "rowid INTEGER PRIMARY KEY, :mission_col TEXT not null, :sol_col TEXT not null, :instr_col TEXT not null, :product_col TEXT not null, :url_col TEXT not null, :sample_col TEXT, :date_col INTEGER, " .
            "CONSTRAINT cmanifest UNIQUE (:mission_col, :sol_col, :instr_col, :product_col) " .
            ")";
        $sSQL = self::replace_sql_params($sSQL);
        $oSqLDB->querySQL($sSQL);
        cDebug::extra_debug("table created");

        //-------------create INDEX
        $sSQL = "CREATE INDEX idx_manifest on ':table' ( :mission_col, :sol_col, :instr_col )";
        $sSQL = self::replace_sql_params($sSQL);
        $oSqLDB->querySQL($sSQL);
        cDebug::extra_debug("main index created");

        //-------------create INDEX
        $sSQL = "CREATE INDEX idx_manifest_date on ':table' ( :date_col )";
        $sSQL = self::replace_sql_params($sSQL);
        $oSqLDB->querySQL($sSQL);
        cDebug::extra_debug("secondary index created");

        //-------------create INDEX
        $sSQL = "CREATE INDEX idx_msproduct on ':table' ( :mission_col, :sol_col, :product_col )";
        $sSQL = self::replace_sql_params($sSQL);
        $oSqLDB->querySQL($sSQL);
        cDebug::extra_debug("secondary index created");

        //-------------create INDEX
        $sSQL = "CREATE INDEX idx_mproduct on ':table' ( :mission_col, :product_col )";
        $sSQL = self::replace_sql_params($sSQL);
        $oSqLDB->querySQL($sSQL);
        cDebug::extra_debug("secondary index created");
    }

    //*****************************************************************************
    // Index functions
    //*****************************************************************************
    static function updateIndex() {
        cDebug::enter();
        cDebug::on(); //turn off extra debugging

        //----------get manifest
        cDebug::write("getting sol Manifest");
        $oManifest = cCuriosityManifest::getManifest();

        //----------get status from odb
        $sStatus = cCuriosityManifestIndexStatus::get_status();

        if ($sStatus === cCuriosityManifestIndexStatus::STATUS_COMPLETE) {
            $sLastIndexedSol = cCuriosityManifestIndexStatus::get_last_indexed_sol();
            $sLatestManifestSol = $oManifest->latest_sol;
            cDebug::write("last indexed sol was: $sLastIndexedSol, latest manifest sol: $sLatestManifestSol");
            if ($sLastIndexedSol >= $sLatestManifestSol)
                cDebug::error("indexing allready complete");
        }

        //----------get last indexed sol  odb
        $sLastSol = cCuriosityManifestIndexStatus::get_last_indexed_sol();
        if ($sLastSol == null) $sLastSol = -1;
        cDebug::write("indexing starting at sol: $sLastSol");

        cDebug::write("processing sol Manifest");
        $aSols = $oManifest->sols;
        ksort($aSols, SORT_NUMERIC);


        //---------------iterate manifest
        foreach ($aSols as $number => $oSol) {
            $sSol = $oSol->sol;
            $bReindex = false;

            //- - - - - - - - -check when SOL was  last updated
            $sStoredLastUpdated = cCuriosityManifestIndexStatus::get_sol_last_updated($sSol);
            $sManifestLastUpdated = $oSol->last_updated;
            //cDebug::write("stored lastindex:$sStoredLastUpdated, manifest date:$sManifestLastUpdated");
            if ($sStoredLastUpdated == null)
                $bReindex = true;
            elseif ($sStoredLastUpdated < $sManifestLastUpdated)
                $bReindex = true;

            //---------------------check if the row needs reindexing
            if (!$bReindex) $bReindex = $sSol > $sLastSol;
            if (!$bReindex) continue;

            //-------perform the index
            self::index_sol($sSol, $sManifestLastUpdated, $bReindex);
        }
        cCuriosityManifestIndexStatus::put_status(cCuriosityManifestIndexStatus::STATUS_COMPLETE);

        //----------------compress database
        cDebug::write("compresssing database");
        cSqlLiteUtils::vacuum(self::DB_FILENAME);
        cDebug::write("done");
        cDebug::leave();
    }

    //*****************************************************************************
    static function index_sol(string $psSol, $sLastUpdatedValue, bool $pbReindex) {
        $oSqlDB = self::$oSQLDB;
        cDebug::extra_debug("indexing sol:$psSol");

        if ($pbReindex) self::delete_sol_index($psSol);

        $bCheckExpiry = !$pbReindex;
        $oSolData = cCuriosityManifest::getSolData($psSol, $bCheckExpiry); //this is needed

        $aImages = $oSolData->images;
        if ($aImages === null) cDebug::error("no image data");

        $oSqlDB->begin_transaction(); {
            foreach ($aImages as $sKey => $oImgData)
                self::add_to_index($psSol, $oImgData);
            $oSqlDB->commit();
        }


        if (cDebug::is_debugging())
            cPageOutput::scroll_to_bottom();


        //update the status
        cCuriosityManifestIndexStatus::put_last_indexed_sol($psSol);
        cCuriosityManifestIndexStatus::put_sol_last_updated($psSol, $sLastUpdatedValue);
    }

    //*****************************************************************************
    static function delete_sol_index(string $psSol) {
        if (cCommon::is_string_empty($psSol))
            cDebug::error("sol must be provided");

        cDebug::extra_debug("deleting Sol $psSol index");
        $sSQL = "DELETE FROM `:table` where :sol_col=:sol";
        $sSQL = self::replace_sql_params($sSQL);

        $oSqlDB = self::$oSQLDB;
        $oStmt = $oSqlDB->prepare($sSQL);
        $oStmt->bindValue(":sol", $psSol);

        $oSqlDB->exec_stmt($oStmt); //handles retries and errors

        cCuriosityManifestIndexStatus::kill_sol_last_updated($psSol);
    }

    //*****************************************************************************
    static function add_to_index($psSol, $poItem) {
        //cDebug::enter();
        //--------------get the data out of the item
        $sSampleType = $poItem->sampleType;
        $sInstr = $poItem->instrument;
        $sProduct = $poItem->itemName;
        $sUrl = $poItem->urlList;
        $sUtc = $poItem->utc;
        $iUtc = cCommon::UTC_to_epoch($sUtc);

        //--------------get the data out of the item
        cDebug::extra_debug("adding to index: $psSol, $sInstr, $sProduct, $sSampleType");
        if (cDebug::is_debugging())   cCommon::flushprint(".");
        $sSQL = "INSERT INTO `:table` (:mission_col, :sol_col, :instr_col, :product_col, :url_col, :sample_col ,:date_col) VALUES (:mission, :sol, :instr, :product, :url, :sample , :d_val)";
        $sSQL = self::replace_sql_params($sSQL);

        //--------------put it into the database
        /** @var cSQLLite $oSqlDB  */
        $oSqlDB = self::$oSQLDB;
        $oStmt = $oSqlDB->prepare($sSQL);
        $oStmt->bindValue(":mission", cSpaceMissions::CURIOSITY);
        $oStmt->bindValue(":sol", $psSol);
        $oStmt->bindValue(":instr", $sInstr);
        $oStmt->bindValue(":product", $sProduct);
        $oStmt->bindValue(":url", $sUrl);
        $oStmt->bindValue(":sample", $sSampleType);
        $oStmt->bindValue(":d_val", $iUtc);


        $oSqlDB->exec_stmt($oStmt); //handles retries and errors

        //cDebug::leave();
    }

    //******************************************************************************************* */
    static function deleteEntireIndex() {
        cDebug::enter();
        //delete everything
        cDebug::write("deleting from sql");
        $sSQL = "DELETE from `:table`";
        $sSQL = self::replace_sql_params($sSQL);
        $oSqlDB = self::$oSQLDB;
        $oStmt = $oSqlDB->prepare($sSQL);
        $oSqlDB->exec_stmt($oStmt); //handles retries and errors

        cDebug::write("vacuuming db");
        cSqlLiteUtils::vacuum(self::DB_FILENAME);

        //update the status
        cDebug::write("updating status");
        cCuriosityManifestIndexStatus::clear_status();

        cDebug::write("done");
        cDebug::leave();
    }

    //*****************************************************************************
    // getters functions
    //*****************************************************************************
    static function reindex_if_needed(string $psSol) {
        cDebug::enter();

        cDebug::write("checking Sol $psSol");
        $bIndexIt = false;

        //----------------is it in the index?
        $oManifestData = cCuriosityManifest::getSolEntry($psSol);
        if ($oManifestData == null) {
            cDebug::write("$psSol is not in the manifest at all");
            return null;
        }
        $sManUtc = $oManifestData->last_updated;


        $slastUpdated = cCuriosityManifestIndexStatus::get_sol_last_updated($psSol);
        if ($slastUpdated === null) {
            cDebug::write("sol $psSol is not in the index");
            $bIndexIt = true;
        } elseif ($slastUpdated < $sManUtc) {
            cDebug::write("sol $psSol needs to be reindexed");
            $bIndexIt = true;
        } else
            cDebug::write("no reindexing needed - data is up-to-date");

        //----------------Not in index, then index it by jeeves
        if ($bIndexIt) {
            cDebug::write("indexing sol $psSol");
            self::index_sol($psSol, $sManUtc, true);
        }
        cDebug::leave();
    }

    //*****************************************************************************
    static function get_all_sol_data(string $psSol, ?string $psInstrument = null, string $piSampleType = self::SAMPLE_ALL) {
        cDebug::enter();
        cDebug::write("attempting to get data for $psSol");

        //---------------- check if Sol is in the index or needs updating
        self::reindex_if_needed($psSol);
        cDebug::write("sol $psSol is in the index");

        //---------------- build SQL where
        $sWhere = ":mission_col=:mission AND :sol_col=:sol";
        switch ($piSampleType) {
            case self::SAMPLE_NONTHUMBS:
                $sWhere = "$sWhere AND :sample_col != :sample_type";
                break;
            case self::SAMPLE_THUMBS:
                $sWhere = "$sWhere AND :sample_col = :sample_type";
        }

        if ($psInstrument !== null)
            $sWhere = "$sWhere AND :instr_col = :instr";

        //---------------- build SQL
        $sSQL = "SELECT :mission_col, :sol_col, :instr_col, :product_col, :url_col, :sample_col, :date_col from `:table` where $sWhere ORDER BY :sol_col, :instr_col, :product_col";
        $sSQL = self::replace_sql_params($sSQL);

        //---------------- get SQL Statement
        $oSqlDB = self::$oSQLDB;
        $oBinds = new cSqlBinds(); {
            $oBinds->add_bind(":sol", $psSol);

            $oBinds->add_bind(":sol", $psSol);
            $oBinds->add_bind(":mission", cSpaceMissions::CURIOSITY);
            if ($psInstrument !== null) $oBinds->add_bind(":instr", $psInstrument);
            $oBinds->add_bind(":mission", cSpaceMissions::CURIOSITY);
            $oBinds->add_bind(":sample_type", cCuriosityManifest::SAMPLE_TYPE_THUMBNAIL);
        }

        //---------------- exec SQL
        $aSQLData = $oSqlDB->prep_exec_fetch($sSQL, $oBinds);

        //-------------return cSpaceProductData
        $oOut = new cManifestSolData;
        $oOut->sol = $psSol;
        foreach ($aSQLData as $oItem) {
            $aItem = (array)$oItem;
            $oNewItem = new cSpaceProductData; {
                $oNewItem->mission = $aItem[self::COL_MISSION];
                $oNewItem->sol = $aItem[self::COL_SOL];
                $oNewItem->instr = $aItem[self::COL_INSTR];
                $oNewItem->product = $aItem[self::COL_PRODUCT];
                $oNewItem->sample_type = $aItem[self::COL_SAMPLE_TYPE];
                $oNewItem->image_url = $aItem[self::COL_IMAGE_URL];
                $oNewItem->utc_date = $aItem[self::COL_DATE_ADDED];
                $oOut->data[] = $oNewItem;
            }
        }


        cDebug::leave();
        return $oOut;
    }
}
cCuriosityManifestIndex::init_db();

//###################################################################################
//#
//###################################################################################
class cCuriosityManifestUtils {
    const TIMESLOT = 10;


    //******************************************************************************
    /**
     * returns a random image
     * @param string $sIntrumentPattern 
     * @param int $piHowmany 
     * @return array
     */
    static function get_random_images(string $sIntrumentPattern, int $piHowmany) {
        cDebug::enter();

        //----------------prepare statement
        $sSQL = "SELECT :mission_col,:sol_col,:instr_col,:product_col,:url_col FROM `:table` WHERE ( :mission_col=:mission AND  :instr_col LIKE :pattern AND :sample_col != 'thumbnail')  ORDER BY RANDOM() LIMIT :howmany";
        $sSQL = cCuriosityManifestIndex::replace_sql_params($sSQL);
        $sSQL = str_replace(":howmany", $piHowmany, $sSQL);     //cant bind LIMIT values

        $oSqlDB = cCuriosityManifestIndex::get_db();
        $oBinds = new cSqlBinds; {
            $oBinds->add_bind(":mission", cSpaceMissions::CURIOSITY);
            $oBinds->add_bind(":pattern", $sIntrumentPattern);
        }

        //----------------fetch results
        $aResults = $oSqlDB->prep_exec_fetch($sSQL, $oBinds);
        $aOut = [];
        foreach ($aResults as $oRow) {
            $aRow = (array) $oRow;
            $sMission = $aRow[cCuriosityManifestIndex::COL_MISSION];
            $sSol = $aRow[cCuriosityManifestIndex::COL_SOL];
            $sInstr = $aRow[cCuriosityManifestIndex::COL_INSTR];
            $sProduct = $aRow[cCuriosityManifestIndex::COL_PRODUCT];
            $sUrl = $aRow[cCuriosityManifestIndex::COL_IMAGE_URL];
            $oProduct = new cRoverManifestImage($sMission, $sSol, $sInstr, $sProduct, $sUrl);
            $aOut[] = $oProduct;
        }

        cDebug::leave();
        return $aOut;
    }

    //********************************************************
    static function search_for_product(string $psProduct) {
        cDebug::enter();
        $sSQL = "SELECT :mission_col,:sol_col,:instr_col,:product_col,:url_col FROM `:table` WHERE ( :mission_col=:mission AND :product_col=:search AND :sample_col != 'thumbnail')  LIMIT 1";
        $sSQL = cCuriosityManifestIndex::replace_sql_params($sSQL);

        $oSqlDB = cCuriosityManifestIndex::get_db();
        $oBinds = new cSqlBinds; {
            $oBinds->add_bind(":mission", cSpaceMissions::CURIOSITY);
            $oBinds->add_bind(":search", $psProduct);
        }
        $aResult = $oSqlDB->prep_exec_fetch($sSQL, $oBinds);
        if ($aResult == null) cDebug::error("nothing matched");

        $aRow = (array)$aResult[0];
        $oOut = new cSpaceProductData; {
            $oOut->sol = $aRow[cCuriosityManifestIndex::COL_SOL];
            $oOut->product = $aRow[cCuriosityManifestIndex::COL_PRODUCT];
            $oOut->instr = $aRow[cCuriosityManifestIndex::COL_INSTR];
            $oOut->image_url = $aRow[cCuriosityManifestIndex::COL_IMAGE_URL];
        }

        cDebug::leave();
        return $oOut;
    }

    //********************************************************
    static function get_calendar(string $psSol) {
        cDebug::write("getting instruments");

        $oInstruments = cCuriosityInstrument::getInstrumentList();
        /** @var cManifestSolData $oData */
        $oData = cCuriosityManifestIndex::get_all_sol_data($psSol, null, cCuriosityManifestIndex::SAMPLE_NONTHUMBS);
        $aManData = $oData->data;

        $oData = (object) [
            "sol" => $psSol,
            "cal" => [],
            "instr" => $oInstruments
        ];

        cDebug::write("processing images");
        $aCal = [];
        /** @var cSpaceProductData $oItem */
        foreach ($aManData as $oItem) {
            //ignore thumbnails
            if ($oItem->sample_type === "thumbnail")
                continue;

            //Get instruments
            $sInstr = $oItem->instr;
            $sInstrAbbr = cCuriosityInstrument::getInstrumentAbbr($sInstr);

            //work out the date
            $epoch = $oItem->utc_date;
            $dDate = new DateTime("@$epoch");
            $sDate = $dDate->format("d-m-y");
            if (!array_key_exists($sDate, $aCal)) $aCal[$sDate] = [];

            //work out the time key
            $sHour = $dDate->format("H");
            $sMin = $dDate->format("i");
            $sMin =  intdiv($sMin, self::TIMESLOT) * self::TIMESLOT;
            $sMin = str_pad($sMin, 2, "0", STR_PAD_LEFT);
            $sTimeKey = "$sHour:$sMin";

            //debug
            /*
            $sOriginal = $dDate->format("d-m-y H:i");
            cDebug::write("original: $sOriginal -> $sDate $sTimeKey");
            */

            //add the entry to the array
            if (!array_key_exists($sTimeKey, $aCal[$sDate])) $aCal[$sDate][$sTimeKey] = [];
            $aCal[$sDate][$sTimeKey][] = (object)["i" => $sInstrAbbr, "d" => $epoch, "p" => $oItem->product];
        }
        $oData->cal = $aCal;

        return $oData;
    }

    //********************************************************
    static function get_instruments_for_sol($psSol): array {
        cDebug::enter();

        cCuriosityManifestIndex::reindex_if_needed($psSol);

        // build SQL
        $sSQL = "SELECT DISTINCT :instr_col from `:table` WHERE :mission_col=:mission  AND :sol_col=:sol ORDER BY :instr_col";
        $sSQL = cCuriosityManifestIndex::replace_sql_params($sSQL);

        //build stmt
        $oBinds = new cSqlBinds; {
            $oBinds->add_bind(":mission", cSpaceMissions::CURIOSITY);
            $oBinds->add_bind(":sol", $psSol);
        }

        $oSqlDB = cCuriosityManifestIndex::get_db();
        $aSQLData = $oSqlDB->prep_exec_fetch($sSQL, $oBinds);

        //process results
        $aData = [];
        foreach ($aSQLData as $oItem) {
            $aItem = (array) $oItem;
            $aData[] = $aItem[cCuriosityManifestIndex::COL_INSTR];
        }

        cDebug::leave();
        return $aData;
    }

    //*********************************************************************
    static function get_products(string $psSol, ?string $psInstr = null): array {
        // read the img files for the products
        cDebug::enter();
        $oRawData = cCuriosityManifestIndex::get_all_sol_data($psSol, $psInstr, cCuriosityManifestIndex::SAMPLE_NONTHUMBS);
        $aProducts = [];
        foreach ($oRawData->data as $oItem)
            $aProducts[] = $oItem->product;
        cDebug::leave();
        return $aProducts;
    }

    //*********************************************************************
    static function count_products_in_sol($psSol, ?string $psInstr, $piSampleType): int {
        cDebug::enter();

        //-----------------------build SQL
        $sSQL = "SELECT count(*) as count from `:table` WHERE :mission_col=:mission AND :sol_col=:sol";
        switch ($piSampleType) {
            case cCuriosityManifestIndex::SAMPLE_NONTHUMBS:
                $sSQL = "$sSQL AND :sample_col != :sample_type";
                break;
            case cCuriosityManifestIndex::SAMPLE_THUMBS:
                $sSQL = "$sSQL AND :sample_col = :sample_type";
        }

        $sSQL = cCuriosityManifestIndex::replace_sql_params($sSQL);

        //-----------------------execute SQL
        $oBinds = new cSqlBinds; {
            $oBinds->add_bind(":mission", cSpaceMissions::CURIOSITY);
            $oBinds->add_bind(":sol", $psSol);
            $oBinds->add_bind(":sample_type", cCuriosityManifest::SAMPLE_TYPE_THUMBNAIL);
        }

        //-----------------------execute SQL
        $oSqlDB = cCuriosityManifestIndex::get_db();
        $aResults = $oSqlDB->prep_exec_fetch($sSQL, $oBinds);
        if ($aResults == null)
            cDebug::error("no products found");
        $aRow = $aResults[0];
        $iCount = $aRow["count"];
        //cDebug::write("there are $iCount matching rows");

        cDebug::leave();
        return $iCount;
    }

    //*********************************************************************
    static function get_product_index(string $psProduct): cSpaceProductData {
        cDebug::enter();

        $sSQL = "SELECT rowid,:mission_col,:instr_col,:product_col FROM `:table` WHERE :mission_col=:mission AND :product_col=:product";
        $sSQL = cCuriosityManifestIndex::replace_sql_params($sSQL);

        $oBind = new cSqlBinds; {
            $oBind->add_bind(":mission", cSpaceMissions::CURIOSITY);
            $oBind->add_bind(":product", $psProduct);
        }
        $oDB = cCuriosityManifestIndex::get_db();
        $aResults = $oDB->prep_exec_fetch($sSQL, $oBind);

        if ($aResults == null || count($aResults) == 0)
            cDebug::error("unable to find product $psProduct");
        $aRow = (array) $aResults[0];
        $iRowID = $aRow["rowid"];
        cDebug::write("rowid for $psProduct is $iRowID");

        $oData = new cSpaceProductData; {
            $oData->rowid = $iRowID;
            $oData->mission = $aRow[cCuriosityManifestIndex::COL_MISSION];
            $oData->instr = $aRow[cCuriosityManifestIndex::COL_INSTR];
            $oData->product = $aRow[cCuriosityManifestIndex::COL_PRODUCT];
        }

        cDebug::leave();
        return $oData;
    }

    //*********************************************************************
    static function get_sol_indexed_product(string $psSol, string $piIndex, int $piSampleType) {
        cDebug::enter();

        //-----------------------build SQL
        $sWhere = ":mission_col=:mission AND :sol_col=:sol";
        switch ($piSampleType) {
            case cCuriosityManifestIndex::SAMPLE_NONTHUMBS:
                $sWhere = "$sWhere AND :sample_col != :sample_type";
                break;
            case cCuriosityManifestIndex::SAMPLE_THUMBS:
                $sWhere = "$sWhere AND :sample_col = :sample_type";
        }
        $iOffset = $piIndex - 1;
        $sSQL = "SELECT :mission_col,:sol_col,:instr_col,:product_col FROM `:table` WHERE $sWhere ORDER BY :product_col LIMIT 1 OFFSET $iOffset";
        $sSQL = cCuriosityManifestIndex::replace_sql_params($sSQL);

        //-----------------------execute SQL
        $oBinds = new cSqlBinds; {
            $oBinds->add_bind(":mission", cSpaceMissions::CURIOSITY);
            $oBinds->add_bind(":sol", $psSol);
            $oBinds->add_bind(":sample_type", cCuriosityManifest::SAMPLE_TYPE_THUMBNAIL);
        }
        $oSqlDB = cCuriosityManifestIndex::get_db();

        //-----------------------get results
        $aResults  = $oSqlDB->prep_exec_fetch($sSQL, $oBinds);
        if (!$aResults) cDebug::error("no results returned");
        $aRow = $aResults[0];
        $oProduct = new cSpaceProductData;
        $oProduct->sol = $aRow[cCuriosityManifestIndex::COL_SOL];
        $oProduct->instr = $aRow[cCuriosityManifestIndex::COL_INSTR];
        $oProduct->product = $aRow[cCuriosityManifestIndex::COL_PRODUCT];

        cDebug::leave();
        return $oProduct;
    }

    //*********************************************************************
    static function find_sequential_product(string $psProduct, string $psDirection, bool $pbAnyInstrument = false) {
        cDebug::enter();

        //get the table row number of the input product 
        $oRow = self::get_product_index($psProduct);
        $iProductRow = $oRow->rowid;
        $sInstrument = $oRow->instr;

        //prepare the sql
        $sWhere = ":mission_col=:mission AND :sample_col <> :thumbnail";
        if (! $pbAnyInstrument)
            $sWhere = "$sWhere AND :instr_col=:instr";

        switch ($psDirection) {
            case cSpaceConstants::DIRECTION_PREVIOUS:
                $sWhere = "$sWhere AND rowid < $iProductRow ORDER BY rowid DESC";
                break;
            case cSpaceConstants::DIRECTION_NEXT:
                $sWhere = "$sWhere AND rowid > $iProductRow ORDER BY rowid ASC";
                break;
            default:
                cDebug::error("unknown direction $psDirection");
        }
        $sSQL = "SELECT rowid,:mission_col,:sol_col,:instr_col,:product_col from `:table` WHERE $sWhere LIMIT 1";
        $sSQL = cCuriosityManifestIndex::replace_sql_params($sSQL);

        //bind data
        $oBinds = new cSqlBinds; {
            $oBinds->add_bind(":mission", cSpaceMissions::CURIOSITY);
            $oBinds->add_bind(":thumbnail", cCuriosityManifest::SAMPLE_TYPE_THUMBNAIL);

            if (!$pbAnyInstrument)
                $oBinds->add_bind(":instr", $sInstrument);
        }

        //-----------exec the SQL
        $oDB = cCuriosityManifestIndex::get_db();
        $aRows = $oDB->prep_exec_fetch($sSQL, $oBinds);

        //parse the results
        if ($aRows == null || count($aRows) == 0) cDebug::error("nothing found - reached the end of the index?");
        $aRow = (array) $aRows[0];
        $sOutProduct = $aRow[cCuriosityManifestIndex::COL_PRODUCT];
        if ($sOutProduct == $psProduct) cDebug::error("same product returned $psProduct");

        $oProduct = new cSpaceProductData;
        $oProduct->mission = $aRow[cCuriosityManifestIndex::COL_MISSION];
        $oProduct->sol = $aRow[cCuriosityManifestIndex::COL_SOL];
        $oProduct->instr = $aRow[cCuriosityManifestIndex::COL_INSTR];
        $oProduct->product = $aRow[cCuriosityManifestIndex::COL_PRODUCT];
        $oProduct->rowid = $aRow["rowid"];

        cDebug::leave();
        return $oProduct;
    }

    //*************************************************************************
    /**
     * this function attempts to find the next or previous product for a particular instrument
     * its similar to find_time_sequential_product() but more challenging as adjacent sols may not have the same instrument.
     * 
     * @param string $psSol 
     * @param string $psInstr 
     * @param string $psProduct 
     * @param string $psDirection 
     * @return void 
     */
    static function find_instr_sequential_product(string $psSol, string $psInstr, string $psProduct, string $psDirection) {
    }
}

//###############################################################################
class cCuriosityManifest {
    const MANIFEST_CACHE = 2 * 3600;    //2 hour
    const FEED_URL = "https://mars.jpl.nasa.gov/msl-raw-images/image/image_manifest.json";
    const SOL_URL = "https://mars.jpl.nasa.gov/msl-raw-images/image/images_sol";
    const SOL_CACHE = 7 * 24 * 3600;    //1 week
    const FEED_SLEEP = 200; //milliseconds
    const SAMPLE_TYPE_THUMBNAIL = "thumbnail";
    static $cached_manifest = null;



    //*****************************************************************************
    static function getManifest() {
        $oResult = null;
        cDebug::enter();

        $oResult = self::$cached_manifest;
        if ($oResult == null) {
            $oCache = new cCachedHttp(); {
                $oCache->CACHE_EXPIRY = self::MANIFEST_CACHE;
                $oResult = $oCache->getCachedJson(self::FEED_URL);
            }
            self::$cached_manifest = $oResult;
        }
        cDebug::leave();
        return $oResult;
    }

    //*****************************************************************************
    static function getSolEntry(string $psSol) {
        cDebug::enter();
        //---- get the manifest
        $oManifest = self::getManifest();
        $aSols = $oManifest->sols;
        if (!is_numeric($psSol)) cDebug::error("not an integer");

        $iSol = (int) $psSol; //make sure we have an integer
        ksort($aSols, SORT_NUMERIC);

        $oMatched = null;

        //----array is not keyed on sol, so we have to find the sol
        foreach ($aSols as $oSol) {
            if ($oSol->sol === $iSol) {
                $oMatched = $oSol;
                break;
            }
        }

        if ($oMatched == null) cDebug::write("unable to find the SOL entry");
        cDebug::leave();
        return $oMatched;
    }

    //*****************************************************************************
    static function getSolJsonUrl($psSol) {
        cDebug::enter();

        $oSol = self::getSolEntry($psSol);
        $sUrl = $oSol->catalog_url;

        cDebug::leave();
        return $sUrl;
    }

    //*****************************************************************************
    public static function getSolData($psSol, $pbCheckExpiry = true) {
        cDebug::enter();

        $sUrl = self::getSolJsonUrl($psSol);
        if (cCommon::is_string_empty($sUrl)) cDebug::error("empty url for $psSol");

        cDebug::extra_debug("Getting all sol data for sol $psSol");

        $oCache = new cCachedHttp(); {
            $oCache->CACHE_EXPIRY = self::SOL_CACHE;
            $bIsCached = $oCache->is_cached($sUrl, $pbCheckExpiry);
            $oResult = $oCache->getCachedJson($sUrl, $pbCheckExpiry);
        }

        if (cDebug::is_debugging() && !$bIsCached) {
            cDebug::write("<p> -- sleeping for " . self::FEED_SLEEP . " ms\n");
            usleep(self::FEED_SLEEP);
        }

        cDebug::leave();
        return $oResult;
    }

    //*****************************************************************************
    public static function clearSolDataCache($psSol) {
        cDebug::enter();

        cDebug::write("clearing sol cache : " . $psSol);
        $sUrl = self::getSolJsonUrl($psSol);
        $oCache = new cCachedHttp();
        $oCache->deleteCachedURL($sUrl);

        cDebug::leave();
    }
}
