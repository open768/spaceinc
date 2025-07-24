<?php

class cSpaceConstants {
    const DIRECTION_NEXT = "n";
    const DIRECTION_PREVIOUS = "p";
}

class cSpaceUrlParams {
    const MISSION = "m";
    const SOL = "s";
    const INSTRUMENT = "i";
    const PRODUCT = "p";
    const URL = "u";
    const DATA = "d";

    const PDS_VOLUME = "v";
    const EDR_INDEX = "i";
    const SITE = "site";
}

enum eSpaceSampleTypes {
    case SAMPLE_ALL;
    case SAMPLE_THUMBS;
    case SAMPLE_NONTHUMBS;
}

//#################################################################################
class cSpaceProductData {
    public string $mission;
    public string $sol;
    public string $instr;
    public string $full_instr;
    public ?string $product = null;
    public ?string $image_url = null;
    public string $utc_date;
    public ?string $sample_type = null;
    public int $rowid;
    public ?array $data = null;

    public function get_abbreviated_data() {
        $aOut = []; {
            $aOut[cSpaceUrlParams::MISSION] = $this->mission;
            $aOut[cSpaceUrlParams::SOL] = $this->sol;
            $aOut[cSpaceUrlParams::INSTRUMENT] = $this->instr;
            $aOut[cSpaceUrlParams::PRODUCT] = $this->product;
            $aOut[cSpaceUrlParams::URL] = $this->image_url;
            $aOut[cSpaceUrlParams::DATA] = $this->data;
        }
        return (object)$aOut;
    }
}
