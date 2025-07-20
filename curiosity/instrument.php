<?php

/**************************************************************************
Copyright (C) Chicken Katsu 2013 -2024

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
 **************************************************************************/

require_once  cAppGlobals::$ckPhpInc . "/cached_http.php";
require_once  cAppGlobals::$spaceInc . "/curiosity/manifest/orm.php";
require_once  cAppGlobals::$spaceInc . "/curiosity/constants.php";
require_once  cAppGlobals::$spaceInc . "/db/mission-manifest.php";

//##########################################################################
class cCuriosityInstrumentData {
    public $du, $dm, $i, $p, $data;
}

//##########################################################################
class cCuriosityInstrument {
    public $instrument;
    public $data;
    public $product;
    public static $Instruments, $instrument_map;

    //*************************************************************************
    function __construct($psInstrument) {
        $this->instrument = $psInstrument;
        $this->data = array();
    }

    //*************************************************************************
    public function add($poCuriosityData, $pbThumbs = false) {
        //dont add thumbnail products if not wanted

        $bProceed = false;
        if ($poCuriosityData->sampleType === cCuriosityProduct::THUMB_SAMPLE_TYPE)
            $bProceed = $pbThumbs;
        else
            $bProceed = !$pbThumbs;

        if ($bProceed) {
            //cDebug::vardump($poCuriosityData);
            $aData = [
                "du" => $poCuriosityData->utc,
                "dm" => $poCuriosityData->lmst,
                "i" => $poCuriosityData->urlList,
                "p" => $poCuriosityData->itemName,
                "data" => $poCuriosityData
            ];
            if (isset($poCuriosityData->pdsLabelUrl))
                $aData["l"] = $poCuriosityData->pdsLabelUrl;
            else
                $aData["l"] = "UNK";


            array_push($this->data, $aData);
        }
    }

    //*****************************************************************************
    public static function getInstrumentList() {
        cTracing::enter();
        if (!self::$Instruments) {
            // build instrument list
            self::$Instruments = cCuriosityConstants::$Instruments;

            // build associative array
            self::$instrument_map = [];
            foreach (self::$Instruments as $oInstr) {
                $sName  =  strtolower($oInstr["name"]);
                self::$instrument_map[$sName] = $oInstr;

                $sAbbr = strtolower($oInstr["abbr"]);
                self::$instrument_map[$sAbbr] = $oInstr;
            }
        }
        cTracing::leave();
        return self::$Instruments;
    }

    //*****************************************************************************
    public static function getInstrumentAbbr($psInstrument) {
        $sLower = strtolower($psInstrument);
        $sAbbr = null;
        foreach (cCuriosityConstants::$Instruments as $oItem) {
            $sItemName = strtolower($oItem["name"]);
            if ($sItemName === $sLower) {
                $sAbbr = $oItem["abbr"];
                break;
            }
        }
        return $sAbbr;
    }

    //*****************************************************************************
    public static function getInstrumentName($psInstrument) {
        self::getInstrumentList();
        $sLower = strtolower($psInstrument);
        if (array_key_exists($sLower, self::$instrument_map))
            return  self::$instrument_map[$sLower]["name"];
        else
            cDebug::error("unknown instrument: $psInstrument");
    }

    //*****************************************************************************
    /** returns a list of instrument objs where the name contains either the instrument name or abbreviation */
    public static function get_matching(array $paNames) {
        $aOut = [];
        foreach ($paNames as $sName) {
            $sLower = strtolower($sName);
            if (array_key_exists($sLower, self::$instrument_map))
                $aOut[] = self::$instrument_map[$sLower];
        }
        return $aOut;
    }
}
cCuriosityInstrument::getInstrumentList();
