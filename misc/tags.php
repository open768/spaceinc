<?php

/**************************************************************************
Copyright (C) Chicken Katsu 2013 -2024

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
 **************************************************************************/
require_once  "$phpInc/ckinc/objstoredb.php";
require_once  "$spaceInc/misc/realms.php";


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
        $oDB->add_to_array_oldstyle(self::TAG_FOLDER, $filename, $sFolder);
        cDebug::leave();
    }

    //********************************************************************
    static function search_tag_names($psPartial) {
        cDebug::enter();

        if (strlen($psPartial) < 2) cDebug::error("partial match must be at least 2 characeters");
        $aOut = [];
        $oAllNames = self::get_top_tag_names();

        foreach ($oAllNames as $sTag => $iCount)
            if (strstr($sTag, $psPartial))
                $aOut[] = $sTag;

        cDebug::leave();
        return $aOut;
    }
}

//#############################################################################
class cSpaceTagsIndex {
    static function get_top_sol_index() {
        return cSpaceIndex::get_top_sol_data(cSpaceIndex::TAG_SUFFIX);
    }

    static function update_indexes($psSol, $psInstrument, $psProduct, $piValue) {
        cDebug::enter();
        cSpaceIndex::update_indexes($psSol, $psInstrument, $psProduct, $piValue, cSpaceIndex::TAG_SUFFIX);
        cDebug::leave();
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
    static function get_product_tags($psSol, $psInstrument, $psProduct) {
        cDebug::enter();

        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $sFolder = self::get_product_tag_folder($psSol, $psInstrument, $psProduct);
        $aTags = $oDB->get("$sFolder/" . self::PRODUCT_TAG_FILE);
        if (!$aTags) $aTags = [];

        $aKeys = [];
        foreach ($aTags as $sKey => $oValue)
            array_push($aKeys, $sKey);

        cDebug::leave();
        return $aKeys;
    }

    //********************************************************************
    static function set_product_tag($psSol, $psInstrument, $psProduct, $psTag, $psUser) {
        cDebug::enter();

        //tidy the tags - remove anything non alphanumeric
        $psTag = strtolower($psTag);
        $psTag = preg_replace("/[^a-z0-9\-]/", '', $psTag);

        //get the file from the object store
        $aData = self::get_product_tags($psSol, $psInstrument, $psProduct);
        if (!$aData) $aData = [];
        cDebug::write("existing tags: ");
        cDebug::vardump($aData, true);
        cDebug::error("stop");

        //update the structure (array of arrays)
        if (!isset($aData[$psTag])) {
            cDebug::write("creating tag entry: $psTag");
            $aData[$psTag] = [];
        }

        if (!isset($aData[$psTag][$psUser])) {
            cDebug::write("adding user $psUser to tags : $psTag");
            $aData[$psTag][$psUser] = 1;
        } else {
            cDebug::write("user has already reported this tag : $psTag");
            return;
        }

        cDebug::write("existing tags: ");
        cDebug::vardump($aData, true);
        cDebug::error("stop");
        //put the file back
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $sFolder = self::get_product_tag_folder($psSol, $psInstrument, $psProduct);
        $oDB->put("$sFolder/" . self::PRODUCT_TAG_FILE, $aData);

        //update Indexes
        self::update_sol_tags($psSol, $psInstr, $psProduct, $psTag);

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
        cSpaceTagNames::update_indexes($psTag, $psSol, $psInstrument, $psProduct);
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
}
cSpaceTags::init_obj_store_db();
