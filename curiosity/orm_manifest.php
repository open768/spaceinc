<?php
require_once "manifest.php";

/**************************************************************************
Copyright (C) Chicken Katsu 2013 -2024

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk
// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
 **************************************************************************/

class cCuriosityORMManifest {
    static function updateIndex() {
        cDebug::enter();
        //reset index status for DEBUGGING PRUPOSES
        cCuriosityManifestIndexStatus::clear_status();
        cDebug::leave();
    }
}
