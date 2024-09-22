<?php

/**************************************************************************
Copyright (C) Chicken Katsu 2013 -2024

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
 **************************************************************************/

require_once  cAppGlobals::$phpInc . "/ckinc/common.php";
require_once  cAppGlobals::$phpInc . "/ckinc/blobber.php";
require_once  cAppGlobals::$phpInc . "/ckinc/image.php";
require_once  cAppGlobals::$phpInc . "/ckinc/cached_http.php";
require_once  cAppGlobals::$spaceInc . "/missions/mission.php";
require_once  cAppGlobals::$spaceInc . "/curiosity/instrument.php";
require_once  cAppGlobals::$spaceInc . "/curiosity/static.php";
require_once  cAppGlobals::$spaceInc . "/curiosity/curiositypds.php";
require_once  cAppGlobals::$spaceInc . "/curiosity/manifest.php";


//##########################################################################

class cCuriosity implements iMission {
    const PDS_VOLUMES = "http://pds-imaging.jpl.nasa.gov/volumes/msl.html";
    const ALL_INSTRUMENTS = "All";
    const MISSION_NAME = "curiosity";
    const MISSION_ID = "msl";
    const MISSION_URL = "https://science.nasa.gov/mission/msl-curiosity/";

    private static $Instruments, $instrument_map;

    static function get_mission_name() {
        return self::MISSION_NAME;
    }


    static function get_mission_id() {
        return self::MISSION_ID;
    }

    static function get_mission_url() {
        return self::MISSION_URL;
    }

    //*****************************************************************************
    public static function search_product($psSearch) {
        cDebug::enter();

        //check for a valid product ID - will error if not
        $aExploded = cCuriosityPDS::explode_productID($psSearch);

        $oData = cCuriosityManifestUtils::search_for_product($psSearch);

        //return the product data
        cDebug::leave();
        return $oData;
    }

    //*****************************************************************************
    public static function getSolRawData($psSol, $psInstrument = null, $pbThumbs = false): cCuriosityInstrument {
        cDebug::enter();
        $oJson = cCuriosityManifest::getSolData($psSol);
        $oData = new cCuriosityInstrument($psInstrument);  //put all images under a single instrument

        //get the images from the json response
        $aImages = $oJson->images;

        //---work through list of images
        foreach ($aImages as $oItem) {
            $sInstrument = $oItem->instrument;
            //add item to response either if no instrument was supplied, or the item is of the requested instrument 
            if ((!$psInstrument) || ($sInstrument === $psInstrument))
                $oData->add($oItem, $pbThumbs);
        }

        cDebug::leave();
        return $oData;
    }

    //*****************************************************************************
    public static function getSolData($psSol, $psInstrument = null, $pbThumbs = false): cCuriosityInstrument {
        cDebug::enter();
        $oData = new cCuriosityInstrument($psInstrument);  //put all images under a single instrument
        cDebug::leave();
        return $oData;
    }

    //*****************************************************************************
    public static function getSolList() {
        cDebug::enter();

        //get the manifest
        $oManifest = cCuriosityManifest::getManifest();
        $aSols = $oManifest->sols;
        $aData = [];

        //extract sols - should be cached ideally!
        foreach ($aSols as $oSol) {
            $iSol = $oSol->sol;
            $sDate = $oSol->last_updated;
            $aData[] = ["sol" => $iSol, "date" => $sDate];
        }

        cDebug::leave();
        return $aData;
    }

    //*****************************************************************************
    public static function nextSol($piSol, $piIncrement) {
        cDebug::enter();

        $aSols = self::getSolList();
        $iCount = count($aSols);

        for ($i = 0; $i < $iCount; $i++)
            if ($aSols[$i]["sol"] == $piSol) {
                $i2 = $i + $piIncrement;
                if (($i2 >= 0) && ($i2 < $iCount))
                    return $aSols[$i2]["sol"];
            }

        cDebug::leave();
        return null;
    }

    //*****************************************************************************
    public static function getSolInstrumentList($piSol) {
        $aResults = [];
        cDebug::enter();

        cDebug::write("Getting instrument list for sol " . $piSol);

        $aData = cCuriosityManifestUtils::get_instruments_for_sol($piSol);

        cDebug::leave();
        return $aData;
    }

