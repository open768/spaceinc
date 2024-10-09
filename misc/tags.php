<?php

/**************************************************************************
Copyright (C) Chicken Katsu 2013 -2024

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
 **************************************************************************/
require_once  cAppGlobals::$ckPhpInc . "/objstoredb.php";
require_once  cAppGlobals::$spaceInc . "/misc/realms.php";


//#############################################################################
class cSpaceTagNames {
    const TOP_TAG_NAME_FILE = "[top].txt";
    const TAG_FOLDER = "[tags]";

    //********************************************************************
    static function get_top_tag_names() {
        cDebug::enter();
        /** @var cObjStoreDB $oDB **/
        $oDB = cSpaceTags::$objstoreDB;

        $aTags = $oDB->get("/" . self::TOP_TAG_NAME_FILE);
        //cDebug::vardump($aTags);
        if ($aTags) ksort($aTags);
        cDebug::leave();
        return $aTags;
    }

    //********************************************************************
    static function kill_tag_name($psTag) {
        cDebug::enter(); {

            /** @var cObjStoreDB $oDB **/
            $oDB = cSpaceTags::$objstoreDB;

            //remove entry from top tag file 
            $aData = self::get_top_tag_names();
            if (isset($aData[$psTag])) {
                unset($aData[$psTag]);
                $oDB->put("/" . self::TOP_TAG_NAME_FILE, $aData);
            } else {
                cDebug::write("tag not found");
                return;
            }

            //remove tag index file 
            $filename = $psTag . ".txt";
            $aTags = $oDB->get(self::TAG_FOLDER . "/$filename");
            if ($aTags != null)
                $oDB->kill(self::TAG_FOLDER . "/$filename");
            else {
                cDebug::write("tagindex not found");
                return;
            }

            //remove individual tags
            foreach ($aTags as $sFolder)
                $oDB->kill("$sFolder/" . cSpaceTags::PRODUCT_TAG_FILE);
        }
        cDebug::leave();
    }

    //********************************************************************
    static function update_indexes($psSol, $psInstr, $psProduct, $psTag) {
        if (!is_numeric($psSol)) cDebug::error("sol must be numeric");
        self::update_top_name_index($psTag);
        self::update_tag_name_index($psTag, $psSol, $psInstr, $psProduct);
    }

    //********************************************************************
    static function update_top_name_index($psTag) {
        cDebug::enter();

        cDebug::write("updating index for tag : $psTag");

        // get the existing tags
        $aData = self::get_top_tag_names();
        if (!$aData) $aData = [];

        //update the count
        $count = 0;
        if (isset($aData[$psTag])) $count = $aData[$psTag];
        $count++;
        $aData[$psTag] = $count;
        //cDebug::vardump($aData);

        //write out the data
        /** @var cObjStoreDB $oDB **/
        $oDB = cSpaceTags::$objstoreDB;
        $oDB->put("/" . self::TOP_TAG_NAME_FILE, $aData);
        cDebug::leave();
    }
    //********************************************************************
    static function get_tag_name_index($psTag) {
        cDebug::enter();

        $filename = $psTag . ".txt";
        /** @var cObjStoreDB $oDB **/
        $oDB = cSpaceTags::$objstoreDB;

        $aTags = $oDB->get(self::TAG_FOLDER . "/$filename");
        cDebug::leave();
        if ($aTags) sort($aTags);
        return $aTags;
    }
    //********************************************************************
    static function update_tag_name_index($psTag, $psSol, $psInstrument, $psProduct) {
        cDebug::enter();
        $sFolder = cSpaceTags::get_product_tag_folder($psSol, $psInstrument, $psProduct);
        $filename = $psTag . ".txt";
        /** @var cObjStoreDB $oDB **/
        $oDB = cSpaceTags::$objstoreDB;
        $oDB->add_to_array(self::TAG_FOLDER . "/$filename", $sFolder);
        cDebug::leave();
    }

    //********************************************************************
    static function search_tag_names($psPartial) {
        cDebug::enter();

        if (strlen($psPartial) < 2) cDebug::error("partial match must be at least 2 characeters");
        $aOut = [];
        $aAllNames = self::get_top_tag_names();

        foreach ($aAllNames as $sTag => $iCount)
            if (stristr($sTag, $psPartial))
                $aOut[] = strtolower($sTag);

        cDebug::leave();
        return $aOut;
    }
}

//#############################################################################
class cSpaceTagsIndex {
    static function get_top_sol_index() {
        return cSpaceIndex::get_top_sol_data(cSpaceIndex::TAG_SUFFIX);
    }

    static function get_sol_index($psSol) {
        return cSpaceIndex::get_sol_index($psSol, cSpaceIndex::TAG_SUFFIX);
    }

    static function update_indexes($psSol, $psInstrument, $psProduct, $piValue) {
        cSpaceIndex::update_indexes($psSol, $psInstrument, $psProduct, $piValue, cSpaceIndex::TAG_SUFFIX);
    }
}

