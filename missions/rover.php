<?php

/**************************************************************************
Copyright (C) Chicken Katsu 2013 - 2024
This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED

 **************************************************************************/
require_once  cAppGlobals::$phpInc . "/ckinc/debug.php";
require_once  cAppGlobals::$phpInc . "/ckinc/http.php";
require_once  cAppGlobals::$phpInc . "/ckinc/objstoredb.php";
require_once  cAppGlobals::$spaceInc . "/misc/realms.php";

//#################################################################################
class cRoverConstants {
    const MANIFEST_PATH = "[manifest]";
    const DETAILS_PATH = "[details]";
    const MANIFEST_FILE = "manifest";
    const SOLS_FILE = "sols";
}

//#################################################################################
class cRoverManifestImage {
    public $m = null;   //mission
    public $s = null;   //sol
    public $i = null;   //instrument
    public $p = null;   //product
    public $d = null;    //data

    //************************************************************* */
    function __construct($psMission, $psSol, $psInstrument, $psProduct, $poData = null) {
        $this->m = $psMission;
        $this->s = $psSol;
        $this->i = $psInstrument;
        $this->p = $psProduct;
        $this->d = $poData;
    }
}

//#####################################################################
//#####################################################################
class cRoverSols {
    private $aSols = null;
    private $iExpires = null;

    public function add($piSol, $psInstr = null, $piCount = null, $psUrl = null) {
        if (!$this->aSols) $this->aSols = [];

        $sKey = (string) $piSol;
        if (!isset($this->aSols[$sKey])) $this->aSols[$sKey] = new cRoverSol();
        $oSol = $this->aSols[$sKey];
        $oSol->add($psInstr, $piCount, $psUrl);
    }

    public function get_sol_numbers() {
        if (!$this->aSols) cDebug::error("no sols loaded");
        ksort($this->aSols);
        return array_keys($this->aSols);
    }

    public function get_sol($piSol) {
        if (!isset($this->aSols[(string)$piSol])) cDebug::error("Sol $piSol not found");
        return $this->aSols[(string)$piSol];
    }
}

//#####################################################################
//#####################################################################
abstract class cRoverManifest {
    const EXPIRY_TIME = 3600; //hourly expiry
    protected static $objstoreDB = null;
    public $MISSION = null;
    protected $oSols = null;

    //#####################################################################
    //# constructor
    //#####################################################################
    //********************************************************************
    static function init_obj_store_db() {
        if (!self::$objstoreDB) {
            $oStore = new cObjStoreDB(cSpaceRealms::ROVER_MANIFEST, cSpaceTables::ROVER_MANIFEST);
            $oStore->check_expiry = true;
            $oStore->expire_time = self::EXPIRY_TIME;
            self::$objstoreDB = $oStore;
        }
    }

    function __construct() {
        if (!$this->MISSION) cDebug::error("MISSION not set");
        $sPath = $this->pr__get_manifest_path();
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $this->oSols = $oDB->get($sPath);
        if (!$this->oSols or cDebug::$IGNORE_CACHE) {
            cDebug::write("generating manifest");
            $this->pr__get_manifest();
        }
    }

    //#####################################################################
    //# abstract functions
    //#####################################################################

    //must return an array of cRoverSol
    protected abstract function pr_build_manifest();

    protected abstract function pr_generate_details($psSol, $psInstr);

    //#####################################################################
    //# private class functions
    //#####################################################################
    private function pr__get_manifest_path() {
        return  $this->MISSION . "/" . cRoverConstants::MANIFEST_PATH . "/" . cRoverConstants::MANIFEST_FILE;
    }
    private function pr__get_manifest() {

        $sPath = $this->pr__get_manifest_path();
        $oSols = $this->pr_build_manifest();

        //check return type 
        if (!$oSols instanceof  cRoverSols) cDebug::error("return from pr_build_manifest must be cRoverSols");
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $oDB->put($sPath, $oSols, true);
        $this->oSols = $oSols;
    }

    //#####################################################################
    //# PUBLIC functions
    //#####################################################################
    public function get_details($psSol, $psInstr) {
        /** @var cObjStoreDB $oDB **/
        $oDB = self::$objstoreDB;
        $sPath  = $this->MISSION . "/" . cRoverConstants::DETAILS_PATH . "/$psSol/$psInstr";
        $oDetails =  $oDB->get($sPath);
        if ($oDetails) return $oDetails;

        //------------------------------------------------------
        cDebug::write("generating details");
        $oDetails = $this->pr_generate_details($psSol, $psInstr);
        $oDB->put($sPath, $oDetails, true);
        return $oDetails;
    }

    //*************************************************************************************************
    public function get_sol_numbers() {
        cDebug::enter();
        if (!$this->oSols) cDebug::error("no sols");
        $aSols = $this->oSols->get_sol_numbers();
        cDebug::leave();
        return $aSols;
    }

    //*************************************************************************************************
    public function get_sol($piSol) {
        if (!$this->oSols) cDebug::error("no sols");
        return $this->oSols->get_sol($piSol);
    }
}
cRoverManifest::init_obj_store_db();


//#####################################################################
//#####################################################################
class cRoverSol {
    public $instruments = [];

    public function add($psInstr, $piCount, $psUrl) {
        if (!isset($this->instruments[$psInstr])) $this->instruments[$psInstr] = new cRoverInstrument();
        $oEntry = $this->instruments[$psInstr];
        $oEntry->count = $piCount;
        $oEntry->url = $psUrl;
    }
}

//#####################################################################
//#####################################################################
abstract class cRoverInstruments {
    private $aInstruments = [];
    private $aInstrument_map = [];

    protected abstract function prAddInstruments();

    //********************************************************************
    public  function getInstruments() {
        cDebug::enter();
        if (count($this->aInstruments) == 0)     $this->prAddInstruments();
        cDebug::leave();
        return $this->aInstruments;
    }

    //*****************************************************************************
    protected function pr_add($psName, $psAbbreviation, $psCaption, $psColour) {
        $aInstr = ["name" => $psName, "colour" => $psColour, "abbr" => $psAbbreviation,    "caption" => $psCaption];
        $this->aInstruments[] = $aInstr;
        $this->aInstrument_map[$psName] = $aInstr;
        $this->aInstrument_map[$psAbbreviation] = $aInstr;
    }

    //*****************************************************************************
    public  function getAbbreviation($psName) {
        cDebug::enter();
        $this->getInstruments();
        if (isset($this->aInstrument_map[$psName])) {
            cDebug::leave();
            return $this->aInstrument_map[$psName]["abbr"];
        }

        foreach ($this->aInstruments as $aInstrument)
            if ($aInstrument["caption"] == $psName) {
                cDebug::leave();
                return $aInstrument["abbr"];
            }

        cDebug::error("unknown Instrument: $psName");
    }

    //*****************************************************************************
    public  function getInstrumentName($psAbbr) {
        cDebug::enter();
        $this->getInstruments();
        cDebug::leave();
        return  $this->aInstrument_map[$psAbbr]["name"];
    }

    //*****************************************************************************
    public  function getDetails($psAbbr) {
        cDebug::enter();
        $this->getInstruments();
        cDebug::leave();
        return  $this->aInstrument_map[$psAbbr];
    }
}

class cRoverInstrument {
    public $count = -1;
    public $url = null;
}

//#####################################################################
//#####################################################################
class cRoverImage {
    public $source = null;
    public $thumbnail = null;
    public $image = null;
}
