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


class cTags
{
    const TOP_TAG_FILE = "[top].txt";
    const TOP_SOL_TAG_FILE = "[solstag].txt";
    const SOL_TAG_FILE = "[soltag].txt";
    const INSTR_TAG_FILE = "[instrtag].txt";
    const PROD_TAG_FILE = "[tag].txt";
    const TAG_FOLDER = "[tags]";
    const RECENT_TAG = "TAG";
    private static $objstoreDB = null;


    //********************************************************************
    static function init_obj_store_db()
    {
        if (!self::$objstoreDB)
            self::$objstoreDB = new cObjStoreDB(cSpaceRealms::TAGS);
    }

    //********************************************************************
    static function get_tag_names($psSol, $psInstrument, $psProduct)
    {
        cDebug::enter();

        /** @var cObjStoreDB **/
        $oDB = self::$objstoreDB;
        $sFolder = "$psSol/$psInstrument/$psProduct";
        $aTags = $oDB->get_oldstyle($sFolder, self::PROD_TAG_FILE);
        if (!$aTags) $aTags = [];

        $aKeys = [];
        foreach ($aTags as $sKey => $oValue)
            array_push($aKeys, $sKey);

        cDebug::leave();
        return $aKeys;
    }

    //********************************************************************
    static function set_tag($psSol, $psInstrument, $psProduct, $psTag, $psUser)
    {
        cDebug::enter();

        $sFolder = "$psSol/$psInstrument/$psProduct";
        $psTag = strtolower($psTag);
        $psTag = preg_replace("/[^a-z0-9\-]/", '', $psTag);

        //get the file from the object store
        /** @var cObjStoreDB **/
        $oDB = self::$objstoreDB;
        $aData = $oDB->get_oldstyle($sFolder, self::PROD_TAG_FILE);
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
        /** @var cObjStoreDB **/
        $oDB = self::$objstoreDB;
        $oDB->put_oldstyle($sFolder, self::PROD_TAG_FILE, $aData);

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

    //********************************************************************
    static function get_top_tags()
    {
        cDebug::enter();
        /** @var cObjStoreDB **/
        $oDB = self::$objstoreDB;

        $aTags = $oDB->get_oldstyle("", self::TOP_TAG_FILE);
        //cDebug::vardump($aTags);
        if ($aTags) ksort($aTags);
        cDebug::leave();
        return $aTags;
    }

    //********************************************************************
    static function get_tag_index($psTag)
    {
        cDebug::enter();

        $filename = $psTag . ".txt";
        /** @var cObjStoreDB **/
        $oDB = self::$objstoreDB;

        $aTags = $oDB->get_oldstyle(self::TAG_FOLDER, $filename);
        cDebug::leave();
        if ($aTags) sort($aTags);
        return $aTags;
    }

    //********************************************************************
    static function get_sol_tags($psSol)
    {
        cDebug::enter();
        /** @var cObjStoreDB **/
        $oDB = self::$objstoreDB;

        $aData =  $oDB->get_oldstyle($psSol, self::SOL_TAG_FILE);
        cDebug::leave();
        return $aData;
    }

    //********************************************************************
    static function get_top_sol_index()
    {
        cDebug::enter();
        /** @var cObjStoreDB **/
        $oDB = self::$objstoreDB;

        $aData = $oDB->get_oldstyle("", self::TOP_SOL_TAG_FILE);
        cDebug::leave();
        return $aData;
    }

    //********************************************************************
    static function get_sol_tag_count($psSol)
    {
        cDebug::enter();
        /** @var cObjStoreDB **/
        $oDB = self::$objstoreDB;

        $aData = $oDB->get_oldstyle($psSol, self::SOL_TAG_FILE);
        $iCount = 0;
        if ($aData != null)
            foreach ($aData as $sInstr => $aTags)
                foreach ($aTags as $oItem)
                    $iCount++;
        cDebug::leave();
        return $iCount;
    }

    //######################################################################
    //# UPDATE functions
    //######################################################################
    static function update_top_sol_index($psSol)
    {
        cDebug::enter();

        /** @var cObjStoreDB **/
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
    static function update_sol_index($psSol, $psInstrument, $psProduct, $psTag)
    {
        cDebug::enter();

        /** @var cObjStoreDB **/
        $oDB = self::$objstoreDB;
        $aData = $oDB->get_oldstyle($psSol, self::SOL_TAG_FILE);
        if (!$aData) $aData = [];
        if (!isset($aData[$psInstrument])) $aData[$psInstrument] = [];
        $aData[$psInstrument][] = ["p" => $psProduct, "t" => $psTag];
        $oDB->put_oldstyle($psSol, self::SOL_TAG_FILE, $aData);
        cDebug::leave();
    }

    //********************************************************************
    static function update_instr_index($psSol, $psInstrument, $psProduct, $psTag)
    {
        cDebug::enter();

        /** @var cObjStoreDB **/
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
    static function update_tag_index($psTag, $psValue)
    {
        cDebug::enter();

        $filename = $psTag . ".txt";
        /** @var cObjStoreDB **/
        $oDB = self::$objstoreDB;
        $oDB->add_to_array_oldstyle(self::TAG_FOLDER, $filename, $psValue);
        cDebug::leave();
    }

    //********************************************************************
    static function update_top_index($psTag)
    {
        cDebug::enter();

        cDebug::write("updating index for tag : $psTag");
        /** @var cObjStoreDB **/
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

    //######################################################################
    //# admin functions
    //######################################################################
    static function reindex()
    {
        cDebug::enter();

        $aAllTags = [];

        //get all the tags
        $aTopTags = self::get_top_tags();
        foreach ($aTopTags as $sTag => $iValue) {
            $aTagData = self::get_tag_index($sTag);
            foreach ($aTagData as $sIndex) {
                $aSplit = explode("/", $sIndex);
                $sSol = $aSplit[0];
                $sInstr = $aSplit[1];
                $sProduct = $aSplit[2];

                if (!isset($aAllTags[$sSol])) $aAllTags[$sSol] = [];
                if (!isset($aAllTags[$sSol][$sInstr])) $aAllTags[$sSol][$sInstr] = [];
                if (!isset($aAllTags[$sSol][$sInstr][$sProduct])) $aAllTags[$sSol][$sInstr][$sProduct] = [];
                $aAllTags[$sSol][$sInstr][$sProduct][$sTag] = 1;
            }
        }

        //write out the sol index files
        /** @var cObjStoreDB **/
        $oDB = self::$objstoreDB;

        $aTopSols = [];
        foreach ($aAllTags as  $sSol => $aSolData) {
            $aTopSols[$sSol] = 1;
            $aSolDataOut = [];
            foreach ($aSolData as $sInstr => $aInstrData) {
                $aInstrDataOut = [];
                foreach ($aInstrData as $sProduct => $aProductData) {
                    foreach ($aProductData as $sTag => $iValue) {
                        //update the sol data 
                        if (!isset($aSolDataOut[$sInstr])) $aSolDataOut[$sInstr] = [];
                        $aSolDataOut[$sInstr][] = ["p" => $sProduct, "t" => $sTag];

                        //update the instrument data
                        if (!isset($aInstrDataOut[$sProduct])) $aInstrDataOut[$sProduct] = [];
                        $aInstrDataOut[$sProduct][] = $sTag;
                    }
                    $oDB->put_oldstyle("$sSol/$sInstr/$sProduct", self::PROD_TAG_FILE, $aProductData);
                }
                $oDB->put_oldstyle("$sSol/$sInstr", self::INSTR_TAG_FILE, $aInstrDataOut);
            }
            $oDB->put_oldstyle($sSol, self::SOL_TAG_FILE, $aSolDataOut);
        }
        $oDB->put_oldstyle("", self::TOP_SOL_TAG_FILE, $aTopSols);


        cDebug::leave();
    }

    //********************************************************************
    static function kill_tag($psTag)
    {
        cDebug::enter();

        /** @var cObjStoreDB **/
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
            $oDB->kill_oldstyle($sFolder, self::PROD_TAG_FILE);

        cDebug::leave();
    }
}
cTags::init_obj_store_db();
