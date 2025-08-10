<?php

/**************************************************************************
Copyright (C) Chicken Katsu 2013 -2024

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
 **************************************************************************/

require_once  cAppGlobals::$ckPhpInc . "/common.php";
require_once  cAppGlobals::$ckPhpInc . "/blobber.php";
require_once  cAppGlobals::$ckPhpInc . "/image.php";
require_once  cAppGlobals::$ckPhpInc . "/cached_http.php";
require_once  cAppGlobals::$spaceInc . "/missions/mission.php";
require_once  cAppGlobals::$spaceInc . "/curiosity/constants.php";
require_once  cAppGlobals::$spaceInc . "/curiosity/instrument.php";
require_once  cAppGlobals::$spaceInc . "/curiosity/static.php";
require_once  cAppGlobals::$spaceInc . "/curiosity/curiositypds.php";
require_once  cAppGlobals::$spaceInc . "/curiosity/manifest/manifest.php";
require_once  cAppGlobals::$spaceInc . "/curiosity/product.php";


//##########################################################################

class cCuriosity implements iMission {


    private static $Instruments, $instrument_map;

    static function get_mission_name() {
        return cCuriosityConstants::MISSION_NAME;
    }


    static function get_mission_id() {
        return cCuriosityConstants::MISSION_ID;
    }

    static function get_mission_url() {
        return cCuriosityConstants::MISSION_URL;
    }



    //*****************************************************************************
    public static function search_product($psSearch) {
        cTracing::enter();

        //check for a valid product ID - will error if not
        cCuriosityPDS::explode_productID($psSearch);

        $oData = cMSLManifestOrmUtils::search_for_product($psSearch);

        //return the product data
        cTracing::leave();
        return $oData;
    }

    //*****************************************************************************
    public static function getSolRawData($psSol, $psInstrument = null, $pbThumbs = false): cCuriosityInstrument {
        cTracing::enter();
        $oJson = cCuriosityJPLManifest::getSolRawData($psSol);
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

        cTracing::leave();
        return $oData;
    }

    //*****************************************************************************
    public static function getSolData($psSol, $psInstrument = null, $pbThumbs = false): cCuriosityInstrument {
        cTracing::enter();
        $oData = new cCuriosityInstrument($psInstrument);  //put all images under a single instrument
        cTracing::leave();
        return $oData;
    }

    //*****************************************************************************
    public static function getSolList() {
        cTracing::enter();

        //get the manifest
        $oManifest = cCuriosityJPLManifest::getManifest();
        $aSols = $oManifest->sols;
        $aData = [];

        //extract sols - should be cached ideally!
        foreach ($aSols as $oSol) {
            $iSol = $oSol->sol;
            $sDate = $oSol->last_updated;
            $aData[] = ["sol" => $iSol, "date" => $sDate];
        }

        cTracing::leave();
        return $aData;
    }

    //*****************************************************************************
    public static function nextSol($piSol, $piIncrement) {
        cTracing::enter();

        $aSols = self::getSolList();
        $iCount = count($aSols);

        for ($i = 0; $i < $iCount; $i++)
            if ($aSols[$i]["sol"] == $piSol) {
                $i2 = $i + $piIncrement;
                if (($i2 >= 0) && ($i2 < $iCount))
                    return $aSols[$i2]["sol"];
            }

        cTracing::leave();
        return null;
    }

    //*****************************************************************************
    /**returns a list of matching instrument names */
    public static function getSolInstrumentList($piSol) {
        cTracing::enter();

        cDebug::write("Getting instrument list for sol " . $piSol);

        $aInstrumentNames = cCuriosityORMManifest::get_instruments_for_sol($piSol);
        $aFull = cCuriosityInstrument::get_matching($aInstrumentNames);

        cTracing::leave();
        return $aFull;
    }

