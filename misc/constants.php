<?php

class cSpaceConstants {
    const DIRECTION_NEXT = "n";
    const DIRECTION_PREVIOUS = "p";
}

class cSpaceUrlParams {
    const SOL = "s";
    const INSTRUMENT = "i";
    const PRODUCT = "p";
    const MISSION = "m";
    const URL = "u";

    const HIGHLIGHT_TOP = "t";
    const HIGHLIGHT_LEFT = "l";
    const TAG = "t";
    const PDS_VOLUME = "v";
    const EDR_INDEX = "i";
    const VALUE = "v";
}

//#################################################################################
class cSpaceProductData {
    public string $mission;
    public string $sol;
    public string $instr;
    public string $product;
    public string $image_url;
    public int $utc_date;
    public string $sample_type;
    public int $rowid;
}
