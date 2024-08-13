<?php
require_once  "$spaceInc/misc/realms.php";
require_once  "$phpInc/ckinc/objstoredb.php";


class cSpaceIndex {
    const TOP_PREFIX = "t";
    const SOL_PREFIX = "s";
    const INSTR_PREFIX = "i";
    const PDS_PREFIX = "p";

    const HILITE_SUFFIX = "Highlite";

    static $objstoreDB = null;

    //********************************************************************
    //********************************************************************
    static function init_obj_store_db() {
        cDebug::enter();
        if (self::$objstoreDB == null) {
            self::$objstoreDB = new cObjStoreDB(cSpaceRealms::INDEXES, cSpaceTables::INDEX);
        }
        cDebug::leave();
    }

    //********************************************************************
    //********************************************************************
    public static function get_filename($psPrefix, $psSuffix) {
        return "[{$psPrefix}{$psSuffix}].txt";
    }

    //********************************************************************
    static function get_top_sol_data($psSuffix) {
        cDebug::enter();
        /** @var cObjStoreDB **/
        $oDB = self::$objstoreDB;

        $sFile = self::get_filename(self::TOP_PREFIX, $psSuffix);
        $oData = $oDB->get_oldstyle("", $sFile);
        cDebug::leave();

        return $oData;
    }

    //********************************************************************
    static function get_sol_data($psSol, $psSuffix) {
        cDebug::enter();
        $sFile = self::get_filename(self::SOL_PREFIX, $psSuffix);
        /** @var cObjStoreDB **/
        $oDB = self::$objstoreDB;
        $oData = $oDB->get_oldstyle($psSol, $sFile);
        cDebug::leave();

        return $oData;
    }

    //********************************************************************
    static function get_instr_data($psSol, $psInstrument, $psSuffix) {
        cDebug::enter();
        $sFile = self::get_filename(self::INSTR_PREFIX, $psSuffix);
        /** @var cObjStoreDB **/
        $oDB = self::$objstoreDB;
        $oData = $oDB->get_oldstyle("$psSol/$psInstrument", $sFile);
        cDebug::leave();

        return $oData;
    }

    //********************************************************************
    static function get_solcount($psSol, $psFile) {
        cDebug::enter();
        $iCount = 0;
        $aData = self::get_sol_data($psSol, $psFile);
        if ($aData) {
            foreach ($aData as $sInstr => $aInstrData) {
                foreach ($aInstrData as $sProduct)
                    $iCount++;
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
        self::update_instr_index($psSol, $psInstrument, $psProduct, $poData, $psSuffix);
        self::update_sol_index($psSol, $psInstrument, $psProduct, $psSuffix);
        self::update_top_sol_index($psSol, $psSuffix);
        cDebug::leave();
    }

    //********************************************************************
    static function update_top_sol_index($psSol, $psSuffix) {
        cDebug::enter();
        $sFile = self::get_filename(self::TOP_PREFIX, $psSuffix);
        /** @var cObjStoreDB **/
        $oDB = self::$objstoreDB;
        $aData = $oDB->get_oldstyle("", $sFile);

        if (!$aData) $aData = [];
        if (!isset($aData[$psSol])) $aData[$psSol] = 0;

        $aData[$psSol] = $aData[$psSol] + 1;
        cDebug::write("updating top sol index for sol $psSol");
        $oDB->put_oldstyle("", $sFile, $aData);
        cDebug::leave();
    }

    //********************************************************************
    static function update_sol_index($psSol, $psInstrument, $psProduct, $psSuffix) {
        cDebug::enter();
        $sFile = self::get_filename(self::SOL_PREFIX, $psSuffix);
        /** @var cObjStoreDB **/
        $oDB = self::$objstoreDB;
        $aData = $oDB->get_oldstyle($psSol, $sFile);
        if (!$aData) $aData = [];
        if (!isset($aData[$psInstrument])) $aData[$psInstrument] = [];
        if (!isset($aData[$psInstrument])) $aData[$psInstrument][$psProduct] = 0;
        $aData[$psInstrument][$psProduct] = $aData[$psInstrument][$psProduct] + 1;
        $oDB->put_oldstyle($psSol, $sFile, $aData);
        cDebug::leave();
    }

    //********************************************************************
    static function update_instr_index($psSol, $psInstrument, $psProduct, $poData, $psSuffix) {
        cDebug::enter();
        $sFile = self::get_filename(self::INSTR_PREFIX, $psSuffix);
        $sFolder = "$psSol/$psInstrument";
        /** @var cObjStoreDB **/
        $oDB = self::$objstoreDB;
        $aData = $oDB->get_oldstyle($sFolder, $sFile);
        if (!$aData) $aData = [];
        $aData[$psProduct] = $poData;
        $oDB->put_oldstyle($sFolder, $sFile, $aData);
        cDebug::leave();
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
        /** @var cObjStoreDB **/
        $oDB = self::$objstoreDB;
        foreach ($paData as  $sSol => $aSolData) {
            $aTopSols[$sSol] = 1;
            foreach ($aSolData as $sInstr => $aInstrData)
                $oDB->put_oldstyle("$sSol/$sInstr", self::get_filename(self::INSTR_PREFIX, $psSuffix), $aInstrData);
            $oDB->put_oldstyle($sSol, self::get_filename(self::SOL_PREFIX, $psSuffix), $aSolData);
        }
        $oDB->put_oldstyle("", self::get_filename(self::TOP_PREFIX, $psSuffix), $aTopSols);
        cDebug::leave();
    }
}

cSpaceIndex::init_obj_store_db();
