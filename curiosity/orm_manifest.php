<?php

/**************************************************************************
Copyright (C) Chicken Katsu 2013 -2024

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk
// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
 **************************************************************************/
require_once "manifest.php";
require_once cAppGlobals::$spaceInc . "/db/mission-manifest.php";
require_once  cAppGlobals::$spaceInc . "/curiosity/curiosity.php";


class cCuriosityORMManifest {
    static $mission_id = null;

    static function init() {
        $mission_id = tblMissions::get_id(null, cCuriosity::MISSION_ID);
    }

    static function empty_ORM_tables() {
        //drop all tables in the manifest
        cDebug::enter();
        cMissionManifest::empty_manifest();
        cDebug::leave();
    }

    static function updateIndex() {
        cDebug::enter();

        //reset index status for DEBUGGING PRUPOSES
        //cCuriosityManifestIndexStatus::clear_status();

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
            TransactionsORM::beginTransaction();
            try {
                self::index_sol($sSol, $sManifestLastUpdated, $bReindex);
                TransactionsORM::commit();
            } catch (Exception $e) {
                TransactionsORM::rollBack();
                cDebug::error("unable to index sol $sSol: $e ");
            }
        }
        cCuriosityManifestIndexStatus::put_status(cCuriosityManifestIndexStatus::STATUS_COMPLETE);
        cDebug::leave();
    }

    //*****************************************************************************
    static function index_sol(string $psSol, $sLastUpdatedValue, bool $pbReindex) {
        cDebug::extra_debug("indexing sol:$psSol");

        if ($pbReindex) self::delete_sol_index($psSol);

        $bCheckExpiry = !$pbReindex;
        $oSolData = cCuriosityManifest::getSolRawData($psSol, $bCheckExpiry); //this is needed

        $aImages = $oSolData->images;
        if ($aImages === null) cDebug::error("no image data");

        foreach ($aImages as $sKey => $oImgData)
            self::add_to_index($psSol, $oImgData);


        if (cDebug::is_debugging())
            cPageOutput::scroll_to_bottom();


        //update the status
        cCuriosityManifestIndexStatus::put_last_indexed_sol($psSol);
        cCuriosityManifestIndexStatus::put_sol_last_updated($psSol, $sLastUpdatedValue);
    }
}

cCuriosityORMManifest::init();
