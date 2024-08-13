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
    const OBJDATA_TOP_FOLDER = "[pds]";
    const PDS_SUFFIX = "PDS";
    private static $objstoreDB = null;

    //********************************************************************
    static function init_obj_store_db() {
        if (!self::$objstoreDB) {
            self::$objstoreDB = new cObjStoreDB(cSpaceRealms::PDS);
        }
    }

    //**********************************************************************
    private static function pr__get_objstore_Folder($psSol, $psInstrument) {
        $sFolder = self::OBJDATA_TOP_FOLDER . "/$psSol/$psInstrument";
        return $sFolder;
    }

    //**********************************************************************
    public static function get_pds_data($psSol, $psInstrument) {
        cDebug::enter();
        /** @var cObjStoreDB **/
        $oDB = self::$objstoreDB;

        $sFolder = self::pr__get_objstore_Folder($psSol, $psInstrument);
        $oData = $oDB->get_oldstyle($sFolder, cSpaceIndex::get_filename(cSpaceIndex::INSTR_PREFIX, self::PDS_SUFFIX));
        cDebug::leave();

        return $oData;
    }

    //**********************************************************************
    public static function write_index_data($paData) {
        /** @var cObjStoreDB **/
        $oDB = self::$objstoreDB;
        foreach ($paData as  $sSol => $aSolData)
            foreach ($aSolData as $sInstr => $aInstrData) {
                $sFilename = cSpaceIndex::get_filename(cSpaceIndex::INSTR_PREFIX, self::PDS_SUFFIX);
                $aPDSData = $oDB->get_oldstyle(self::OBJDATA_TOP_FOLDER . "/$sSol/$sInstr", $sFilename);
                if ($aPDSData) {
                    //update existing with new data
                    foreach ($aInstrData as $sNewKey => $aNewData)
                        $aPDSData[$sNewKey] = $aNewData;
                } else
                    $aPDSData = $aInstrData;

                /** @var cObjStoreDB **/
                $oDB = self::$objstoreDB;
                $oDB->put_oldstyle(self::OBJDATA_TOP_FOLDER . "/$sSol/$sInstr", $sFilename, $aPDSData);
                cDebug::extra_debug("$sSol/$sInstr lines:" . count($aPDSData));
            }
    }

    //**********************************************************************
    public static function kill_index_files() {
        /** @var cObjStoreDB **/
        $oDB = self::$objstoreDB;
        $oDB->kill_folder_oldstyle(self::OBJDATA_TOP_FOLDER);
    }
}
cPDS::init_obj_store_db();
