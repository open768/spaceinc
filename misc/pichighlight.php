<?php

/**************************************************************************
Copyright (C) Chicken Katsu 2013 - 2024
This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
//
 **************************************************************************/

require_once  cAppGlobals::$phpInc . "/ckinc/objstoredb.php";
require_once  cAppGlobals::$phpInc . "/ckinc/image.php";
require_once  cAppGlobals::$spaceInc . "/misc/indexes.php";
require_once  cAppGlobals::$spaceInc . "/misc/realms.php";
require_once  cAppGlobals::$phpInc . "/ckinc/http.php";
require_once  cAppGlobals::$phpInc . "/ckinc/hash.php";
require_once  cAppGlobals::$spaceInc . "/curiosity/curiosity.php";

//###############################################################
class cSpaceImageMosaic {
    const MOSAIC_COUNT_FILENAME = "[moscount].txt";
    const MOSAIC_FOLDER = "images/[mosaics]";
    const MOSAIC_WIDTH = 8;
    const BORDER_WIDTH = 5;

    private static $objstoreDB = null;


    //********************************************************************
    static function init_obj_store_db() {
        if (!self::$objstoreDB)
            self::$objstoreDB = new cObjStoreDB(cSpaceRealms::MOSAICS);
    }

    //********************************************************************
    static function get_mosaic_sol_highlight_count($psSol) {
        cDebug::enter();
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $iCount = $oDB->get("$psSol/" . self::MOSAIC_COUNT_FILENAME);

        if ($iCount == null) $iCount = 0;
        cDebug::leave();
        return $iCount;
    }

    //**********************************************************************
    static private function pr_put_mosaic_sol_hilight_count($psSol, $piCount) {
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $oDB->put("$psSol/" . self::MOSAIC_COUNT_FILENAME, $piCount);
        cSpaceIndex::update_top_sol_index($psSol, cSpaceIndex::MOSAIC_SUFFIX);
    }

    //**********************************************************************
    static private function pr_generate_mosaic(string $psSol, array $paData): string {
        cDebug::enter();

        $aImgList = [];

        //generate blobs
        foreach ($paData as $sInstr => $aInstrData) {
            foreach ($aInstrData as $sProd => $oProdData)
                foreach ($oProdData->data as $oBox)
                    $oBlob = cSpaceImageHighlight::get_box_blob($oProdData, $oBox);
        }


        //now combine the highlights into a single mosaic
        $iCount = count($aImgList);
        cDebug::write("combining $iCount thumbnails");
        $iRows = ceil($iCount / self::MOSAIC_WIDTH);
        cDebug::write("into a Mosaic with size " . self::MOSAIC_WIDTH . " x $iRows");
        $iWidth = self::BORDER_WIDTH + self::MOSAIC_WIDTH * (cAppConsts::CROP_WIDTH + self::BORDER_WIDTH);
        $iHeight = self::BORDER_WIDTH + $iRows * (cAppConsts::CROP_HEIGHT + self::BORDER_WIDTH);

        $oDest = imagecreatetruecolor($iWidth, $iHeight);

        $iRow = 0;
        $iCol = 0;
        $iX = self::BORDER_WIDTH;
        $iY = self::BORDER_WIDTH;

        for ($i = 0; $i < $iCount; $i++) {
            //load the original image
            $sThumbFilename = cAppGlobals::$root . "/" . $aImgList[$i];
            if (!file_exists($sThumbFilename)) continue;

            $oThumbImg = imagecreatefromjpeg($sThumbFilename);

            //copy it into the mosaic
            //cDebug::write("copying image into $iX, $iY");
            imagecopy($oDest, $oThumbImg, $iX, $iY, 0, 0,  cAppConsts::CROP_WIDTH, cAppConsts::CROP_HEIGHT);

            //next
            imagedestroy($oThumbImg);
            $iCol++;
            $iX += (self::BORDER_WIDTH + cAppConsts::CROP_WIDTH);
            if ($iCol >= self::MOSAIC_WIDTH) {
                $iRow++;
                $iCol = 0;
                $iX = self::BORDER_WIDTH;
                $iY += (self::BORDER_WIDTH + cAppConsts::CROP_HEIGHT);
            }
        }

        //write out the results
        $sImageFile = self::MOSAIC_FOLDER . "/$psSol.jpg";
        $sReal = cAppGlobals::$root . "/$sImageFile";
        imagejpeg($oDest, $sReal, cAppConsts::THUMB_QUALITY);
        imagedestroy($oDest);

        cDebug::leave();
        return $sImageFile;
    }