    //*****************************************************************************
    public static function getProductDetails($psSol, $psInstrument, $psProduct) {
        cDebug::enter();

        //check if the instrument might be an abbreviation
        $sInstr = cCuriosityInstrument::getInstrumentName($psInstrument);
        $aOutput = ["s" => $psSol, "i" => $sInstr, "p" => $psProduct, "d" => null, "max" => null, "item" => null, "migrate" => null];

        //get the data
        $oInstrumentData = self::getSolRawData($psSol, $sInstr); //does need raw data
        $aInstrumentImages = $oInstrumentData->data;
        $oDetails = cCuriosityImages::getInstrumentImageDetails($aInstrumentImages, $psProduct);


        //if nothing found look for similar products
        if ($oDetails === null) {
            cDebug::write("Nothing found!! for $psProduct");
            $oPDSData = cCuriosityPDS::search_pds($psSol, $psInstrument, $psProduct);
            if ($oPDSData == null) {
                cDebug::write("drawn a complete blank!");
            } else {
                cDebug::vardump($oPDSData);
                $aOutput["migrate"] = $oPDSData["p"];
                //**** TODO **** start the migration
            }
        } else {
            $aOutput["d"] = $oDetails["d"];
            $aOutput["max"] = $oDetails["max"];
            $aOutput["item"] = $oDetails["item"];
        }

        //return the result
        cDebug::leave();
        return $aOutput;
    }
}
cMissions::add_mission(cCuriosity::class);


class cCuriosityImages implements iMissionImages {
    const LOCAL_THUMB_FOLDER = "images/[thumbs]";
    const THUMBNAIL_QUALITY = 90;
    const THUMBNAIL_HEIGHT = 120;

    //*****************************************************************************
    public static function getThumbBlobData($psSol, $psInstr, $psProduct) {
        cDebug::enter();

        $sImgUrl = cCuriosityImages::getImageUrl($psSol, $psInstr, $psProduct);
        if ($sImgUrl == null)
            cDebug::error("unable to find image for $psSol, $psInstr, $psProduct");
        $aData = cImageFunctions::get_thumbnail_blob_data($sImgUrl, self::THUMBNAIL_HEIGHT, self::THUMBNAIL_QUALITY);

        cDebug::leave();
        return $aData;
    }

    public static function getImageUrl($psSol, $psInstrument, $psProduct) {
        cDebug::enter();
        $oDetails = cCuriosity::getProductDetails($psSol, $psInstrument, $psProduct);
        if ($oDetails["d"])
            return $oDetails["d"]["i"];
        else
            cDebug::error("no image found");

        cDebug::leave();
    }

    //*****************************************************************************
    static function getInstrumentImageDetails($paInstrumentImages, $psProduct) {
        $oDetails = null;
        $oResult = null;
        cDebug::enter();

        cDebug::write("looking for $psProduct");
        $iCount = count($paInstrumentImages);
        for ($i = 0; $i < $iCount; $i++) {
            $aItem = $paInstrumentImages[$i];
            if ($aItem["p"] === $psProduct) {
                $oDetails = $aItem;
                cDebug::write("found $psProduct");
                break;
            }
        }
        //if nothing found

        if ($oDetails == null) {
            $oResult = null;
        } else
            $oResult = ["d" => $oDetails, "max" => $iCount, "item" => $i + 1];

        cDebug::leave();
        return $oResult;
    }
    //*****************************************************************************
    public static function getThumbnails($psSol, $psInstrument) {
        cDebug::enter();
        $oResult = null;

        //get the thumbnails and the non thumbnails
        if ($psInstrument == cCuriosity::ALL_INSTRUMENTS) {
            $oAllSolThumbs = self::getAllSolThumbs($psSol);
            $oResult = self::pr_match_thumbs($psSol, null, $oAllSolThumbs);
        } else {
            $oAllSolThumbs = self::getSolThumbs($psSol, $psInstrument);
            $oResult = self::pr_match_thumbs($psSol, $psInstrument, $oAllSolThumbs);
        }

        cDebug::leave();
        return $oResult;
    }

