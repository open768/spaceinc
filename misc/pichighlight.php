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

require_once  "$phpInc/ckinc/objstoredb.php";
require_once  "$spaceInc/misc/indexes.php";
require_once  "$spaceInc/misc/realms.php";
require_once  "$phpInc/ckinc/http.php";
require_once  "$phpInc/ckinc/hash.php";
require_once  "$spaceInc/curiosity/curiosity.php";

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
        $iCount = $oDB->get_oldstyle($psSol, self::MOSAIC_COUNT_FILENAME);

        if ($iCount == null) $iCount = 0;
        cDebug::leave();
        return $iCount;
    }
    //**********************************************************************
    static private function pr_put_mosaic_sol_hilight_count($psSol, $piCount) {
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $oDB->put_oldstyle($psSol, self::MOSAIC_COUNT_FILENAME, $piCount);
        cSpaceIndex::update_top_sol_index($psSol, cSpaceIndex::MOSAIC_SUFFIX);
    }

    //**********************************************************************
    static private function pr_generate_mosaic($psSol, $paData) {
        global $root;
        $aImgList = [];

        //first make sure all the thumbnails are actually there
        foreach ($paData as $sInstr => $aInstrData) {
            cDebug::write("processing thumbs for $sInstr");
            foreach ($aInstrData as $sProd => $sProdData) {
                try {
                    $aData = cSpaceImageHighlight::get_thumbs($psSol, $sInstr, $sProd);
                } catch (Exception $e) {
                    continue;
                }
                foreach ($aData["u"] as $sPath)
                    $aImgList[] = $sPath;
            }
        }

        //the folder has to be there 
        $sFolder = $root . "/" . self::MOSAIC_FOLDER;
        if (!file_exists($sFolder)) {
            cDebug::write("creating folder: $sFolder");
            mkdir($sFolder, 0755, true); //folder needs to readable by apache
        }

        //now combine the highlights into a single mosaic
        $iCount = count($aImgList);
        cDebug::write("combining $iCount thumbnails");
        $iRows = ceil($iCount / self::MOSAIC_WIDTH);
        cDebug::write("into a Mosaic with size " . self::MOSAIC_WIDTH . " x $iRows");
        $iWidth = self::BORDER_WIDTH + self::MOSAIC_WIDTH * (cSpaceImageHighlight::CROP_WIDTH + self::BORDER_WIDTH);
        $iHeight = self::BORDER_WIDTH + $iRows * (cSpaceImageHighlight::CROP_HEIGHT + self::BORDER_WIDTH);

        $oDest = imagecreatetruecolor($iWidth, $iHeight);

        $iRow = 0;
        $iCol = 0;
        $iX = self::BORDER_WIDTH;
        $iY = self::BORDER_WIDTH;

        for ($i = 0; $i < $iCount; $i++) {
            //load the original image
            $sThumbFilename = $root . "/" . $aImgList[$i];
            if (!file_exists($sThumbFilename)) continue;

            $oThumbImg = imagecreatefromjpeg($sThumbFilename);

            //copy it into the mosaic
            //cDebug::write("copying image into $iX, $iY");
            imagecopy($oDest, $oThumbImg, $iX, $iY, 0, 0,  cSpaceImageHighlight::CROP_WIDTH, cSpaceImageHighlight::CROP_HEIGHT);

            //next
            imagedestroy($oThumbImg);
            $iCol++;
            $iX += (self::BORDER_WIDTH + cSpaceImageHighlight::CROP_WIDTH);
            if ($iCol >= self::MOSAIC_WIDTH) {
                $iRow++;
                $iCol = 0;
                $iX = self::BORDER_WIDTH;
                $iY += (self::BORDER_WIDTH + cSpaceImageHighlight::CROP_HEIGHT);
            }
        }

        //write out the results
        $sImageFile = self::MOSAIC_FOLDER . "/$psSol.jpg";
        $sReal = "$root/$sImageFile";
        imagejpeg($oDest, $sReal, cSpaceImageHighlight::THUMB_QUALITY);
        imagedestroy($oDest);

        return $sImageFile;
    }

    //**********************************************************************
    static function get_sol_high_mosaic($psSol) {
        global $root;

        $oData = cSpaceImageHighlight::get_all_highlights($psSol);
        $iCount = cSpaceImageHighlight::count_highlights($oData);
        cDebug::write("there were $iCount highlights");
        if ($iCount == 0) {
            cDebug::write("no highlights to create a mosaic from");
            return null;
        }

        //------------------------------------------------------------------
        //does the count match what is stored - in that case the mosaic has allready been produced
        $iStoredCount = self::get_mosaic_sol_highlight_count($psSol);
        if ($iStoredCount != $iCount) {
            cDebug::write("but only $iStoredCount were previously known");
            //generate the mosaic
            $sMosaic = self::pr_generate_mosaic($psSol, $oData);

            //write out the count 
            self::pr_put_mosaic_sol_hilight_count($psSol, $iCount);
        }

        //------------------------------------------------------------------
        $sMosaicFile = self::MOSAIC_FOLDER . "/$psSol.jpg";
        if (!file_exists("$root/$sMosaicFile")) {
            cDebug::write("regenerating missing mosaic file");
            $sMosaic = self::pr_generate_mosaic($psSol, $oData);
        }


        return self::MOSAIC_FOLDER . "/$psSol.jpg";
    }
}
cSpaceImageMosaic::init_obj_store_db();

