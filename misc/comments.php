<?php

/**************************************************************************
	Copyright (C) Chicken Katsu 2013 - 2024
This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk
or leave a message on github

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
//
//% OBJSTOREDB - simplistic store objects without a relational database!
//%
//% solves Problem -  thousands files on busy websites that exceed inode quotas.
//%
 **************************************************************************/


require_once  "$phpInc/ckinc/objstoredb.php";
require_once  "$spaceInc/misc/realms.php";

class cSpaceComments {
    const COMMENT_FILENAME = "[comment].txt";
    const STRIP_HTML = false;
    private static $objstoreDB = null;


    //********************************************************************
    static function init_obj_store_db() {
        if (!self::$objstoreDB)
            self::$objstoreDB = new cObjStoreDB(cSpaceRealms::COMMENTS);
    }

    //######################################################################
    //# INDEX functions
    //######################################################################
    static function get_top_index() {
        cDebug::enter();
        $aResult = cSpaceIndex::get_top_sol_data(cSpaceIndex::COMMENT_SUFFIX);
        if ($aResult) ksort($aResult, SORT_NUMERIC);
        cDebug::leave();
        return $aResult;
    }

    //********************************************************************
    static function get_sol_index($psSol) {
        $aResult = cSpaceIndex::get_sol_data($psSol, cSpaceIndex::COMMENT_SUFFIX, true);
        return $aResult;
    }

    //********************************************************************
    static function add_to_index($psSol, $psInstrument, $psProduct) {
        cSpaceIndex::update_indexes($psSol, $psInstrument, $psProduct, 1, cSpaceIndex::COMMENT_SUFFIX);
    }

    //######################################################################
    //# getters and setters
    //######################################################################
    static function get($psSol, $psInstrument, $psProduct) {
        $sFolder = "$psSol/$psInstrument/$psProduct";
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $aTags = $oDB->get_oldstyle($sFolder, self::COMMENT_FILENAME);
        return $aTags;
    }

    //********************************************************************
    static function set($psSol, $psInstrument, $psProduct, $psComment, $psUser) {
        $sFolder = "$psSol/$psInstrument/$psProduct";
        if (self::STRIP_HTML) $psComment = strip_tags($psComment);
        cDebug::write("comment: $psComment");

        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $aData = ["c" => $psComment, "u" => $psUser];
        $aData = $oDB->add_to_array_oldstyle($sFolder, self::COMMENT_FILENAME, $aData);

        self::add_to_index($psSol, $psInstrument, $psProduct);

        return $aData;
    }
}

cSpaceComments::init_obj_store_db();