    //*****************************************************************************
    public static function getAllSolThumbs($psSol) {
        cDebug::enter();
        $oResult = cCuriosity::getSolRawData($psSol, null, true); //doesnt need to use raw data
        cDebug::leave();
        return $oResult;
    }

    //*****************************************************************************
    public static function getSolThumbs($psSol, $psInstrument) {
        cDebug::enter();
        $oResult = cCuriosity::getSolRawData($psSol, $psInstrument, true); //doesnt need to use raw data

        cDebug::leave();
        return $oResult;
    }
    //*****************************************************************************
    private static function pr_match_thumbs($psSol, $psInstrument, $poAllSolThumbs) {
        cDebug::enter();

        $aThumbData = $poAllSolThumbs->data;
        $iThumbCount = count($aThumbData);
        if ($iThumbCount == 0)
            cDebug::write("no thumbnails found");
        else {
            // read the img files for the products
            cDebug::write("Found $iThumbCount thumbnails: ");
            $oRawData = cCuriosity::getSolRawData($psSol, $psInstrument); //doesnt need to use raw data
            $aIData = $oRawData->data;
            $iICount = count($aIData);

            // create a list of product Ids
            $aProducts = [];
            foreach ($aIData as $oIItem) {
                $sProduct = $oIItem["p"];
                $aIProducts[$sProduct] = 1;
            }
            $aIProductKeys = array_keys($aIProducts);
            cDebug::write("product IDs found:");
            cDebug::vardump($aIProductKeys);

            //try to match up thumbmnails to full products or delete
            for ($i = $iThumbCount - 1; $i >= 0; $i--) {
                $aTItem = $aThumbData[$i];
                $sTProduct = $aTItem["p"];
                cDebug::write("0.. product id from Json: $sTProduct");

                //this is a bodge to correct an unrecognised product id 
                $sIProduct = str_replace("I1_D", "E1_D", $sTProduct);

                //check if this product is in  $aIProducts
                if (isset($aIProducts[$sIProduct])) {
                    cDebug::write("1.. product found for $sIProduct");
                    $aTItem["p"] = $sIProduct;
                    $aThumbData[$i] = $aTItem;
                    continue;
                }

                //this is a bodge to correct an unrecognised product id 
                $sRegex = str_replace("EDR_T", "EDR_.", $sTProduct);
                $sRegex  = "/" . $sRegex . "/";
                $aMatches = preg_grep($sRegex, $aIProductKeys); //search array for a match
                if ($aMatches) {
                    $sMatch = array_values($aMatches)[0];
                    $aTItem["p"] = $sMatch;
                    $aThumbData[$i] = $aTItem;
                    cDebug::write("2.. product found for $sIProduct");
                    continue;
                }

                //break the productID into its parts
                try {
                    $aProduct = cCuriosityPDS::explode_productID($sTProduct);
                } catch (Exception $e) {
                    cDebug::write("3.. product not found for " . $sIProduct);
                    continue;
                }

                //one last throw of the dice, create a product string and look for it again
                $sPartial = sprintf("/%04d%s%06d%03d/", $aProduct["sol"], $aProduct["instrument"], $aProduct["seqid"], $aProduct["seq line"], $aProduct["CDPID"]);
                $aMatches = preg_grep($sPartial, $aIProductKeys);
                if (count($aMatches) > 0) {
                    $aValues = array_values($aMatches);
                    cDebug::write("4.. thumbnail $sTProduct matches " . $aValues[0]);
                    $aTItem["p"] = $aValues[0];
                    $aThumbData[$i] = $aTItem;
                    continue;
                }

                //give up
                cDebug::write("5.. Thumbnail didnt match $sPartial");
                unset($aThumbData[$i]); //delete the thumbnail
            }

            if (count($aThumbData) == 0) {
                cDebug::write("5.. no thumbnails matched");
                cDebug::vardump($aIProducts);
            }

            //TBD
            //store the final version of the data			
            $aValues = array_values($aThumbData);
            $poAllSolThumbs->data = $aValues;
        }

        cDebug::leave();
        return ["s" => $psSol, "i" => $psInstrument, "d" => $poAllSolThumbs];
    }
}

//cMissions::register("MSL", cCuriosity, cCuriosityImages);
