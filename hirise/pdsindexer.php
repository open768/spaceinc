<?php

/**************************************************************************
Copyright (C) Chicken Katsu 2013 - 2024
This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
 **************************************************************************/

require_once  cAppGlobals::$phpInc . "/ckinc/objstoredb.php";
require_once  cAppGlobals::$spaceInc . "/pds/pdsreader.php";

class cHiRise {
    const OBJDATA_TOP_FOLDER = "[hirise]";
    const OBJDATA_TABLE = "HIRISE";

    public static $objstoreDB = null;


    //********************************************************************
    public static function init_obj_store_db() {
        if (!self::$objstoreDB)
            self::$objstoreDB = new cObjStoreDB(cSpaceRealms::ROVER_MISSION, "HIRISE");
    }

    //********************************************************************
    public static function getIntersections($pfLat1, $pfLong1, $pfLat2, $pfLong2) {
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $aHirise = $oDB->get_oldstyle(self::OBJDATA_TOP_FOLDER, cHiRisePDSIndexer::OBSERVATION_FILE);
        if (!$aHirise) cDebug::error("no hirise index found");

        $aOut = [];
        $oInRect = new cRect($pfLat1, $pfLong1, $pfLat2, $pfLong2);
        foreach ($aHirise as $oObs) {
            //bum no intersections
            if ($oInRect->intersects($oObs->oRect))
                $aOut[] = $oObs;
        }
        return $aOut;
    }
}

class cHiRiseEDRObj {
    public $oRect, $sID, $sTime;
    public function __construct($paData) {
        $this->oRect = new cRect(
            (float) $paData["MINIMUM_LATITUDE"],
            (float) $paData["MINIMUM_LONGITUDE"],
            (float) $paData["MAXIMUM_LATITUDE"],
            (float) $paData["MAXIMUM_LONGITUDE"]
        );
        $this->sID = $paData["OBSERVATION_ID"];
        $this->sTime = $paData["START_TIME"];
    }

    public function merge($paData) {
        $oNewObj = new cHiRiseEDRObj($paData);
        $this->oRect->merge($oNewObj->oRect);
    }
}
cHiRise::init_obj_store_db();

//##########################################################################
class cHiRisePDSIndexer {
    const PDS_PATH = "http://hirise-pds.lpl.arizona.edu/PDS/INDEX/";
    const PDS_LBL = "EDRINDEX.LBL";
    const OBSERVATION_FILE = "obs.txt.gz";

    private static $PDS_COL_NAMES = ["OBSERVATION_ID", "START_TIME", "MINIMUM_LATITUDE", "MAXIMUM_LATITUDE", "MINIMUM_LONGITUDE", "MAXIMUM_LONGITUDE"];

    //**********************************************************************
    public static function run_indexer() {
        //-------------------------------------------------------------------------------
        //get the LBL file to understand how to parse the file 
        //cPDS_Reader::$force_delete = true;
        $sLBLUrl = self::PDS_PATH . self::PDS_LBL;
        cDebug::write("Getting LBL file $sLBLUrl");
        $oLBL = cPDS_Reader::fetch_lbl($sLBLUrl);
        cDebug::write("got LBL file $sLBLUrl");
        //$oLBL->__dump();

        //-------------------------------------------------------------------------------
        //get the TAB file
        $sTBLFileName = $oLBL->get("^EDR_INDEX_TABLE");
        if ($sTBLFileName == null)
            cDebug::error("unable to determine TAB - was the LBL Parsed correctly?");
        $sTABUrl = self::PDS_PATH . $sTBLFileName;
        cDebug::write("Getting TAB file $sTABUrl");
        $sOutFile = "HIRISE.TAB";
        $sTABFile = cPDS_Reader::fetch_tab($sTABUrl, $sOutFile);
        cDebug::write("done Getting TAB file $sTABUrl");

        //-------------------------------------------------------------------------------
        //get the data out from the TAB file:
        cDebug::write("Parsing TAB");
        cPDS_Reader::$columns_object_name = "EDR_INDEX_TABLE";
        $aData = cPDS_Reader::parse_TAB($oLBL, $sTABFile, self::$PDS_COL_NAMES);
        cDebug::write("Done Parsing TAB");

        //-------------------------------------------------------------------------------
        cDebug::write("writing files");
        self::pr__create_index_files($aData);
        cDebug::write("Done OK");
    }

    //**********************************************************************
    private static function pr__create_index_files($paTabData) {
        $aData = [];

        foreach ($paTabData as  $aRow) {
            $sID = $aRow["OBSERVATION_ID"];
            if (!isset($aData[$sID]))
                $aData[$sID] = new cHiRiseEDRObj($aRow);
            else
                $aData[$sID]->merge($aRow);
        }

        /** @var cObjStoreDB $oDB **/
        $oDB = cHiRise::$objstoreDB;
        $oDB->put_oldstyle(cHiRise::OBJDATA_TOP_FOLDER, self::OBSERVATION_FILE, $aData);
    }
}
