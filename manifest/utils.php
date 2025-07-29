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

require_once cAppGlobals::$spaceInc . "/db/mission-manifest.php";
require_once cAppGlobals::$spaceInc . "/misc/constants.php";

class cSpaceManifestUtilsException  extends Exception {
}

/**
 * mission agnostic routines to get information from a manifest database
 */
class cSpaceManifestUtils {
    /**
     * @return cSpaceProductData[]
     */
    static function map_collection(Collection $poCollection) {
        //cTracing::enter();
        $aResults = $poCollection->map(
            function (tblProducts $poItem) {
                return self::pr__map_to_spaceproduct($poItem);
            }
        )->toArray();
        //cTracing::leave();
        return $aResults;
    }

    /**
     * 
     * @param tblProducts $poItem 
     * @return cSpaceProductData
     */
    private static function pr__map_to_spaceproduct(tblProducts $poItem) {
        $sProduct = $poItem[tblProducts::PRODUCT];
        $sAbbreviated = $poItem[tblProducts::IMAGE_URL];
        $sFull = cMSLImageURLUtil::expand_image_url($sAbbreviated, $sProduct);

        $sFullInstrument = $poItem->instrument[tblID::NAME];
        $sAbbrInstrument = cCuriosityInstrument::getInstrumentAbbr($sFullInstrument);
        $oProduct = new cSpaceProductData(); {
            $oProduct->sol = $poItem[cMissionColumns::SOL];
            $oProduct->image_url = $sFull;
            $oProduct->product = $sProduct;
            $oProduct->utc_date = $poItem[tblProducts::UTC_DATE];
            $oProduct->full_instr = $sFullInstrument;
            $oProduct->instr = $sAbbrInstrument;
            $oProduct->mission = $poItem->mission[tblID::NAME];
            $oProduct->sample_type = $poItem->sampleType[tblID::NAME];
        }
        return $oProduct;
    }

    //*******************************************************************************
    //*
    //*******************************************************************************
    static function remove_sample_types(int $piMission, array $pasample_types) {
        cTracing::enter();

        cDebug::extra_debug("building lists");
        $aIDs = tblSampleType::get_matching_ids($piMission, $pasample_types);

        // remove the offending sample types from product table
        cDebug::extra_debug("removing from products table");
        tblProducts::whereIn(tblProducts::SAMPLE_TYPE_ID, $aIDs)
            ->where(cMissionColumns::MISSION_ID, $piMission)
            ->delete();

        // remove the offending sample types from sampletypes table
        cDebug::extra_debug("removing from sampletypes table");
        tblSampleType::whereIn(tblID::ID, $aIDs)
            ->where(cMissionColumns::MISSION_ID, $piMission)
            ->delete();

        cTracing::leave();
    }

    //*******************************************************************************
    //*
    //*******************************************************************************
    /**
     * 
     * @param int $piMission  MIssion ID
     * @param array $aInstrumentIDs list of instruments for products
     * @param int $iLimit How many rows to return
     * @return cSpaceProductData[] 
     */
    public static function get_random_images(int $piMission, array $paInstrumentIDs, int $piLimit = 10) {
        cTracing::enter();

        /** @var Builder $oBuilder */
        $oBuilder = tblProducts::get_builder($piMission);

        /** @var Collection $oCollection */
        $oBuilder  = $oBuilder->whereIn(tblProducts::INSTRUMENT_ID, $paInstrumentIDs)
            ->inRandomOrder()
            ->limit($piLimit);
        $oCollection = cEloquentORM::get($oBuilder);

        /** map the collection to cSpaceProductData */
        $aOutput = self::map_collection($oCollection);


        cTracing::leave();

        return $aOutput;
    }

    //*******************************************************************************
    /**
     * 
     * @param int $piMission  MIssion ID
     * @param string 
     * @param int $iLimit How many rows to return
     * @return cSpaceProductData[] 
     */
    public static function search_product(int $piMission, string $psSearch, array $paSampleTypeIDs) {
        cTracing::enter();
        /** @var Builder $oBuilder */
        $oCollection = tblProducts::search_product($piMission, $psSearch, $paSampleTypeIDs);

        $iCount = $oCollection->count();
        if ($iCount == 0) {
            cDebug::extra_debug("no products found for $psSearch");
            return null;
        }
        $aOutput = self::map_collection($oCollection);

        cTracing::leave();
        return $aOutput;
    }
}