//###############################################################
class cSpaceImageHighlight {
    const IMGHIGH_FILENAME = "[imgbox].txt";
    const THUMBS_FILENAME = "[thumbs].txt";
    const THUMBS_FOLDER = "images/[highs]/";
    const CROP_WIDTH = 120;
    const CROP_HEIGHT = 120;
    const THUMB_QUALITY = 90;
    private static $objstoreDB = null;


    //********************************************************************
    static function init_obj_store_db() {
        if (!self::$objstoreDB)
            self::$objstoreDB = new cObjStoreDB(cSpaceRealms::IMAGE_HIGHLIGHT);
    }

    //######################################################################
    //# GETTERS functions
    //######################################################################
    // #######################################################################
    // # TODO: TODO: TODO: TODO: TODO: TODO: TODO: TODO: TODO: TODO: TODO: 
    // #	make this generic can use take any image as its input
    // #	should need a "Key" and "image url" to do its work
    // #	shouldnt be tied to curiosity
    // #######################################################################

    static function get($psSol, $psInstrument, $psProduct) {
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $sFolder = "$psSol/$psInstrument/$psProduct";
        $aData = $oDB->get_oldstyle($sFolder, self::IMGHIGH_FILENAME);
        $aOut = ["s" => $psSol, "i" => $psInstrument, "p" => $psProduct, "d" => $aData];
        return $aOut;
    }

    //**********************************************************************
    private static function pr_get_image($psSol, $psInstrument, $psProduct) {
        //get the original image once 
        $oInstrumentData = cCuriosity::getProductDetails($psSol, $psInstrument, $psProduct);
        $sImageUrl = null;

        if (isset($oInstrumentData["d"]["i"]))
            $sImageUrl = $oInstrumentData["d"]["i"];
        else {
            cDebug::write("no image found");
            return null;
        }

        cDebug::write("fetching image from '$sImageUrl'");
        $oHttp = new cHttp();
        $oMSLImg = $oHttp->fetch_image($sImageUrl);
        return $oMSLImg;
    }

    //**********************************************************************
    private static function pr_perform_crop($poImg, $piX, $piY, $psOutfile) {
        global $root;
        cDebug::write("cropping to $piX, $piY");

        $oDest = imagecreatetruecolor(self::CROP_WIDTH, self::CROP_HEIGHT);
        cDebug::write("cropping ($piX, $piY), w=" . self::CROP_WIDTH . " h=" . self::CROP_HEIGHT);
        imagecopy($oDest, $poImg, 0, 0, $piX, $piY, self::CROP_WIDTH, self::CROP_HEIGHT);

        //write out the file
        $sFilename = "$root/$psOutfile";
        $sFolder = dirname($sFilename);
        if (!file_exists($sFolder)) {
            cDebug::write("creating folder: $sFolder");
            mkdir($sFolder, 0755, true); //folder needs to readable by apache
        }

        cDebug::write("writing jpeg to $sFilename");
        imagejpeg($oDest, $sFilename, self::THUMB_QUALITY);
        imagedestroy($oDest);
    }

