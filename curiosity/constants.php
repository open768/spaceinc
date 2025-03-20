<?php

//###########################################################################################
//#
//###########################################################################################
class cCuriosityConstants {
    const PDS_VOLUMES = "http://pds-imaging.jpl.nasa.gov/volumes/msl.html";
    const ALL_INSTRUMENTS = "All";
    const MISSION_NAME = "curiosity";
    const MISSION_ID = "msl";
    const MISSION_URL = "https://science.nasa.gov/mission/msl-curiosity/";

    static $Instruments = [
        ["name" => "CHEMCAM_RMI",    "colour" => "red",    "abbr" => "CC",    "caption" => "Chemistry "],
        ["name" => "MAST_LEFT",    "colour" => "white",    "abbr" => "ML",    "caption" => "MastCam Left"],
        ["name" => "MAST_RIGHT",    "colour" => "yellow",    "abbr" => "MR",    "caption" => "MastCam Right"],
        ["name" => "MAHLI",        "colour" => "cyan",    "abbr" => "HL",    "caption" => "Mars Hand Lens Imager"],
        ["name" => "MARDI",        "colour" => "magenta", "abbr" => "DI",    "caption" => "Mars Descent Imager"],
        ["name" => "NAV_LEFT_A",    "colour" => "tomato",    "abbr" => "NLa",    "caption" => "Left Navigation (A)"],
        ["name" => "NAV_RIGHT_A",    "colour" => "gray",    "abbr" => "NRa",    "caption" => "Right Navigation (A)"],
        ["name" => "NAV_LEFT_B",    "colour" => "orange",    "abbr" => "NLb",    "caption" => "Left Navigation (B)"],
        ["name" => "NAV_RIGHT_B",    "colour" => "black",    "abbr" => "NRb",    "caption" => "Right Navigation (B)"]
    ];

    static function get_instr_abbr($psFullInstrument) {
        $lower_instrument = strtolower($psFullInstrument);
    }
}

class cOutputColumns {
    const MISSION = "m";
    const SOL = "s";
    const INSTRUMENT = "i";
    const FULL_INSTRUMENT = "fi";
    const PRODUCT = "p";
    const DATA = "d";
    const URL = "d";
    const DATE = "dt";
    const SAMPLETYPE = "st";
}

//###########################################################################################
//#
//###########################################################################################
class cMSLImageURLUtil {
    static $url_replacements = [
        "http://" => "{1}",
        "https://" => "{2}",
        "mars.jpl.nasa.gov/msl-raw-images/" => "{3}",
        "mars.nasa.gov/msl-raw-images/" => "{4}",
        "proj/msl/redops/ods/surface/sol/" => "{5}",
        "ods/surface/sol/" => "{6}",
        "{1}{3}msss/" => "{7}",
        "opgs/edr" => "{8}",
        "soas/rdr/" => "{9}"
    ];
    static $url_replacement_flip = null;

    //************************************************************************************************
    static function reduce_image_url($psUrl, $psProduct) {
        $sOut = str_replace($psProduct, "{P}", $psUrl);
        foreach (self::$url_replacements as $sSearch => $sReplace) {
            $sOut = str_replace($sSearch, $sReplace, $sOut);
        }
        return $sOut;
    }

    //************************************************************************************************
    static function expand_image_url($psUrl, $psProduct) {

        $sOut = str_replace("{P}", $psProduct, $psUrl);

        while (true) {
            $iStartPos = strpos($sOut, "{");
            if ($iStartPos === false) break;

            $iEndPos = strpos($sOut, "}", $iStartPos);
            if ($iEndPos === false) break;

            $sFragment = substr($sOut, $iStartPos, $iEndPos - $iStartPos + 1);
            if (!isset(self::$url_replacement_flip[$sFragment]))
                break;

            $sReplacement = self::$url_replacement_flip[$sFragment];
            $sOut = str_replace($sFragment, $sReplacement, $sOut);
        }

        return $sOut;
    }
    static function flip_url_replacements() {
        self::$url_replacement_flip = array_flip(self::$url_replacements);
    }
}
cMSLImageURLUtil::flip_url_replacements();