    //*****************************************************************************
    /**
     * gets PDS details 
     * @param mixed $psSol 
     * @param mixed $psInstrument 
     * @param mixed $psProduct 
     */
    public static function getProductDetails($psSol, $psInstrument, $psProduct) {
        cTracing::enter();

        //check if the instrument might be an abbreviation
        $sInstr = cCuriosityInstrument::getInstrumentName($psInstrument);
        $aOutput = [
            cSpaceUrlParams::SOL => $psSol,
            cSpaceUrlParams::INSTRUMENT => $sInstr,
            cSpaceUrlParams::PRODUCT => $psProduct,
            cSpaceUrlParams::DATA => null,
            "max" => null,
            "item" => null,
            "migrate" => null
        ];

        //get the data
        $oRawData = self::getSolRawData($psSol, $sInstr); //using raw details is fine as it does need raw data
        // if ($oInstrumentData != null) cDebug::vardump($oInstrumentData);
        $aInstrumentImages = $oRawData->data;
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
        cTracing::leave();
        return $aOutput;
    }

    //*****************************************************************************
    static function deleteSolData($piSol) {
        cTracing::enter();
        cCuriosityORMManifestIndexer::delete_sol_index($piSol);
        cTracing::leave();
    }
}
cMissions::add_mission(cCuriosity::class);

//##########################################################################
//#
//##########################################################################
class cCuriosityThumbtUtil {
    public static function bodge_EI_I1_D(string $psProduct) {
        //this is a bodge to correct an unrecognised product id 
        $sProduct = str_replace("E1_D", "I1_D", $psProduct);
        return $sProduct;
    }

    public static function get_EDR_regex($psProduct) {
        $sRegex = preg_replace("/EDR_\w/", "EDR_.", $psProduct);    // a bodge to correct an unrecognised product id 
        return "/$sRegex/";
    }
}
//##########################################################################
//#
//##########################################################################
class cCuriosityImages implements iMissionImages {
    const LOCAL_THUMB_FOLDER = "images/[thumbs]";
    const THUMBNAIL_QUALITY = 90;
    const THUMBNAIL_HEIGHT = 120;

    //*****************************************************************************
    public static function getThumbBlobData($psSol, $psInstr, $psProduct) {
        cTracing::enter();

        $sImgUrl = cCuriosityImages::getImageUrl($psSol, $psInstr, $psProduct);
        if ($sImgUrl == null)
            cDebug::error("unable to find image for $psSol, $psInstr, $psProduct");
        $aData = cThumbNailer::get_thumbnail_blob_data($sImgUrl, self::THUMBNAIL_HEIGHT, self::THUMBNAIL_QUALITY);

        cTracing::leave();
        return $aData;
    }

    public static function getImageUrl($psSol, $psInstrument, $psProduct) {
        cTracing::enter();
        $oDetails = cCuriosity::getProductDetails($psSol, $psInstrument, $psProduct);
        if ($oDetails["d"])
            return $oDetails["d"]["i"];
        else
            cDebug::error("no image found");

        cTracing::leave();
    }

    //*****************************************************************************
    static function getInstrumentImageDetails($paInstrumentImages, $psProduct) {
        $oDetails = null;
        $oResult = null;
        cTracing::enter();
        $aNotFound = [];

        $iFoundIndex = -1;
        cDebug::write("looking for $psProduct");
        foreach ($paInstrumentImages as $iItemIndex => $aItem) {
            $sItemProduct = $aItem["p"];
            if ($sItemProduct === $psProduct) {
                $oDetails = $aItem;
                $iFoundIndex = $iItemIndex;
                cDebug::write("found $psProduct");

                break;
            } else
                $aNotFound[] = $sItemProduct;
        }
        //if nothing found
        if ($oDetails == null) {
            $sList = join(", ", $aNotFound);
            cDebug::write("no instrument data found for $psProduct in [$sList]");
            $oResult = null;
        } else {
            $iCount = count($paInstrumentImages);
            $oResult = ["d" => $oDetails, "max" => $iCount, "item" => $iFoundIndex + 1];
        }

        cTracing::leave();
        return $oResult;
    }