    //**********************************************************************
    //@TODO convert to using blobber
    static function get_sol_high_mosaic($psSol) {
        cDebug::enter();

        $aHighData = cSpaceImageHighlight::get_all_highlights($psSol, true);
        $iCount = cSpaceImageHighlight::count_highlights($aHighData);
        cDebug::write("there were $iCount highlights");
        if ($iCount == 0) {
            cDebug::write("no highlights to create a mosaic from");
            return null;
        }

        //------------------------------------------------------------------
        //does the count match what is stored - in that case the mosaic has allready been produced
        $iStoredCount = self::get_mosaic_sol_highlight_count($psSol);
        if ($iStoredCount != $iCount || cDebug::$IGNORE_CACHE) {
            if ($iStoredCount > 0) cDebug::write("but only $iStoredCount were previously known");
            //generate the mosaic
            $sMosaic = self::pr_generate_mosaic($psSol, $aHighData);

            //write out the count 
            self::pr_put_mosaic_sol_hilight_count($psSol, $iCount);
        } else
            cDebug::extra_debug("no need to regenerate mosaic - count matches");

        //------------------------------------------------------------------
        $sMosaicFile = self::MOSAIC_FOLDER . "/$psSol.jpg";
        if (!file_exists(cAppGlobals::$root . "/$sMosaicFile")) {
            cDebug::write("regenerating missing mosaic file");
            $sMosaic = self::pr_generate_mosaic($psSol, $aHighData);
        } else
            cDebug::extra_debug("no need to regenerate mosaic - file exists");


        cDebug::leave();
        return self::MOSAIC_FOLDER . "/$psSol.jpg";
    }
}
cSpaceImageMosaic::init_obj_store_db();

//###############################################################
class cSpaceImageHighlight {
    const IMGHIGH_FILENAME = "[imgbox].txt";
    const THUMBS_FILENAME = "[thumbs].txt";
    const THUMBS_FOLDER = "images/[highs]/";
    private static $objstoreDB = null;


    //********************************************************************
    static function init_obj_store_db() {
        if (!self::$objstoreDB)
            self::$objstoreDB = new cObjStoreDB(cSpaceRealms::IMAGE_HIGHLIGHT);
    }

    //######################################################################
    //# GETTERS functions
    //######################################################################
    static function pr_get_filename($psSol, $psInstrument, $psProduct) {
        $sFolder = "$psSol/$psInstrument/$psProduct";
        return "$sFolder/" . self::IMGHIGH_FILENAME;
    }

    //****************************************************************
    static function get(string $psSol, string $psInstrument, string $psProduct, bool $pbGetImgUrl = false): cSpaceProductData {
        cDebug::enter();
        cDebug::extra_debug("s:$psSol, i:$psInstrument, p:$psProduct");
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $sFile = self::pr_get_filename($psSol, $psInstrument, $psProduct);
        $aData = $oDB->get($sFile);

        $oOut = new cSpaceProductData; {
            $oOut->mission = cSpaceMissions::CURIOSITY;
            $oOut->sol = $psSol;
            $oOut->instr = $psInstrument;
            $oOut->product = $psProduct;
            $oOut->data = $aData;
        }

        if ($pbGetImgUrl) {
            $oProduct = cCuriosityManifestUtils::search_for_product($psProduct);
            $oOut->image_url = $oProduct->image_url;
        }

        cDebug::leave();
        return $oOut;
    }

    //****************************************************************
    static function put(string $psSol, string $psInstrument, string $psProduct, array $paData): void {
        cDebug::enter();
        if (!cAuth::is_role(cAuth::ADMIN_ROLE)) cDebug::error("put can only be done by admin");
        $oDB = self::$objstoreDB;
        $sFile = self::pr_get_filename($psSol, $psInstrument, $psProduct);
        $oDB->put($sFile, $paData);

        cDebug::leave();
    }

    //****************************************************************
    static function get_db(): cObjStoreDB {
        if (!cAuth::is_role(cAuth::ADMIN_ROLE)) cDebug::error("admin function");
        return self::$objstoreDB;
    }


    /**
     * gets the highlights for all products in the sol
     * 
     * @param string $psSol 
     * @return array
     */
    static function get_all_highlights(string $psSol, bool $pbGetImageUrls = false): array {
        cDebug::enter();

        //get which products have highlights
        cDebug::write("getting highlights for sol $psSol");
        $aData = self::get_sol_highlighted_products($psSol);
        if ($aData == null) {
            cDebug::write("no highlights for sol $psSol");
            return null;
        }

        //get all the highlights
        foreach ($aData as $sInstrument => $aProducts) {
            foreach ($aProducts as $sProduct => $iCount) {
                $oHighlites = self::get($psSol, $sInstrument, $sProduct, $pbGetImageUrls);
                $aData[$sInstrument][$sProduct] = $oHighlites;
            }
        }
        cDebug::leave();
        return $aData;
    }

