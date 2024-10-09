<?php

/**************************************************************************
Copyright (C) Chicken Katsu 2013 -2024

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
// based on specifications found at  http://naif.jpl.nasa.gov/pub/naif/toolkit_docs/C/req/daf.html
 **************************************************************************/

require_once  cAppGlobals::$ckPhpInc . "/debug.php";
require_once  cAppGlobals::$ckPhpInc . "/http.php";
require_once  cAppGlobals::$ckPhpInc . "/gz.php";
require_once  cAppGlobals::$spaceInc . "/curiosity/static.php";
require_once  cAppGlobals::$ckPhpInc . "/common.php";
require_once  cAppGlobals::$ckPhpInc . "/hash.php";


class cPDS_DAFReader {
    //**********************************************************************
    public function parseURL($psUrl) {
        //fetch and compress url
        //open a stream from the file
        //read the header of the file
        //read the body of the file
        //close the file
    }
}
