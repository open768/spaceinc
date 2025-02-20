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

//################################################################################
class cManifestUtils {
    static $replacements = [
        "http://" => "{1}",
        "https://" => "{2}",
        "mars.jpl.nasa.gov/msl-raw-images/" => "{3}",
        "mars.nasa.gov/msl-raw-images/" => "{4}",
        "proj/msl/redops/ods/surface/sol/" => "{5}",
        "ods/surface/sol/" => "{6}",
        "{1}{3}msss/" => "{7}",
        "opgs/edr" => "{8}",
        "soas/rdr/" => "{9}"
    ];

    static function reduce_image_url($psUrl, $psProduct) {
        $sOut = str_replace($psProduct, "{P}", $psUrl);
        foreach (self::$replacements as $sSearch => $sReplace) {
            $sOut = str_replace($sSearch, $sReplace, $sOut);
        }
        return $sOut;
    }

    static function expand_image_url($psUrl, $psProduct) {
        $sOut = str_replace("{P}", $psProduct, $psUrl);
        foreach (self::$replacements as $sReplace => $sSearch) {
            $sOut = str_replace($sSearch, $sReplace, $sOut);
        }
        return $sOut;
    }
}

//################################################################################
class cCuriosityORMManifest {

    const SAMPLE_ALL = 1;
    const SAMPLE_THUMBS = 2;
    const SAMPLE_NONTHUMBS = 3;

    //const FEED_URL = "https://mars.jpl.nasa.gov/msl-raw-images/image/image_manifest.json";
    static $mission_id = null;

    //***************************************************************************
    static function init() {
        self::$mission_id = tblMissions::get_id(null, cCuriosity::MISSION_ID);
    }

    //***************************************************************************
    static function deleteEntireIndex() {
        //drop all tables in the manifest
        cDebug::enter();

        cDebug::write("emptying manifest");
        cCuriosityManifestIndexStatus::clear_status();
        cMissionManifest::empty_manifest();

        cDebug::leave();
    }

    //***************************************************************************
    static function is_sol_in_index(int $piSol): bool {
        cDebug::enter();
        $slastUpdated = tblSolStatus::get_last_updated(cCuriosityORMManifest::$mission_id, $piSol);
        cDebug::leave();
        return ($slastUpdated !== null);
    }

    //***************************************************************************
    static function get_all_sol_data(int $piSol, ?string $psInstrument = null, string $piSampleType = self::SAMPLE_ALL): array {
        cDebug::enter();
        return tblProducts::get_all_sol_data(self::$mission_id, $piSol,  $psInstrument, $piSampleType);
        cDebug::leave();
    }
}
cCuriosityORMManifest::init();

//################################################################################
class    cCuriosityORMManifestIndexer {
    static function is_reindex_needed(int $piSol): bool {

        //get the last_updated from the JPL manifest
        $oManifestData = cCuriosityJPLManifest::getSolEntry($piSol);
        if ($oManifestData == null) {
            cDebug::write("$piSol is not in the manifest at all");
            return null;
        }
        $sManifestUtc = $oManifestData->last_updated;

        //calculate if sol needs reindexing
        $bIndexIt = false;
        $slastUpdated = tblSolStatus::get_last_updated(cCuriosityORMManifest::$mission_id, $piSol);
        if ($slastUpdated === null) {
            cDebug::write("sol $piSol is not in the index");
            $bIndexIt = true;
        } elseif ($slastUpdated < $sManifestUtc) {
            cDebug::write("sol $piSol needs to be reindexed");
            $bIndexIt = true;
        } else
            cDebug::write("sol $piSol no reindexing needed");

        return $bIndexIt;
    }

    static function reindex_if_needed(int $piSol) {
        cDebug::enter();

        cDebug::write("checking Sol $piSol");

        $bIndexIt = self::is_reindex_needed($piSol);
        if ($bIndexIt) {
            cDebug::write("indexing sol $piSol");
            self::index_sol($piSol, true);
        }
        cDebug::leave();
    }