    //**********************************************************************
    // this function should be multithreaded when the software becomes a #product# #TBD#
    static function get_thumbs($psSol, $psInstrument, $psProduct) {
        global $root;

        $bUpdated = false;
        $oMSLImg = null;

        //get existing thumbnail details  
        $sPath = "$psSol/$psInstrument/$psProduct/" . self::THUMBS_FILENAME;
        $aThumbs = cHash::get($sPath);
        if ($aThumbs == null)    $aThumbs = [];

        //get the highlights for the selected product
        $aHighs = self::get($psSol, $psInstrument, $psProduct);
        if ($aHighs["d"]) {

            //work through each checking if the thumbnail is present
            for ($i = 0; $i < count($aHighs["d"]); $i++) {
                //figure out where stuff should go 
                $sOutThumbfile = self::THUMBS_FOLDER . "$psSol/$psInstrument/$psProduct/$i.jpg";
                $sReal = "$root/$sOutThumbfile";

                // key that identifies the thumbnail uses coordinates
                $oItem = $aHighs["d"][$i];
                $sKey = $psProduct . $oItem["t"] . $oItem["l"];

                //check if the array entry exists and the thumbnail exists
                if (isset($aThumbs[$sKey]))
                    if (file_exists($sReal)) continue;

                //---------------split this out---------------------
                //if you got here something wasnt there - regenerate the thumbnail
                cDebug::write("creating thumbnail ");
                if (!$oMSLImg) $oMSLImg = self::pr_get_image($psSol, $psInstrument, $psProduct);

                //get the coordinates of the box
                preg_match("/^(\d*)/", $oItem["l"], $aMatches);
                $iX = $aMatches[0];
                if ($iX < 0) $iX = 0;
                preg_match("/^(\d*)/", $oItem["t"], $aMatches);
                $iY = $aMatches[0];
                if ($iY < 0) $iY = 0;

                //perform the crop
                self::pr_perform_crop($oMSLImg, $iX, $iY, $sOutThumbfile);

                //update the structure
                $aThumbs[$sKey] = $sOutThumbfile;
                $bUpdated = true;
            }

            //update the objstore if necessary
            if ($bUpdated)
                cHash::put($sPath, $aThumbs, true);

            //we dont want to hang onto the original jpeg
            if ($oMSLImg) {
                cDebug::write("destroying image");
                imagedestroy($oMSLImg);
            }
        }

        //return the data
        $aData = ["s" => $psSol, "i" => $psInstrument, "p" => $psProduct, "u" => array_values($aThumbs)];
        return $aData;
    }


    /**
     * gets the highlights for all products in the sol
     * 
     * @param string $psSol 
     * @return array
     */
    static function get_all_highlights($psSol) {
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
                $oHighlites = self::get($psSol, $sInstrument, $sProduct);
                $aData[$sInstrument][$sProduct] = $oHighlites["d"];
            }
        }
        return $aData;
    }

    //**********************************************************************
    static function count_highlights($paData) {
        $iCount = 0;

        if ($paData == null)     return 0;

        foreach ($paData as $sInstr => $aInstrData)
            foreach ($aInstrData as $sProduct => $aProdData)
                $iCount += count($aProdData);
        return $iCount;
    }

    //######################################################################
    //# INDEX functions
    //######################################################################
    static function get_sol_highlighted_products($psSol) {
        $oResult = cSpaceIndex::get_sol_data($psSol, cSpaceIndex::HILITE_SUFFIX);
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
    static function set($psSol, $psInstrument, $psProduct, $psTop, $psLeft, $psUser) {
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        //get the file from the object store to get the latest version
        $sFolder = "$psSol/$psInstrument/$psProduct";
        $aData = ["t" => $psTop, "l" => $psLeft, "u" => $psUser];
        $oDB->add_to_array_oldstyle($sFolder, self::IMGHIGH_FILENAME, $aData); //store highlight
        cSpaceIndex::update_indexes($psSol, $psInstrument, $psProduct, 1, cSpaceIndex::HILITE_SUFFIX);
        return "ok";
    }

    //######################################################################
    //# ADMIN functions
    //######################################################################
    static function reindex() {
        cSpaceIndex::reindex(1, cSpaceIndex::HILITE_SUFFIX, self::IMGHIGH_FILENAME);
    }

    static function kill_highlites($psSol, $psInstr, $psProduct, $psWhich) {
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $sFolder = "$psSol/$psInstr/$psProduct";
        $oDB->kill_oldstyle($sFolder, self::IMGHIGH_FILENAME);
        cDebug::write("now reindex the image highlihgts");
    }
}
cSpaceImageHighlight::init_obj_store_db();
