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


class cSpaceTags {
    const TOP_TAG_FILE = "[top].txt";
    const TOP_SOL_TAG_FILE = "[solstag].txt";
    const SOL_TAG_FILE = "[soltag].txt";
    const INSTR_TAG_FILE = "[instrtag].txt";
    const PRODUCT_TAG_FILE = "[tag].txt";
    const TAG_FOLDER = "[tags]";
    const RECENT_TAG = "TAG";
    private static $objstoreDB = null;


    //********************************************************************
    //* objdb stuff
    //********************************************************************
    static function init_obj_store_db() {
        if (!self::$objstoreDB)
            self::$objstoreDB = new cObjStoreDB(cSpaceRealms::TAGS);
    }

    private static function pr__get_product_tag_folder_name($psSol, $psInstrument, $psProduct) {
        return "$psSol/$psInstrument/$psProduct";
    }

    //********************************************************************
    //* TAG Names
    //********************************************************************
    static function get_product_tags($psSol, $psInstrument, $psProduct) {
        cDebug::enter();

        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $sFolder = self::pr__get_product_tag_folder_name($psSol, $psInstrument, $psProduct);
        $aTags = $oDB->get_oldstyle($sFolder, self::PRODUCT_TAG_FILE);
        if (!$aTags) $aTags = [];

        $aKeys = [];
        foreach ($aTags as $sKey => $oValue)
            array_push($aKeys, $sKey);

        cDebug::leave();
        return $aKeys;
    }

    //********************************************************************
    static function get_top_tag_names() {
        cDebug::enter();
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;

        $aTags = $oDB->get_oldstyle("", self::TOP_TAG_FILE);
        //cDebug::vardump($aTags);
        if ($aTags) ksort($aTags);
        cDebug::leave();
        return $aTags;
    }

    //********************************************************************
    static function kill_tag_name($psTag) {
        cDebug::enter();

        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;

        //remove entry from top tag file 
        $aData = $oDB->get_oldstyle("", self::TOP_TAG_FILE);
        if (isset($aData[$psTag])) {
            unset($aData[$psTag]);
            $oDB->put_oldstyle("", self::TOP_TAG_FILE, $aData);
        } else {
            cDebug::write("tag not found");
            return;
        }

        //remove tag index file 
        $filename = $psTag . ".txt";
        $aTags = $oDB->get_oldstyle(self::TAG_FOLDER, $filename);
        if ($aTags != null)
            $oDB->kill_oldstyle(self::TAG_FOLDER, $filename);
        else {
            cDebug::write("tagindex not found");
            return;
        }

        //remove individual tags
        foreach ($aTags as $sFolder)
            $oDB->kill_oldstyle($sFolder, self::PRODUCT_TAG_FILE);

        cDebug::leave();
    }

    //********************************************************************
    //* TAG counts
    //********************************************************************
    static function get_sol_tags($psSol) {
        cDebug::enter();
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;

        $aData =  $oDB->get_oldstyle($psSol, self::SOL_TAG_FILE);
        cDebug::leave();
        return $aData;
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
    static function set_tag($psSol, $psInstrument, $psProduct, $psTag, $psUser) {
        cDebug::enter();

        //tidy the tags - remove anything non alphanumeric
        $psTag = strtolower($psTag);
        $psTag = preg_replace("/[^a-z0-9\-]/", '', $psTag);

        //get the file from the object store
        $aData = self::get_product_tags($psSol, $psInstrument, $psProduct);
        if (!$aData) $aData = [];

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

        //put the file back
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $sFolder = self::pr__get_product_tag_folder_name($psSol, $psInstrument, $psProduct);
        $oDB->put_oldstyle($sFolder, self::PRODUCT_TAG_FILE, $aData);

        //now update the top_index
        self::update_top_index($psTag);

        //mark this sol as tagged
        self::update_top_sol_index($psSol);
        self::update_sol_index($psSol, $psInstrument, $psProduct, $psTag);
        self::update_instr_index($psSol, $psInstrument, $psProduct, $psTag);


        //and update the index for the image
        self::update_tag_index($psTag, $sFolder);
        cDebug::leave();
    }

    //######################################################################
    //# INDEX functions
    //######################################################################
    static function get_tag_name_index($psTag) {
        cDebug::enter();

        $filename = $psTag . ".txt";
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;

        $aTags = $oDB->get_oldstyle(self::TAG_FOLDER, $filename);
        cDebug::leave();
        if ($aTags) sort($aTags);
        return $aTags;
    }

    static function get_top_sol_index() {
        cDebug::enter();
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;

        $aData = $oDB->get_oldstyle("", self::TOP_SOL_TAG_FILE);
        if ($aData !== null) ksort($aData);
        cDebug::leave();
        return $aData;
    }

    static function update_top_sol_index($psSol) {
        cDebug::enter();

        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $aData = $oDB->get_oldstyle("", self::TOP_SOL_TAG_FILE);
        if (!$aData) $aData = [];
        if (!isset($aData[$psSol])) {
            $aData[$psSol] = 1;
            cDebug::write("updating top sol index for sol $psSol");
            $oDB->put_oldstyle("", self::TOP_SOL_TAG_FILE, $aData);
        }
        cDebug::leave();
    }

    //********************************************************************
    static function update_sol_index($psSol, $psInstrument, $psProduct, $psTag) {
        cDebug::enter();

        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $aData = $oDB->get_oldstyle($psSol, self::SOL_TAG_FILE);
        if (!$aData) $aData = [];
        if (!isset($aData[$psInstrument])) $aData[$psInstrument] = [];
        $aData[$psInstrument][] = ["p" => $psProduct, "t" => $psTag];
        $oDB->put_oldstyle($psSol, self::SOL_TAG_FILE, $aData);
        cDebug::leave();
    }

    //********************************************************************
    static function update_instr_index($psSol, $psInstrument, $psProduct, $psTag) {
        cDebug::enter();

        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $sFolder = "$psSol/$psInstrument";
        $aData = $oDB->get_oldstyle($sFolder, self::INSTR_TAG_FILE);
        if (!$aData) $aData = [];
        if (!isset($aData[$psProduct])) $aData[$psProduct] = [];
        $aData[$psProduct][] = $psTag;
        $oDB->put_oldstyle($sFolder, self::INSTR_TAG_FILE, $aData);
        cDebug::leave();
    }

    //********************************************************************
    static function update_tag_index($psTag, $psValue) {
        cDebug::enter();

        $filename = $psTag . ".txt";
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $oDB->add_to_array_oldstyle(self::TAG_FOLDER, $filename, $psValue);
        cDebug::leave();
    }

    //********************************************************************
    static function update_top_index($psTag) {
        cDebug::enter();

        cDebug::write("updating index for tag : $psTag");
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;

        // get the existing tags
        $aData = $oDB->get_oldstyle("", self::TOP_TAG_FILE);
        if (!$aData) $aData = [];

        //update the count
        $count = 0;
        if (isset($aData[$psTag])) $count = $aData[$psTag];
        $count++;
        $aData[$psTag] = $count;
        //cDebug::vardump($aData);

        //write out the data
        $oDB->put_oldstyle("", self::TOP_TAG_FILE, $aData);
        cDebug::leave();
    }
}
cSpaceTags::init_obj_store_db();