    //**********************************************************************
    static function count_highlights(array $paData): int {
        $iCount = 0;

        if ($paData == null)     return 0;

        foreach ($paData as $sInstr => $aInstrData)
            foreach ($aInstrData as $sProduct => $oProdData)
                $iCount += count($oProdData->data);
        return $iCount;
    }

    //######################################################################
    //# INDEX functions
    //######################################################################
    static function get_sol_highlighted_products($psSol) {
        $oResult = cSpaceIndex::get_sol_index($psSol, cSpaceIndex::HILITE_SUFFIX);
        return $oResult;
    }

    //********************************************************************
    static function get_top_index() {
        cDebug::enter();
        $aResult = cSpaceIndex::get_top_sol_data(cSpaceIndex::HILITE_SUFFIX);
        if ($aResult) ksort($aResult, SORT_NUMERIC);
        cDebug::leave();
        return $aResult;
    }

    static function get_top_mosaic_index() {
        return cSpaceIndex::get_top_sol_data(cSpaceIndex::MOSAIC_SUFFIX);
    }

    //######################################################################
    //# MOSAIC functions
    //######################################################################
    //######################################################################
    //# UPDATE functions
    //######################################################################
    static function pr_is_duplicate($psSol, $psInstrument, $psProduct, $psTop, $psLeft): bool {
        cDebug::enter();
        $bIsDuplicate = false;
        $oResult = self::get($psSol, $psInstrument, $psProduct);
        $aData = $oResult->data;

        if ($aData)
            foreach ($aData as $oBox) {
                $sBoxT = $oBox["t"];
                $sBoxL = $oBox["l"];
                if ($sBoxT === $psTop &&  $sBoxL === $psLeft) {
                    $bIsDuplicate = true;
                    break;
                }
            }
        return $bIsDuplicate;
        cDebug::leave();
    }

    //****************************************************************************
    static function set($psSol, $psInstrument, $psProduct, $psTop, $psLeft, $psUser): string {
        cDebug::enter();
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        //get the file from the object store to get the latest version
        $sFile = self::pr_get_filename($psSol, $psInstrument, $psProduct);
        $aData = ["t" => $psTop, "l" => $psLeft, "u" => $psUser];
        $bIsDuplicate = self::pr_is_duplicate($psSol, $psInstrument, $psProduct, $psTop, $psLeft);
        if ($bIsDuplicate) {
            cDebug::write("duplicate");
            return "duplicate";
        } else {
            $oDB->add_to_array($sFile, $aData); //store highlight
            cSpaceIndex::update_indexes($psSol, $psInstrument, $psProduct, 1, cSpaceIndex::HILITE_SUFFIX);
            return "ok";
        }
        cDebug::leave();
    }


    //************************************************************************
    static function get_box_blob(cSpaceProductData $poHighlight, array $poBox): cCropData {
        cDebug::enter();

        //'img' + sProduct + '_' + sTop + '_' + sLeft
        $sTop = $poBox[cAppUrlParams::HIGHLIGHT_TOP];
        if (substr($sTop, -2) === "px") $sTop = substr($sTop, 0, -2);
        $sTop = floor($sTop);

        $sLeft = $poBox[cAppUrlParams::HIGHLIGHT_LEFT];
        if (substr($sLeft, -2) === "px") $sLeft = substr($sLeft, 0, -2);
        $sLeft = substr($sLeft, 0, -2);
        $sLeft = floor($sLeft);
        $sProduct = $poHighlight->product;

        $oCropData = cCropper::get_crop_blob_data($poHighlight->image_url, $sLeft, $sTop, cAppConsts::CROP_WIDTH, cAppConsts::CROP_HEIGHT);

        cDebug::leave();
        return $oCropData;
    }

    //######################################################################
    //# ADMIN functions
    //######################################################################

    static function kill_highlites($psSol, $psInstrument, $psProduct) {
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $sFile = self::pr_get_filename($psSol, $psInstrument, $psProduct);
        $oDB->kill($sFile);
        cDebug::write("now reindex the image highlihgts");
    }
}
cSpaceImageHighlight::init_obj_store_db();
