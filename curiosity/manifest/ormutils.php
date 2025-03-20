<?php

/**************************************************************************
Copyright (C) Chicken Katsu 2013 -2024

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk
// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
 **************************************************************************/
require_once cAppGlobals::$spaceInc . "/curiosity/manifest/manifest.php";
require_once cAppGlobals::$spaceInc . "/curiosity/manifest/index/status.php";
require_once cAppGlobals::$spaceInc . "/curiosity/constants.php";
require_once cAppGlobals::$spaceInc . "/db/mission-manifest.php";
require_once cAppGlobals::$spaceInc . "/manifest/utils.php";



//################################################################################
class cMSLManifestOrmUtils {

    static $flip_replacements;

    //************************************************************************************************
    static function get_random_images(string $psPattern, int $piHowmany) {
        cTracing::enter();

        //get instruments
        $aInstruments = tblInstruments::get_matching(cCuriosityORMManifest::$mission_id, $psPattern);
        if (count($aInstruments) == 0)
            cDebug::error("no matching instruments");

        //from the products table get number of products
        /** @var Collection $oCollection */
        $oCollection = cSpaceManifestUtils::get_random_images(cCuriosityORMManifest::$mission_id, $aInstruments, $piHowmany);
        $aResults =
            $oCollection->map(
                function (tblProducts $poItem) {
                    return self::map_product($poItem);
                }
            )->toArray();

        foreach ($aResults as $iIndex => $aRow) {
            $sAbbreviated = $aRow[cOutputColumns::URL];
            $sProduct = $aRow[cOutputColumns::PRODUCT];
            $sFull = cMSLImageURLUtil::expand_image_url($sAbbreviated, $sProduct);
            $aResults[$iIndex][cOutputColumns::URL] = $sFull;
        }

        cTracing::leave();
        return $aResults;
    }

    //************************************************************************************************
    public static function map_product(tblProducts $poItem) {
        $sUrl = $poItem[tblProducts::IMAGE_URL];

        $sFullInstrument = $poItem->instrument[tblID::NAME];
        $sAbbrInstrument = cCuriosityInstrument::getInstrumentAbbr($sFullInstrument);
        $oList =  [
            cOutputColumns::SOL => $poItem[cMissionColumns::SOL],
            cOutputColumns::URL => $sUrl,
            cOutputColumns::PRODUCT => $poItem[tblProducts::PRODUCT],
            cOutputColumns::DATE => $poItem[tblProducts::UTC_DATE],
            cOutputColumns::FULL_INSTRUMENT => $sFullInstrument,
            cOutputColumns::INSTRUMENT => $sAbbrInstrument,
            cOutputColumns::MISSION => $poItem->mission[tblID::NAME],
            cOutputColumns::SAMPLETYPE => $poItem->sampleType[tblID::NAME]
        ];
        return $oList;
    }
}
