<?php
//panorama data form the marvellous work of Nev Thompson http://www.gigapan.com/profiles/pencilnev

require_once  "$phpInc/ckinc/debug.php";
require_once  "$phpInc/ckinc/objstoredb.php";
require_once  "$spaceInc/misc/realms.php";
static $objstoreDB = null;

class cPencilNev {
    const NEVILLE_FILENAME = "[nevgig].txt";
    const TOP_NEVILLE_FILENAME = "[topnevgig].txt";
    static $objstoreDB = null;

    //********************************************************************
    //********************************************************************
    static function init_obj_store_db() {
        cDebug::enter();
        if (self::$objstoreDB == null) {
            self::$objstoreDB = new cObjStoreDB(cSpaceRealms::NEVILLE);
        }
        cDebug::leave();
    }

    //***********************************************************************************************
    public static function get_top_gigas() {
        cDebug::enter();
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $aData =  $oDB->get_oldstyle("", self::TOP_NEVILLE_FILENAME);
        ksort($aData);
        cDebug::leave();

        return $aData;
    }

    //***********************************************************************************************
    public static function get_sol_gigas($psSol) {
        //cDebug::enter();
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $oData = $oDB->get_oldstyle($psSol, self::NEVILLE_FILENAME);
        //cDebug::leave();
        return $oData;
    }

    //***********************************************************************************************
    public static function index_gigapans($paData) {
        cDebug::enter();
        $aData = [];
        $aTop = [];
        $iMatched = 0;

        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;

        //build up the list
        foreach ($paData as $aItem) {
            $sDescr = $aItem["D"];
            if (stristr($sDescr, "msl") === FALSE) continue;
            $aMatches = [];
            if (preg_match("/\D+(\d+)/", $sDescr, $aMatches)) {
                $sSol = $aMatches[1];
                if (!isset($aData[$sSol]))    $aData[$sSol] = [];
                $aData[$sSol][] = $aItem;
                $aTop[$sSol] = 1;
                $iMatched++;
            } else
                cDebug::write("skipping:$sDescr");
        }
        ksort($aData);
        if ($iMatched == 0)    cDebug::error("** nothing matched");
        //output the files
        foreach ($aData as $sSol => $aItems)
            $oDB->put_oldstyle($sSol, self::NEVILLE_FILENAME, $aItems);
        $oDB->put_oldstyle("", self::TOP_NEVILLE_FILENAME, $aTop);

        cDebug::write("Completed indexing of neville gigapans");
        cDebug::leave();
    }

    //***********************************************************************************************
    public static function get_gigas($psSol) {
        cDebug::enter();
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $oData =  $oDB->get_oldstyle($psSol, self::NEVILLE_FILENAME);
        cDebug::leave();
        return $oData;
    }
}

cPencilNev::init_obj_store_db();
