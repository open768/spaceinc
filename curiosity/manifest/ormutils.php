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

class cOutputColumns {
    const MISSION = "m";
    const SOL = "s";
    const INSTRUMENT = "i";
    const PRODUCT = "p";
    const DATA = "d";
    const URL = "d";
    const DATE = "dt";
    const SAMPLETYPE = "st";
}

//################################################################################
class cManifestOrmUtils {
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
    static $flip_replacements;

    static function reduce_image_url($psUrl, $psProduct) {
        $sOut = str_replace($psProduct, "{P}", $psUrl);
        foreach (self::$replacements as $sSearch => $sReplace) {
            $sOut = str_replace($sSearch, $sReplace, $sOut);
        }
        return $sOut;
    }

    static function expand_image_url($psUrl, $psProduct) {

        $sOut = str_replace("{P}", $psProduct, $psUrl);

        while (true) {
            $iStartPos = strpos($sOut, "{");
            if ($iStartPos === false) break;

            $iEndPos = strpos($sOut, "}", $iStartPos);
            if ($iEndPos === false) break;

            $sFragment = substr($sOut, $iStartPos, $iEndPos - $iStartPos + 1);
            if (!isset(self::$flip_replacements[$sFragment]))
                break;

            $sReplacement = self::$flip_replacements[$sFragment];
            $sOut = str_replace($sFragment, $sReplacement, $sOut);
        }

        return $sOut;
    }


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
            $sFull = self::expand_image_url($sAbbreviated, $sProduct);
            $aResults[$iIndex][cOutputColumns::URL] = $sFull;
        }

        cTracing::leave();
        return $aResults;
    }

    public static function map_product(tblProducts $poItem) {
        $sUrl = $poItem[tblProducts::IMAGE_URL];

        $oList =  [
            cOutputColumns::SOL => $poItem[cMissionColumns::SOL],
            cOutputColumns::URL => $sUrl,
            cOutputColumns::PRODUCT => $poItem[tblProducts::PRODUCT],
            cOutputColumns::DATE => $poItem[tblProducts::UTC_DATE],
            cOutputColumns::INSTRUMENT => $poItem->instrument[tblID::NAME],
            cOutputColumns::MISSION => $poItem->mission[tblID::NAME],
            cOutputColumns::SAMPLETYPE => $poItem->sampleType[tblID::NAME]
        ];
        return $oList;
    }
}
cManifestOrmUtils::$flip_replacements = array_flip(cManifestOrmUtils::$replacements);
