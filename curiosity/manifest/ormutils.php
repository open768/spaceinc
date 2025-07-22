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
    const TIMESLOT = 10;

    //************************************************************************************************
    /**
     * searches for product
     * @param string $psPartial 
     * @return null|array 
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

    //*********************************************************************
    static function get_products(string $psSol, ?string $psInstr = null): array {
        // read the img files for the products
        cTracing::enter();
        $oRawData = cCuriosityORMManifest::get_all_sol_data($psSol, $psInstr, eSpaceSampleTypes::SAMPLE_NONTHUMBS);
        $aProducts = [];
        foreach ($oRawData->data as $oItem)
            $aProducts[] = $oItem->product;
        cTracing::leave();
        return $aProducts;
    }

    static function find_sequential_product(string $psProduct, string $psDirection, ?bool $pbSameInstrument = true): cSpaceProductData {
        cTracing::enter();
        $iMission = cCuriosityORMManifest::$mission_id;
        $oCollection = tblProducts::find_sequential_product($iMission, $psProduct, $psDirection, $pbSameInstrument);
        $aOutput = cSpaceManifestUtils::map_collection($oCollection);
        cTracing::leave();
        return $aOutput[0];
    }


    //************************************************************************************************
    static function get_random_images(string $psInstrumentPattern, int $piHowmany) {
        cTracing::enter();
        $iMission = cCuriosityORMManifest::$mission_id;
        //get instruments
        $aInstruments = tblInstruments::get_matching_ids_from_pattern($iMission, $psInstrumentPattern);
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

    //********************************************************
    static function get_calendar(string $psSol) {
        cTracing::enter();
        cDebug::write("getting instruments");

        $oInstruments = cCuriosityInstrument::getInstrumentList();
        /** @var cManifestSolData $oData */
        $oData = cCuriosityORMManifest::get_all_sol_data($psSol, null, eSpaceSampleTypes::SAMPLE_NONTHUMBS);
        $aManData = $oData->data;

        $oData = (object) [
            "sol" => $psSol,
            "cal" => [],
            "instr" => $oInstruments
        ];

        cDebug::write("processing images");
        $aCal = [];
        /** @var cSpaceProductData $oItem */
        foreach ($aManData as $oItem) {
            //ignore thumbnails
            if ($oItem->sample_type === cCuriosityProduct::THUMB_SAMPLE_TYPE)
                continue;

            //Get instruments
            $sInstr = $oItem->instr;
            if ($sInstr == null)
                cDebug::error("no instrument");


            //work out the date
            $epoch = $oItem->utc_date;
            try {
                $dDate = new DateTime("@$epoch");
            } catch (Exception $e) {
                //try and parse the date anyway
                $dDate = new DateTime($epoch);
            }
            $sDate = $dDate->format("d-m-y");

            if (!array_key_exists($sDate, $aCal)) $aCal[$sDate] = [];

            //work out the time key
            $sHour = $dDate->format("H");
            $sMin = $dDate->format("i");
            $sMin =  intdiv($sMin, self::TIMESLOT) * self::TIMESLOT;
            $sMin = str_pad($sMin, 2, "0", STR_PAD_LEFT);
            $sTimeKey = "$sHour:$sMin";

            //debug
            /*
            $sOriginal = $dDate->format("d-m-y H:i");
            cDebug::write("original: $sOriginal -> $sDate $sTimeKey");
            */

            //add the entry to the array
            if (!array_key_exists($sTimeKey, $aCal[$sDate])) $aCal[$sDate][$sTimeKey] = [];
            $aCal[$sDate][$sTimeKey][] = (object)["i" => $sInstr, "d" => $epoch, "p" => $oItem->product];
        }
        $oData->cal = $aCal;

        cTracing::leave();
        return $oData;
    }
}
