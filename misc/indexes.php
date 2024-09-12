<?php
require_once  cAppGlobals::$spaceInc . "/misc/realms.php";
require_once  cAppGlobals::$phpInc . "/ckinc/objstoredb.php";

/**
 * slight problem this indexes as Sol,Instr,Product when it should have been Sol,Product,Instr
 * @package 
 */
class cSpaceIndex {
    const TOP_PREFIX = "t";
    const SOL_PREFIX = "s";
    const INSTR_PREFIX = "i";
    const PDS_PREFIX = "p";

    const HILITE_SUFFIX = "Highlite";
    const COMMENT_SUFFIX = "Comment";
    const MOSAIC_SUFFIX = "Mosaic";
    const TAG_SUFFIX = "Tags";

    static $objstoreDB = null;

    //********************************************************************
    //********************************************************************
    static function init_obj_store_db() {
        if (self::$objstoreDB == null) {
            self::$objstoreDB = new cObjStoreDB(cSpaceRealms::INDEXES, cSpaceTables::INDEX);
        }
    }

    //********************************************************************
    //********************************************************************
    public static function get_filename($psPrefix, $psSuffix) {
        return "[{$psPrefix}{$psSuffix}].txt";
    }

    //********************************************************************
    static function get_top_sol_data($psSuffix) {
        cDebug::enter();
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;

        $sFile = self::get_filename(self::TOP_PREFIX, $psSuffix);
        $oData = $oDB->get("/$sFile");
        cDebug::leave();

        return $oData;
    }

    //********************************************************************
    static function get_sol_index($psSol, $psSuffix, $pbSolProdInstr = false) {
        //cDebug::enter();
        $sFile = self::get_filename(self::SOL_PREFIX, $psSuffix);
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $oData = $oDB->get("$psSol/$sFile");
        if ($oData == null) {
            cDebug::write("no index found for sol:$psSol suffix:$psSuffix");
            return null;
        }

        //refactor data into sol,prod,instr if required
        if ($pbSolProdInstr) {
            $aProdData = [];
            foreach ($oData as $sInstr => $aProducts) {
                foreach ($aProducts as $sProduct => $iCount) {
                    if (!isset($aProdData[$sProduct])) $aProdData[$sProduct] = [];
                    $aProdData[$sProduct][] = $sInstr;
                }
            }
            $oData = $aProdData;
        }
        //cDebug::leave();
        return $oData;
    }

    //********************************************************************
    static function get_instr_data($psSol, $psInstrument, $psSuffix) {
        cDebug::enter();
        $sFile = self::get_filename(self::INSTR_PREFIX, $psSuffix);
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $oData = $oDB->get("$psSol/$psInstrument/$sFile");
        cDebug::leave();

        return $oData;
    }

    //********************************************************************
    static function get_solcount($psSol, $psFile) {
        cDebug::enter();
        $iCount = 0;
        $aData = self::get_sol_index($psSol, $psFile);
        if ($aData) {
            foreach ($aData as $sInstr => $aInstrData) {
                foreach ($aInstrData as $sProduct => $iProdCount)
                    $iCount += $iProdCount;
            }
        }
        cDebug::leave();

        return $iCount;
    }

    //######################################################################
    //# UPDATE functions
    //######################################################################
    static function update_indexes($psSol, $psInstrument, $psProduct, $poData, $psSuffix) {
        cDebug::enter();
        //@todo check for valid product
        self::update_instr_index($psSol, $psInstrument, $psProduct, $poData, $psSuffix);
        self::update_sol_index($psSol, $psInstrument, $psProduct, $psSuffix);
        self::update_top_sol_index($psSol, $psSuffix);
        cDebug::leave();
    }

    //********************************************************************
    static function update_top_sol_index($psSol, $psSuffix) {
        //cDebug::enter();
        $sFile = self::get_filename(self::TOP_PREFIX, $psSuffix);
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $aData = $oDB->get("/$sFile");

        if (!$aData) $aData = [];
        if (!isset($aData[$psSol])) $aData[$psSol] = 0;

        $aData[$psSol] = $aData[$psSol] + 1;
        cDebug::write("updating top sol index for sol $psSol");
        $oDB->put("/$sFile", $aData);
        //cDebug::leave();
    }