//#############################################################################
class cSpaceTags {
    const SOL_TAG_FILE = "[soltag].txt";
    const PRODUCT_TAG_FILE = "[tag].txt";
    const RECENT_TAG = "TAG";
    static $objstoreDB = null;


    //********************************************************************
    //* objdb stuff
    //********************************************************************
    static function init_obj_store_db() {
        if (!self::$objstoreDB)
            self::$objstoreDB = new cObjStoreDB(cSpaceRealms::TAGS);
    }


    //********************************************************************
    //* product
    //********************************************************************
    static function get_product_tag_folder($psSol, $psInstrument, $psProduct) {
        return "$psSol/$psInstrument/$psProduct";
    }

    //********************************************************************
    static function get_product_tag_names($psSol, $psInstrument, $psProduct) {
        $aData = self::get_product_tags($psSol, $psInstrument, $psProduct);
        $aTagNames = [];
        foreach ($aData as $sTag => $oValue)
            array_push($aTagNames, $sTag);
        return $aTagNames;
    }

    //********************************************************************
    static function get_product_tags($psSol, $psInstrument, $psProduct) {

        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $sFolder = self::get_product_tag_folder($psSol, $psInstrument, $psProduct);
        $aTags = $oDB->get("$sFolder/" . self::PRODUCT_TAG_FILE);
        if (!$aTags) $aTags = [];

        return $aTags;
    }

    //********************************************************************
    static function set_product_tag($psSol, $psInstrument, $psProduct, $psTag, $psUser) {
        cDebug::enter();

        //tidy the tags - remove anything non alphanumeric
        $sTag = strtolower($psTag);
        $sTag = preg_replace("/[^a-z0-9\-]/", '', $sTag);

        //get the file from the object store
        $aData = self::get_product_tags($psSol, $psInstrument, $psProduct);
        if (!$aData) $aData = [];

        //update the structure (array of arrays)
        if (isset($aData[$sTag])) {
            cDebug::write("tag  already reported for this product: $psTag");
            return;
        }

        cDebug::write("creating tag entry: $sTag");
        $aData[$sTag] = [];
        $aData[$sTag][$psUser] = 1;

        //update the data
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $sFolder = self::get_product_tag_folder($psSol, $psInstrument, $psProduct);
        $oDB->put("$sFolder/" . self::PRODUCT_TAG_FILE, $aData);

        //update Indexes
        self::update_sol_tags($psSol, $psInstrument, $psProduct, $sTag);

        cDebug::leave();
    }

    //********************************************************************
    //* Sol
    //********************************************************************
    static function get_sol_tags($psSol) {
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;

        $aData =  $oDB->get("$psSol/" . self::SOL_TAG_FILE);
        return $aData;
    }

    //********************************************************************
    static function update_sol_tags($psSol, $psInstrument, $psProduct, $psTag) {
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $aData = self::get_sol_tags($psSol);
        if (!$aData) $aData = [];
        if (!isset($aData[$psInstrument])) $aData[$psInstrument] = [];
        $aData[$psInstrument][] = ["p" => $psProduct, "t" => $psTag];
        $oDB = self::$objstoreDB;
        $oDB->put("$psSol/" . self::SOL_TAG_FILE, $aData);

        cSpaceTagsIndex::update_indexes($psSol, $psInstrument, $psProduct, 1);
        cSpaceTagNames::update_indexes($psSol, $psInstrument, $psProduct, $psTag);
    }

    //********************************************************************
    static function get_sol_tag_count($psSol) {
        cDebug::enter();
        $aData = self::get_sol_tags($psSol);
        $iCount = 0;
        if ($aData != null)
            foreach ($aData as $sInstr => $aTags)
                foreach ($aTags as $oItem)
                    $iCount++;
        cDebug::leave();
        return $iCount;
    }

    //********************************************************************
    static function clean_product_tags() {
        cDebug::enter(); {
            /** @var cObjStoreDB $oDB **/
            $oDB = self::$objstoreDB;

            //get the top index
            $aTopIndex = cSpaceTagsIndex::get_top_sol_index();
            foreach ($aTopIndex as $sSol => $iSolCount) {
                cCommon::flushprint(".");
                $aSolData  = cSpaceTagsIndex::get_sol_index($sSol);
                foreach ($aSolData as $sInstr => $aProds)
                    foreach ($aProds as $sProd => $iProdCount) {
                        //- - - - - - get the tags for the product
                        $aProdData = self::get_product_tags($sSol, $sInstr, $sProd);
                        $bErrorShown = false;

                        //check if there are any blank entries
                        foreach ($aProdData as $sKey => $sValue) {
                            if (cCommon::is_string_empty($sKey)) {
                                cDebug::write("deleting product data: s:$sSol, i:$sInstr, p:$sProd");
                                $sFolder = self::get_product_tag_folder($sSol, $sInstr, $sProd);
                                $oDB->kill("$sFolder/" . self::PRODUCT_TAG_FILE);
                            }
                        }
                    }
            }
        }
        cDebug::leave();
    }
}
cSpaceTags::init_obj_store_db();
