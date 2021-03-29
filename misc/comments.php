<?php
/**************************************************************************
	Copyright (C) Chicken Katsu 2021

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk
or leave a message on github

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
//
//% OBJSTOREDB - simplistic store objects without a relational database!
//%
//% solves Problem -  thousands files on busy websites that exceed inode quotas.
//%
**************************************************************************/


require_once("$phpinc/ckinc/objstoredb.php");
class cComments{
	const COMMENT_FILENAME = "[comment].txt";
	const STRIP_HTML = false;
	static $oObjStore = null;
	
	
	//********************************************************************
	static function pr_init_objstore(){
		if (!self::$oObjStore){
			self::$oObjStore = new cObjStoreDB();
			self::$oObjStore->realm = "COMMENTS";
		}
	}
	
	//********************************************************************
	static function get( $psSol, $psInstrument, $psProduct){
		$sFolder = "$psSol/$psInstrument/$psProduct";
		$aTags = self::$oObjStore->get_oldstyle( $sFolder, self::COMMENT_FILENAME);
		return $aTags;
	}

	//********************************************************************
	static function set( $psSol, $psInstrument, $psProduct, $psComment, $psUser){
		$sFolder = "$psSol/$psInstrument/$psProduct";
		if (self::STRIP_HTML) $psComment = strip_tags($psComment);
		cDebug::write("comment: $psComment");

		$aData = ["c"=>$psComment, "u"=>$psUser];
		$aData = self::$oObjStore->put_array_oldstyle( $sFolder, self::COMMENT_FILENAME, $aData);
		
		
		// update SOL
		// update SOL/Instrument
		// update recent
		return $aData;
	}
	//
}

cComments::pr_init_objstore();


?>