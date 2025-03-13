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
require_once cAppGlobals::$spaceInc . "/db/mission-manifest.php";
require_once cAppGlobals::$spaceInc . "/misc/constants.php";



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


    /**  @var cSQLLite $oSQLDB */
    private static $oSQLDB = null;

    //*****************************************************************************
    //* DB stuff
    //*****************************************************************************
    static function init_db() {
        //-------------- open SQLlite DB
        /** @var cSQLLite $oSqLDB  */
        $oSqLDB = self::$oSQLDB;
        if ($oSqLDB == null) {
            $oSqLDB = new cSqlLite(self::DB_FILENAME);
            self::$oSQLDB = $oSqLDB;
        }
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
    // getters functions
    //*****************************************************************************

    //*****************************************************************************
    static function get_all_sol_data(string $psSol, ?string $psInstrument = null, eSpaceSampleTypes $piSampleType = eSpaceSampleTypes::SAMPLE_ALL) {
        cTracing::enter();
        cDebug::write("attempting to get data for $psSol");

        //---------------- check if Sol is in the index or needs updating
        cCuriosityORMManifestIndexer::reindex_if_needed($psSol);
        cDebug::write("sol $psSol is in the index");

        //---------------- build SQL where
        $sWhere = ":mission_col=:mission AND :sol_col=:sol";
        switch ($piSampleType) {
            case eSpaceSampleTypes::SAMPLE_NONTHUMBS:
                $sWhere = "$sWhere AND :sample_col != :sample_type";
                break;
            case eSpaceSampleTypes::SAMPLE_THUMBS:
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
            $oBinds->add_bind(":sample_type", cCuriosityProduct::THUMB_SAMPLE_TYPE);
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


        cTracing::leave();
        return $oOut;
    }
}
cCuriosityManifestIndex::init_db();

//###################################################################################
//#
//###################################################################################
class   cCuriosityManifestUtils {
    const TIMESLOT = 10;


    //******************************************************************************
    /**
     * returns a random image
     * @param string $sIntrumentPattern 
     * @param int $piHowmany 
     * @return array
     */
    static function get_random_images(string $sIntrumentPattern, int $piHowmany) {
        cTracing::enter();

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

        cTracing::leave();
        return $aOut;
    }

    //********************************************************
    static function search_for_product(string $psProduct) {
        cTracing::enter();
        cDebug::extra_debug("looking for product: $psProduct");
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

        cTracing::leave();
        return $oOut;
    }

    //********************************************************
    static function get_calendar(string $psSol) {
        cDebug::write("getting instruments");

        $oInstruments = cCuriosityInstrument::getInstrumentList();
        /** @var cManifestSolData $oData */
        $oData = cCuriosityManifestIndex::get_all_sol_data($psSol, null, eSpaceSampleTypes::SAMPLE_NONTHUMBS);
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
            if ($oItem->sample_type === cCuriosityProduct::THUMB_SAMPLE_TYPE)
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
        cTracing::enter();

        cCuriosityORMManifestIndexer::reindex_if_needed($psSol);

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

        cTracing::leave();
        return $aData;
    }

    //*********************************************************************
    static function get_products(string $psSol, ?string $psInstr = null): array {
        // read the img files for the products
        cTracing::enter();
        $oRawData = cCuriosityManifestIndex::get_all_sol_data($psSol, $psInstr, eSpaceSampleTypes::SAMPLE_NONTHUMBS);
        $aProducts = [];
        foreach ($oRawData->data as $oItem)
            $aProducts[] = $oItem->product;
        cTracing::leave();
        return $aProducts;
    }

    //*********************************************************************
    static function count_products_in_sol($psSol, ?string $psInstr, $piSampleType): int {
        cTracing::enter();

        //-----------------------build SQL
        $sSQL = "SELECT count(*) as count from `:table` WHERE :mission_col=:mission AND :sol_col=:sol";
        switch ($piSampleType) {
            case eSpaceSampleTypes::SAMPLE_NONTHUMBS:
                $sSQL = "$sSQL AND :sample_col != :sample_type";
                break;
            case eSpaceSampleTypes::SAMPLE_THUMBS:
                $sSQL = "$sSQL AND :sample_col = :sample_type";
        }

        $sSQL = cCuriosityManifestIndex::replace_sql_params($sSQL);

        //-----------------------execute SQL
        $oBinds = new cSqlBinds; {
            $oBinds->add_bind(":mission", cSpaceMissions::CURIOSITY);
            $oBinds->add_bind(":sol", $psSol);
            $oBinds->add_bind(":sample_type", cCuriosityProduct::THUMB_SAMPLE_TYPE);
        }

        //-----------------------execute SQL
        $oSqlDB = cCuriosityManifestIndex::get_db();
        $aResults = $oSqlDB->prep_exec_fetch($sSQL, $oBinds);
        if ($aResults == null)
            cDebug::error("no products found");
        $aRow = $aResults[0];
        $iCount = $aRow["count"];
        //cDebug::write("there are $iCount matching rows");

        cTracing::leave();
        return $iCount;
    }

    //*********************************************************************
    static function get_product_index(string $psProduct): cSpaceProductData {
        cTracing::enter();

        //********** construct SQL
        $sSQL = "SELECT rowid,:mission_col,:instr_col,:product_col FROM `:table` WHERE :mission_col=:mission AND :product_col=:product";
        $sSQL = cCuriosityManifestIndex::replace_sql_params($sSQL);

        $oBind = new cSqlBinds; {
            $oBind->add_bind(":mission", cSpaceMissions::CURIOSITY);
            $oBind->add_bind(":product", $psProduct);
        }

        //********** exec SQL
        $oDB = cCuriosityManifestIndex::get_db();
        $aResults = $oDB->prep_exec_fetch($sSQL, $oBind);

        //********** no results(how did i get here if the product is not known?)
        if ($aResults == null || count($aResults) == 0)
            cDebug::error("unable to find product $psProduct");

        //********** return result
        $aRow = (array) $aResults[0];
        $iRowID = $aRow["rowid"];
        cDebug::write("rowid for $psProduct is $iRowID");

        $oData = new cSpaceProductData; {
            $oData->rowid = $iRowID;
            $oData->mission = $aRow[cCuriosityManifestIndex::COL_MISSION];
            $oData->instr = $aRow[cCuriosityManifestIndex::COL_INSTR];
            $oData->product = $aRow[cCuriosityManifestIndex::COL_PRODUCT];
        }

        cTracing::leave();
        return $oData;
    }

    //*********************************************************************
    static function get_sol_indexed_product(string $psSol, string $piIndex, int $piSampleType) {
        cTracing::enter();

        //-----------------------build SQL
        $sWhere = ":mission_col=:mission AND :sol_col=:sol";
        switch ($piSampleType) {
            case eSpaceSampleTypes::SAMPLE_NONTHUMBS:
                $sWhere = "$sWhere AND :sample_col != :sample_type";
                break;
            case eSpaceSampleTypes::SAMPLE_THUMBS:
                $sWhere = "$sWhere AND :sample_col = :sample_type";
        }
        $iOffset = $piIndex - 1;
        $sSQL = "SELECT :mission_col,:sol_col,:instr_col,:product_col FROM `:table` WHERE $sWhere ORDER BY :product_col LIMIT 1 OFFSET $iOffset";
        $sSQL = cCuriosityManifestIndex::replace_sql_params($sSQL);

        //-----------------------execute SQL
        $oBinds = new cSqlBinds; {
            $oBinds->add_bind(":mission", cSpaceMissions::CURIOSITY);
            $oBinds->add_bind(":sol", $psSol);
            $oBinds->add_bind(":sample_type", cCuriosityProduct::THUMB_SAMPLE_TYPE);
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

        cTracing::leave();
        return $oProduct;
    }

    //*********************************************************************
    static function find_sequential_product(string $psProduct, string $psDirection, bool $pbAnyInstrument = false) {
        cTracing::enter();

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
            $oBinds->add_bind(":thumbnail", cCuriosityProduct::THUMB_SAMPLE_TYPE);

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

        cTracing::leave();
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
class cCuriosityJPLManifest {
    const MANIFEST_CACHE = 4 * 3600;    //4 hour
    const FEED_URL = "https://mars.jpl.nasa.gov/msl-raw-images/image/image_manifest.json";
    const SOL_URL = "https://mars.jpl.nasa.gov/msl-raw-images/image/images_sol";
    const SOL_CACHE = 7 * 24 * 3600;    //1 week
    const FEED_SLEEP = 100; //milliseconds
    static $cached_manifest = null;
    private static $dont_check_sol_index = false;



    //*****************************************************************************
    static function getManifest() {
        $oResult = null;
        cTracing::enter();

        $oResult = self::$cached_manifest;
        if ($oResult == null) {
            $oCache = new cCachedHttp(); {
                $oCache->CACHE_EXPIRY = self::MANIFEST_CACHE;
                $oResult = $oCache->getCachedJson(self::FEED_URL);
            }
            self::$cached_manifest = $oResult;
        }
        cTracing::leave();
        return $oResult;
    }

    //*****************************************************************************
    static function getSolEntry(string $psSol) {
        cTracing::enter();
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
        cTracing::leave();
        return $oMatched;
    }

    //*****************************************************************************
    static function getSolJsonUrl($psSol) {
        cTracing::enter();

        $oSol = self::getSolEntry($psSol);
        $sUrl = $oSol->catalog_url;

        cTracing::leave();
        return $sUrl;
    }

    //*****************************************************************************
    public static function getSolRawData($psSol, $pbCheckExpiry = true, $pbIndexMissing = false) {
        cTracing::enter();

        $sUrl = self::getSolJsonUrl($psSol);
        if (cCommon::is_string_empty($sUrl)) cDebug::error("empty url for $psSol");

        cDebug::extra_debug("Getting all sol data for sol $psSol");
        $oCache = new cCachedHttp(); {
            $oCache->CACHE_EXPIRY = self::SOL_CACHE;
            $bIsCached = $oCache->is_cached($sUrl, $pbCheckExpiry);
            $oResult = $oCache->getCachedJson($sUrl, $pbCheckExpiry);
        }

        if ($oResult === null)
            cDebug::write("nothing found - sol doesnt exist at $sUrl");
        elseif (! self::$dont_check_sol_index) {
            $bInIndex = cCuriosityORMManifest::is_sol_in_index($psSol);
            if (!$bInIndex && $pbIndexMissing) {
                self::$dont_check_sol_index = true;
                cDebug::extra_debug_warning("sol found, but isnt in manifestindex.. ");
                try {
                    cCuriosityORMManifestIndexer::reindex_if_needed($psSol);
                } finally {
                    self::$dont_check_sol_index = false;
                }
            }
        }



        if (cDebug::is_debugging() && !$bIsCached) {
            cDebug::write("<p> -- sleeping for " . self::FEED_SLEEP . " ms\n");
            usleep(self::FEED_SLEEP);
        }

        cTracing::leave();
        return $oResult;
    }

    //*****************************************************************************
    public static function clearSolDataCache($psSol) {
        cTracing::enter();

        cDebug::write("clearing sol cache : " . $psSol);
        $sUrl = self::getSolJsonUrl($psSol);
        $oCache = new cCachedHttp();
        $oCache->deleteCachedURL($sUrl);

        cTracing::leave();
    }
}
