<?php

/**************************************************************************
Copyright (C) Chicken Katsu 2013 -2024

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
 **************************************************************************/
//#########################################################################
interface iMission {
    static function get_mission_name();
    static function get_mission_id();
    static function get_mission_url();
    static function getSolData($psSol, $psInstrument = null, $pbThumbs = false);
    static function getSolRawData($psSol, $psInstrument = null, $pbThumbs = false);
    static function getSolList();
    static function search_product($psSearch);
    static function getSolInstrumentList($piSol);
    static function getProductDetails($psSol, $psInstrument, $psProduct);
    static function deleteSolData($psSol);
}

interface iMissionImages {
    static function getThumbnails($psSol, $psInstrument);
    static function getImageUrl($psSol, $psInstrument, $psProduct);
}

//#########################################################################
class cMissions {
    static $missions = [];

    static function add_mission(string $oClass) {
        $sName = $oClass::get_mission_name();
        self::$missions[$sName] = $oClass;
    }

    static function get_mission(string $psName): iMission {
        return self::$missions[$psName];
    }
}
