<?php

/**************************************************************************
Copyright (C) Chicken Katsu 2013 -2024

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk
// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
 **************************************************************************/

use Illuminate\Database\Eloquent\Collection;

require_once cAppGlobals::$spaceInc . "/curiosity/manifest/manifest.php";
require_once cAppGlobals::$spaceInc . "/curiosity/manifest/index/status.php";
require_once cAppGlobals::$spaceInc . "/curiosity/constants.php";
require_once cAppGlobals::$spaceInc . "/db/mission-manifest.php";
require_once cAppGlobals::$spaceInc . "/manifest/utils.php";



//################################################################################
class cCuriosityORMManifest {

    const SAMPLE_ALL = 1;
    const SAMPLE_THUMBS = 2;
    const SAMPLE_NONTHUMBS = 3;

    //const FEED_URL = "https://mars.jpl.nasa.gov/msl-raw-images/image/image_manifest.json";
    static $mission_id = null;

    //***************************************************************************
    static function init() {
        self::$mission_id = tblMissions::get_id(null, cCuriosityConstants::MISSION_ID);
    }

    //***************************************************************************
    static function deleteEntireIndex() {
        //drop all tables in the manifest
        cTracing::enter();

        cDebug::write("emptying manifest");
        cCuriosityManifestIndexStatus::clear_status();
        cMissionManifest::empty_manifest();

        cTracing::leave();
    }

    //***************************************************************************
    static function is_sol_in_index(int $piSol): bool {
        //cTracing::enter();
        $slastUpdated = tblSolStatus::get_last_updated(self::$mission_id, $piSol);
        //cTracing::leave();
        return ($slastUpdated !== null);
    }

    //***************************************************************************
    static function get_all_sol_data(int $piSol, ?string $psInstrument = null, ?eSpaceSampleTypes $piSampleTypeChooser = eSpaceSampleTypes::SAMPLE_ALL): cManifestSolData {
        cTracing::enter();

        cCuriosityORMManifestIndexer::reindex_if_needed($piSol);
        // get instrument ID
        $iInstrument = null;
        if ($psInstrument !== null)
            $iInstrument = tblInstruments::get_id(self::$mission_id, $psInstrument);

        //get thumbnail sampletypeid as its not known to generic tblproducts
        $iThumbSampleType = tblSampleType::get_id(self::$mission_id, "thumbnail");
        $oCollection =  tblProducts::get_all_data(self::$mission_id, $piSol, $iInstrument, $piSampleTypeChooser, $iThumbSampleType);

        //map to our format
        $aResults = cSpaceManifestUtils::map_collection($oCollection);

        //and convert to cManifestSolData
        $oData = new cManifestSolData();
        $oData->sol = $piSol;
        $oData->data = $aResults;

        cTracing::leave();
        return $oData;
    }

    //***************************************************************************
    static function get_instruments_for_sol(int $piSol): array {
        cTracing::enter();

        cCuriosityORMManifestIndexer::reindex_if_needed($piSol);

        cDebug::write("Getting instrument list for sol " . $piSol);
        /** @var array $aData */
        $aOut = [];
        $aRows = tblProducts::get_sol_instruments(self::$mission_id, $piSol); //this gets ID and name(abbr) columns
        foreach ($aRows as $oRow) {
            $abbr = $oRow[tblInstruments::NAME];
            $full = cCuriosityInstrument::getInstrumentName($abbr);
            $aOut[] = $full;
        }

        cTracing::leave();
        return $aOut;
    }
}
cCuriosityORMManifest::init();

//################################################################################
class    cCuriosityORMManifestIndexer {
    static function is_reindex_needed(int $piSol): bool|null {

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

    //********************************************************************************************
    static function reindex_if_needed(int $piSol) {
        cTracing::enter();

        cDebug::write("checking Sol $piSol");

        $bIndexIt = self::is_reindex_needed($piSol);
        if ($bIndexIt)
            self::index_sol($piSol, true);
        cTracing::leave();
    }

    //********************************************************************************************
    static function updateIndex() {
        //cTracing::enter();

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
        self::remove_unwanted();

        //finalise
        cDebug::write("vacuuming database");
        cEloquentORM::vacuum(cMissionManifest::DBNAME);

        cDebug::extra_debug("completed");
        cCuriosityManifestIndexStatus::put_status(cCuriosityManifestIndexStatus::STATUS_COMPLETE);
        //cTracing::leave();
    }

    //*****************************************************************************
    public static function remove_unwanted() {
        cTracing::enter();
        try {
            cSpaceManifestUtils::remove_sample_types(cCuriosityORMManifest::$mission_id, ["downsampled", "mixed"]);
        } catch (Exception $e) {
            cDebug::write("unable to remove sample types: " . $e->getMessage());
        }

        tblProducts::keep_instruments(cCuriosityORMManifest::$mission_id, ["mahli", "mardi", "mast_left", "mast_right", "nav_left_a", "nav_left_b", "nav_right_a", "nav_right_b"]);
        cTracing::leave();
    }


    //*****************************************************************************
    static function delete_sol_index(int $piSol) {
        $iMission = cCuriosityORMManifest::$mission_id;
        tblProducts::where("mission_id", $iMission)->where('sol', $piSol)->delete();
    }

    //*****************************************************************************

    static function add_to_index($pisol, $poItem) {
        //cTracing::enter();

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
        $oProduct->image_url = cMSLImageURLUtil::reduce_image_url($poItem->urlList, $poItem->itemName);
        $oProduct->product = $poItem->itemName;
        $oProduct->utc_date = $poItem->utc;
        $oProduct->drive = $poItem->drive;
        $oProduct->save();

        //cTracing::leave();
    }

    //*****************************************************************************
    static function index_sol(int $piSol, bool $pbReindex) {
        //cTracing::enter();
        //cDebug::write("indexing sol:$piSol");

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
        //cTracing::leave();
    }
}