    //*****************************************************************************
    private static function _matchThumbnails(cManifestSolData $poProdData, cManifestSolData $poThumbData) {
        /** @var  cSpaceProductData[] $aThumbData */
        $aThumbData = $poThumbData->data;
        $aThumbAssoc = [];
        foreach ($aThumbData as $oThumb)
            $aThumbAssoc[$oThumb->product] = $oThumb;
        $aThumbAssocKeys = array_keys($aThumbAssoc);

        /** @var  cSpaceProductData[] $aProdData */
        $aProdData = $poProdData->data;

        //go through the product array and try and match it up with a thumbnail
        /** @var cSpaceProductData $oProd */
        foreach ($aProdData as $oProd) {
            $sProduct = $oProd->product;

            //-------------------check if this product is contained in thumbnails
            cDebug::write("🔎 Looking for product: $sProduct");
            $sBodgeProduct = cCuriosityThumbtUtil::bodge_EI_I1_D($sProduct);
            if (in_array($sBodgeProduct, $aThumbAssocKeys)) {
                cDebug::write("1.. thumb  found for $sProduct");
                $oThumbProduct = $aThumbAssoc[$sBodgeProduct];
                $oProd->thumb_url = $oThumbProduct->image_url;
                continue;
            }

            //-------------------check for a partial match (using regex)-----------------          
            if (stristr($sProduct, "EDR_") !== false) {
                $sRegex = cCuriosityThumbtUtil::get_EDR_regex($sProduct);    // a bodge to correct an unrecognised product id 
                cDebug::write("2.. trying regex $sRegex");
                $aMatches = preg_grep($sRegex, $aThumbAssocKeys); //search array for a match
                if ($aMatches) {
                    $sMatch = array_values($aMatches)[0];
                    $oThumbProduct = $aThumbAssoc[$sMatch];
                    $oProd->thumb_url = $oThumbProduct->image_url;
                    cDebug::write("2.. product found for $sProduct= $sMatch");
                    continue;
                }
            }

            //---------------------break the productID into its parts
            try {
                $aProduct = cCuriosityPDS::explode_productID($sProduct);
            } catch (Exception $e) {
                cDebug::write(" 3.. ❌ not a known explodable product ID:" . $sProduct);
                continue;
            }

            //----------------one last throw of the dice, create a product string and look for it again
            $sPartial = sprintf("/%04d%s%06d%03d/", $aProduct["sol"], $aProduct["instrument"], $aProduct["seqid"], $aProduct["seq line"], $aProduct["CDPID"]);
            $aMatches = preg_grep($sPartial, $aThumbAssocKeys); //search array for a match
            if (count($aMatches) > 0) {
                $sMatch = array_values($aMatches)[0];
                cDebug::write("4.. thumbnail $sProduct matches $sMatch");
                $oThumbProduct = $aThumbAssoc[$sMatch];
                $oProd->thumb_url = $oThumbProduct->image_url;
                continue;
            }

            //give up
            cDebug::write("5.. Thumbnail didnt match $sProduct");
        }

        return $aProdData;
    }

    //*****************************************************************************
    public static function getThumbnails($psSol, $psInstrument) {
        cTracing::enter();

        $sMissionID = cCuriosity::get_mission_id();

        //----------check that there are thumbnails
        /** @var cManifestSolData $oThumbData */
        $oThumbData = cCuriosityORMManifest::get_all_sol_data($psSol, $psInstrument, eSpaceSampleTypes::SAMPLE_THUMBS);
        if (count($oThumbData->data) == 0) {
            cDebug::write("no thumbnails found");
            return null;
        }

        //----------check that there are products
        /** @var cManifestSolData $oProdData */
        $oProdData = cCuriosityORMManifest::get_all_sol_data($psSol, $psInstrument, eSpaceSampleTypes::SAMPLE_NONTHUMBS);
        if (count($oProdData->data) == 0) {
            cDebug::write("no products found");
            return null;
        }

        //---------try to match up products with thumbnails
        $_ = self::_matchThumbnails($oProdData, $oThumbData);

        cTracing::leave();
        return $oProdData;
    }
}

//cMissions::register("MSL", cCuriosity, cCuriosityImages);
