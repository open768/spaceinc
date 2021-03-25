<?php
require_once("$phpinc/ckinc/objstore.php");
class cComments{
	const COMMENT_FILENAME = "[comment].txt";
	const STRIP_HTML = false;
	
	//********************************************************************
	static function get( $psSol, $psInstrument, $psProduct){
		$sFolder = "$psSol/$psInstrument/$psProduct";
		$aTags = cObjStore::get_file( $sFolder, self::COMMENT_FILENAME);
		return $aTags;
	}

	//********************************************************************
	static function set( $psSol, $psInstrument, $psProduct, $psComment, $psUser){
		$sFolder = "$psSol/$psInstrument/$psProduct";
		if (self::STRIP_HTML) $psComment = strip_tags($psComment);
		cDebug::write("comment: $psComment");

		$aData = ["c"=>$psComment, "u"=>$psUser];
		$aData = cObjStore::push_to_array( $sFolder, self::COMMENT_FILENAME, $aData);
		
		// update SOL
		// update SOL/Instrument
		// update recent
		return $aData;
	}
	//
}
?>