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
class cMSLManifestOrmUtils {

    //************************************************************************************************
    /**
     * searches for product
     * @param string $psPartial 
     * @return null|cSpaceProductData 
     */
    static function search_for_product(string $psPartial) {
        cTracing::enter();
        //search products table
        $iMission = cCuriosityORMManifest::$mission_id;

        //get data from database
        $aSampleTypeIds = tblSampleType::get_matching_ids($iMission, ["full", "subframe"]);
        $aProducts = cSpaceManifestUtils::search_product($iMission, $psPartial, $aSampleTypeIds);
        if ($aProducts == null)
            return null;

        cTracing::leave();
        return $aProducts;
    }

    //************************************************************************************************
    static function get_random_images(string $psPattern, int $piHowmany) {
        cTracing::enter();
        $iMission = cCuriosityORMManifest::$mission_id;
        //get instruments
        $aInstruments = tblInstruments::get_matching($iMission, $psPattern);
        if (count($aInstruments) == 0)
            cDebug::error("no matching instruments");

        //from the products table get number of products
        /** @var cSpaceProductData[] $aProducts */
        $aProducts = cSpaceManifestUtils::get_random_images($iMission, $aInstruments, $piHowmany);

        //map the data to output format
        $aOutput = self::map_to_output($aProducts);

        cTracing::leave();
        return $aOutput;
    }

    /**
     * 
     * @param cSpaceProductData[] $paData 
     * @return mixed 
     */
    static function map_to_output(array $paData) {
        $aResults = array_map(
            function (cSpaceProductData $poData) {
                return self::pr__map_to_output($poData);
            },
            $paData
        );
        return $aResults;
    }

    //************************************************************************************************
    /**
     * Maps generic product to what the curiosity browser expects
     * @param tblProducts $poItem 
     * @return array<string, mixed> An associative array
     */
    private static function pr__map_to_output(cSpaceProductData $poData) {
        $oList =  [
            cOutputColumns::SOL => $poData->sol,
            cOutputColumns::URL => $poData->image_url,
            cOutputColumns::PRODUCT => $poData->product,
            cOutputColumns::DATE => $poData->utc_date,
            cOutputColumns::FULL_INSTRUMENT => $poData->full_instr,
            cOutputColumns::INSTRUMENT => $poData->instr,
            cOutputColumns::MISSION => $poData->mission,
            cOutputColumns::SAMPLETYPE => $poData->sample_type
        ];
        return $oList;
    }
}
