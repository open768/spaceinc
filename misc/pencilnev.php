<?php
//panorama data form the marvellous work of Nev Thompson http://www.gigapan.com/profiles/pencilnev

require_once  cAppGlobals::$ckPhpInc . "/debug.php";
require_once  cAppGlobals::$ckPhpInc . "/objstoredb.php";
require_once  cAppGlobals::$spaceInc . "/misc/realms.php";
static $objstoreDB = null;

class cPencilNev {
    const NEVILLE_FILENAME = "[nevgig].txt";
    const TOP_NEVILLE_FILENAME = "[topnevgig].txt";
    static $objstoreDB = null;

    //********************************************************************
    //********************************************************************
    static function init_obj_store_db() {
        if (self::$objstoreDB == null) {
            self::$objstoreDB = new cObjStoreDB(cSpaceRealms::NEVILLE);
        }
    }

    //***********************************************************************************************
    public static function get_top_gigas() {
        cTracing::enter();
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $aData =  $oDB->get("/" . self::TOP_NEVILLE_FILENAME);
        ksort($aData);
        cTracing::leave();

        return $aData;
    }

    //***********************************************************************************************
    public static function get_sol_gigas($psSol) {
        //cTracing::enter();
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $oData = $oDB->get("$psSol/" . self::NEVILLE_FILENAME);
        //cTracing::leave();
        return $oData;
    }

    //***********************************************************************************************
    public static function index_gigapans($paData) {
        cTracing::enter();
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
            $oDB->put("$sSol/" . self::NEVILLE_FILENAME, $aItems);
        $oDB->put("/" . self::TOP_NEVILLE_FILENAME, $aTop);

        cDebug::write("Completed indexing of neville gigapans");
        cTracing::leave();
    }

    //***********************************************************************************************
    public static function get_gigas($psSol) {
        cTracing::enter();
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $oData =  $oDB->get("$psSol/" . self::NEVILLE_FILENAME);
        cTracing::leave();
        return $oData;
    }
}

cPencilNev::init_obj_store_db();