    //********************************************************************
    static function update_sol_index($psSol, $psInstrument, $psProduct, $psSuffix) {
        //cDebug::enter();
        $aData = self::get_sol_index($psSol, $psSuffix);
        if (!$aData) $aData = [];
        if (!isset($aData[$psInstrument])) $aData[$psInstrument] = [];
        if (!isset($aData[$psInstrument][$psProduct])) $aData[$psInstrument][$psProduct] = 0;

        $aData[$psInstrument][$psProduct] = $aData[$psInstrument][$psProduct] + 1;

        $sFile = self::get_filename(self::SOL_PREFIX, $psSuffix);
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $oDB->put("$psSol/$sFile", $aData);
        //cDebug::leave();
    }

    //********************************************************************
    static function update_instr_index($psSol, $psInstrument, $psProduct, $poData, $psSuffix) {
        //cDebug::enter();
        $sFile = self::get_filename(self::INSTR_PREFIX, $psSuffix);
        $sFolder = "$psSol/$psInstrument";
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $aData = $oDB->get("$sFolder/$sFile");
        if (!$aData) $aData = [];
        $aData[$psProduct] = $poData;
        $oDB->put("$sFolder/$sFile", $aData);
        //cDebug::leave();
    }

    //######################################################################
    /**
     * reindexes everything based on whats on disk
     * @deprecated 
     * @param mixed $poInstrData
     * @param string $psSuffix
     * @param string $psProdFile
     * 
     * @return [type]
     */
    static function reindex($poInstrData, $psSuffix, $psProdFile) {
        cDebug::enter();
        $aData = [];

        $toppath = cObjStore::$rootFolder . "/" . cObjStore::$OBJDATA_REALM;

        //find the highlight files - tried to do this cleverly, but was more lines of code - so brute force it is
        $aSols = scandir($toppath);
        foreach ($aSols as $sSol)
            if (preg_match("/\d+/", $sSol)) {
                $solPath = "$toppath/$sSol";
                $aInstrs = scandir($solPath);
                foreach ($aInstrs as $sInstr)
                    if (!preg_match("/[\[\.]/", $sInstr)) {
                        $instrPath = "$solPath/$sInstr";
                        $aProducts = scandir($instrPath);
                        foreach ($aProducts as $sProduct)
                            if (!preg_match("/[\[\.]/", $sProduct)) {
                                $prodPath = "$instrPath/$sProduct";
                                $aFiles = scandir($prodPath);
                                foreach ($aFiles as $sFile)
                                    if ($sFile === $psProdFile) {
                                        if (!isset($aData[$sSol])) $aData[$sSol] = [];
                                        if (!isset($aData[$sSol][$sInstr])) $aData[$sSol][$sInstr] = [];
                                        $aData[$sSol][$sInstr][$sProduct] = $poInstrData;
                                    }
                            }
                    }
            }

        self::write_index_files($aData, $psSuffix);
        cDebug::leave();
    }

    //***********************************************************************************************************
    public static function write_index_files($paData, $psSuffix) {
        cDebug::enter();
        $aTopSols = [];
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        foreach ($paData as  $sSol => $aSolData) {
            $aTopSols[$sSol] = 1;
            foreach ($aSolData as $sInstr => $aInstrData)
                $oDB->put("$sSol/$sInstr" . self::get_filename(self::INSTR_PREFIX, $psSuffix), $aInstrData);
            $oDB->put("$sSol/" . self::get_filename(self::SOL_PREFIX, $psSuffix), $aSolData);
        }
        $oDB->put("/" . self::get_filename(self::TOP_PREFIX, $psSuffix), $aTopSols);
        cDebug::leave();
    }
}

cSpaceIndex::init_obj_store_db();