    static function updateIndex() {
        //cDebug::enter();

        //----------get manifest
        cDebug::write("getting sol Manifest");
        $oManifest = cCuriosityJPLManifest::getManifest();

        //----------get status from odb
        $sStatus = cCuriosityManifestIndexStatus::get_status();

        if ($sStatus === cCuriosityManifestIndexStatus::STATUS_COMPLETE) {
            $sLastIndexedSol = cCuriosityManifestIndexStatus::get_last_indexed_sol();
            $sLatestManifestSol = $oManifest->latest_sol;
            cDebug::write("last indexed sol was: $sLastIndexedSol, latest manifest sol: $sLatestManifestSol");
            if ($sLastIndexedSol >= $sLatestManifestSol) {
                cDebug::write("vacuuming database");
                cEloquentORM::vacuum(cMissionManifest::DBNAME);
                cDebug::error("indexing allready complete");
            }
        }

        //----------get last indexed sol  odb
        $sLastSol = cCuriosityManifestIndexStatus::get_last_indexed_sol();
        if ($sLastSol == null) $sLastSol = -1;
        cDebug::write("indexing starting at sol: $sLastSol");

        cDebug::write("processing sol Manifest");
        $aSols = $oManifest->sols;
        ksort($aSols, SORT_NUMERIC);
        $sLastManifestSol = array_key_last($aSols);
        cDebug::write("last sol in manifest is $sLastManifestSol");

        //---------------iterate manifest
        foreach ($aSols as $number => $oSol) {
            $sSol = $oSol->sol;
            $bReindex = self::is_reindex_needed($sSol);

            //---------------------check if the row needs reindexing
            if (!$bReindex) $bReindex = $sSol > $sLastSol;
            if (!$bReindex) continue;

            //-------perform the index
            cEloquentORM::beginTransaction(cMissionManifest::DBNAME);
            try {
                self::index_sol($sSol, $bReindex);
                cEloquentORM::commit(cMissionManifest::DBNAME);
            } catch (Exception $e) {
                cEloquentORM::rollBack(cMissionManifest::DBNAME);
                cDebug::error("unable to index sol $sSol: $e ");
            }
        }

        //remove unwanted instruments and sample types
        cDebug::write("removing unwanted products");
        self::pr__remove_unwanted();

        //finalise
        cDebug::write("vacuuming database");
        cEloquentORM::vacuum(cMissionManifest::DBNAME);

        cDebug::extra_debug("completed");
        cCuriosityManifestIndexStatus::put_status(cCuriosityManifestIndexStatus::STATUS_COMPLETE);
        //cDebug::leave();
    }

    //*****************************************************************************
    private static function pr__remove_unwanted() {
        self::pr__remove_sample_types(["downsampled", "subframe", "mixed"]);
        self::pr__keep_instruments(["mahli", "mardi", "mast_left", "mast_right"]);
    }

    private static function pr__remove_sample_types(array $pasample_types) {
    }

    private static function pr__keep_instruments(array $Instruments) {
    }

    //*****************************************************************************
    static function delete_sol_index(int $piSol) {
        $iMission = cCuriosityORMManifest::$mission_id;
        tblProducts::where("mission_id", $iMission)->where('sol', $piSol)->delete();
    }

    //*****************************************************************************

    static function add_to_index($pisol, $poItem) {
        //cDebug::enter();

        if (cDebug::is_debugging())   cCommon::flushprint(".");
        // Convert sampletype and instrument to integer lookups
        $iMission = cCuriosityORMManifest::$mission_id;
        $iSampleTypeID = tblSampleType::get_id($iMission, $poItem->sampleType);
        $iInstrumentID = tblInstruments::get_id($iMission, $poItem->instrument);

        // Create a new row in tblProducts
        $oProduct = new tblProducts();
        $oProduct->mission_id = $iMission;
        $oProduct->sol = $poItem->sol;
        $oProduct->instrument_id = $iInstrumentID;
        $oProduct->sample_type_id = $iSampleTypeID;
        $oProduct->site = $poItem->site;
        $oProduct->image_url = cManifestUtils::reduce_image_url($poItem->urlList, $poItem->itemName);
        $oProduct->product = $poItem->itemName;
        $oProduct->utc_date = $poItem->utc;
        $oProduct->drive = $poItem->drive;
        $oProduct->save();

        //cDebug::leave();
    }

    //*****************************************************************************
    static function index_sol(int $piSol, bool $pbReindex) {
        //cDebug::enter();
        cDebug::write("indexing sol:$piSol");

        if ($pbReindex) self::delete_sol_index($piSol);


        $bCheckExpiry = !$pbReindex;
        $oSolData = cCuriosityJPLManifest::getSolRawData($piSol, $bCheckExpiry, false); //this is needed

        $aImages = $oSolData->images;
        if ($aImages === null) cDebug::error("no image data");

        if (sizeof($aImages) == 0) {
            cDebug::write("no data in sol:$piSol, but thats ok");
            return;
        }

        cDebug::write("processing images in sol:$piSol");
        foreach ($aImages as $sKey => $oImgData)
            self::add_to_index($piSol, $oImgData);

        if (cDebug::is_debugging())
            cPageOutput::scroll_to_bottom();

        //update the status
        cCuriosityManifestIndexStatus::put_last_indexed_sol($piSol);

        //update the sol status
        $oManifestData = cCuriosityJPLManifest::getSolEntry($piSol);
        tblSolStatus::put_last_updated(cCuriosityORMManifest::$mission_id, $piSol, $oManifestData->last_updated);
        //cDebug::leave();
    }
}
