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
    static function getAllSolData($psSol);
    static function getSolList();
    static function search_product($psSearch);
    static function getSolInstrumentList($piSol);
    static function getProductDetails($psSol, $psInstrument, $psProduct);
}

interface iMissionImages {
    static function getThumbnails($psSol, $psInstrument);
    static function getLocalThumbnail($psSol, $psInstrument, $psProduct);
}

//#########################################################################
class cMissionNamesItem {
    public $name;
    public $ID;
    public $url;

    function __construct(string $psName, string $psID, string $psUrl) {
        $this->name = $psName;
        $this->ID = $psID;
        $this->url = $psUrl;
    }
}

class cMissionNames {
    static $names = [];

    static function add_mission(string $psName, string $psID, string $psUrl) {
        $oItem = new cMissionNamesItem($psName, $psID, $psUrl);
        self::$names[$psName] = $oItem;
    }

    static function get_ID(string $psName) {
        return self::$names[$psName];
    }
}

//#########################################################################
class cMissionsItem {
    public ?string $name = null;
    public ?iMission $MissionClass = null;
    public ?iMissionImages $MissionImages = null;

    //constructor
    function __construct(string $psMissionName, iMission $poMClass, iMissionImages $poMIClass) {
        $this->name = $psMissionName;
        $this->MissionClass = $poMClass;
        $this->MissionImages = $poMIClass;
    }
}

//class cMissions implements iMission, iMissionImages {
class cMissions {
    static $Missions = [];

    static function register(string $psMissionName, iMission $poMClass, iMissionImages $poMIClass) {
        $oItem = new cMissionsItem($psMissionName,  $poMClass,  $poMIClass);
        self::$Missions[$psMissionName] = $oItem;
    }

    static function get(string $psMissionName) {
        return self::$Missions[$psMissionName];
    }
}
