<?php

/**************************************************************************
Copyright (C) Chicken Katsu 2013 -2024

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
 **************************************************************************/

require_once  "$phpInc/ckinc/http.php";
require_once  "$phpInc/ckinc/objstoredb.php";
require_once  "$spaceInc/misc/indexes.php";
require_once  "$spaceInc/misc/realms.php";


//##########################################################################
class cPDS {
    const PDS_TOP_FOLDER = "[pds]";
    const PDS_SUFFIX = "PDS";
    const PDS_FILENAME = "[pds.txt]";
    private static $objstoreDB = null;

    //********************************************************************
    static function init_obj_store_db() {
        if (!self::$objstoreDB) {
            self::$objstoreDB = new cObjStoreDB(cSpaceRealms::PDS, cSpaceTables::PDS);
        }
    }

    //**********************************************************************
    private static function pr__get_objstore_filename($psSol, $psInstrument) {
        $sFolder = self::PDS_TOP_FOLDER . "/$psSol/$psInstrument/" + self::PDS_FILENAME;
        return $sFolder;
    }

    //**********************************************************************
    public static function get_pds_data($psSol, $psInstrument) {
        cDebug::enter();
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;

        $sFilename = self::pr__get_objstore_filename($psSol, $psInstrument);
        $oData = $oDB->get($sFilename);
        cDebug::leave();

        return $oData;
    }

    //**********************************************************************
    public static function put_pds_data($psSol, $psInstr, $paData) {
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $sFilename = self::pr__get_objstore_filename($psSol, $psInstr);
        $oDB->put($sFilename, $paData, true);
    }

    //**********************************************************************
    public static function write_index_data($paData) {
        /** @var cObjStoreDB $oDB **/
        foreach ($paData as  $sSol => $aSolData)
            foreach ($aSolData as $sInstr => $aInstrData) {
                $aPDSData = self::get_pds_data($sSol, $sInstr);
                if ($aPDSData) {
                    //update existing with new data
                    foreach ($aInstrData as $sNewKey => $aNewData)
                        $aPDSData[$sNewKey] = $aNewData;
                } else
                    $aPDSData = $aInstrData;

                self::put_pds_data($sSol, $sInstr, $aPDSData);
            }
    }
}
cPDS::init_obj_store_db();
