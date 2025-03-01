<?php

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
}
