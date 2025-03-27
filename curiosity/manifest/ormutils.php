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

    static function map_product_collection(Collection $poCollection) {
        $aResults = $poCollection->map(
            function (tblProducts $poItem) {
                return self::map_product($poItem);
            }
        )->toArray();
        return $aResults;
    }

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
        $aSampleTypeIds = tblSampleType::get_matching_ids($iMission, ["full"]);
        $oCollection = cSpaceManifestUtils::search_product($iMission, $psPartial, $aSampleTypeIds);
        if ($oCollection == null)
            return null;

        $aResults = self::map_product_collection($oCollection);

        //map to cSpaceProductData
        $aRow = $aResults[0];
        $oOut = new cSpaceProductData; {
            $oOut->sol = $aRow[cOutputColumns::SOL];
            $oOut->product = $aRow[cOutputColumns::PRODUCT];
            $oOut->instr = $aRow[cOutputColumns::INSTRUMENT];
            $oOut->image_url = $aRow[cOutputColumns::URL];
        }
        cTracing::leave();
        return $oOut;
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
        /** @var Collection $oCollection */
        $oCollection = cSpaceManifestUtils::get_random_images($iMission, $aInstruments, $piHowmany);

        //map data to what curiosity browser expects
        $aResults = self::map_product_collection($oCollection);

        cTracing::leave();
        return $aResults;
    }

    //************************************************************************************************
    /**
     * Maps generic product to what the curiosity browser expects
     * @param tblProducts $poItem 
     */
    public static function map_product(tblProducts $poItem) {
        $sProduct = $poItem[tblProducts::PRODUCT];
        $sAbbreviated = $poItem[tblProducts::IMAGE_URL];
        $sFull = cMSLImageURLUtil::expand_image_url($sAbbreviated, $sProduct);

        $sFullInstrument = $poItem->instrument[tblID::NAME];
        $sAbbrInstrument = cCuriosityInstrument::getInstrumentAbbr($sFullInstrument);
        $oList =  [
            cOutputColumns::SOL => $poItem[cMissionColumns::SOL],
            cOutputColumns::URL => $sFull,
            cOutputColumns::PRODUCT => $sProduct,
            cOutputColumns::DATE => $poItem[tblProducts::UTC_DATE],
            cOutputColumns::FULL_INSTRUMENT => $sFullInstrument,
            cOutputColumns::INSTRUMENT => $sAbbrInstrument,
            cOutputColumns::MISSION => $poItem->mission[tblID::NAME],
            cOutputColumns::SAMPLETYPE => $poItem->sampleType[tblID::NAME]
        ];
        return $oList;
    }
}
