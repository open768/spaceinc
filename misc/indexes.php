<?php
require_once("$phpInc/ckinc/objstore.php");


class cIndexes{
	const TOP_PREFIX = "t";
	const SOL_PREFIX = "s";
	const INSTR_PREFIX = "i";
	
	public static function get_filename( $psPrefix, $psSuffix){
		return "[{$psPrefix}{$psSuffix}].txt";
	}
	
	//********************************************************************
	static function get_top_sol_data( $psSuffix){
        cDebug::enter();
		$sFile = self::get_filename(self::TOP_PREFIX, $psSuffix);
        $oData = cObjStore::get_file( "", $sFile);
        cDebug::leave();

		return $oData ;
	}
	
	//********************************************************************
	static function get_sol_data( $psSol, $psSuffix){
        cDebug::enter();
		$sFile = self::get_filename(self::SOL_PREFIX, $psSuffix);
		$oData = cObjStore::get_file( $psSol, $sFile);
        cDebug::leave();

		return $oData ;
	}
	
	//********************************************************************
	static function get_instr_data( $psSol, $psInstrument, $psSuffix){
        cDebug::enter();
		$sFile = self::get_filename(self::INSTR_PREFIX, $psSuffix);
		$oData = cObjStore::get_file( "$psSol/$psInstrument", $sFile);
        cDebug::leave();

		return $oData ;
	}
	
	//********************************************************************
	static function get_solcount( $psSol, $psFile){
        cDebug::enter();
		$iCount = 0;
		$aData = self::get_sol_data( $psSol, $psFile);
		if ($aData){
			foreach ( $aData as $sInstr=>$aInstrData){
				foreach ($aInstrData as $sProduct)
					$iCount++;
			}
		}
        cDebug::leave();

		return $iCount;
	}

	//######################################################################
	//# UPDATE functions
	//######################################################################
	static function update_indexes( $psSol, $psInstrument, $psProduct, $poData, $psSuffix){
        cDebug::enter();
		self::update_instr_index( $psSol, $psInstrument, $psProduct, $poData, $psSuffix);
		self::update_sol_index( $psSol, $psInstrument, $psProduct, $psSuffix);
		self::update_top_sol_index( $psSol, $psSuffix);		
        cDebug::leave();
	}
	
	//********************************************************************
	static function update_top_sol_index( $psSol, $psSuffix){
        cDebug::enter();
		$sFile = self::get_filename(self::TOP_PREFIX, $psSuffix);
		$aData = cObjStore::get_file( "", $sFile);
		
		if (!$aData) $aData=[];
		if ( !isset($aData[$psSol])) $aData[$psSol] = 0;
		
		$aData[$psSol] = $aData[$psSol] +1;
		cDebug::write("updating top sol index for sol $psSol");
		cObjStore::put_file( "", $sFile, $aData);
        cDebug::leave();
	}
		
	//********************************************************************
	static function update_sol_index( $psSol, $psInstrument, $psProduct, $psSuffix){
        cDebug::enter();
		$sFile = self::get_filename(self::SOL_PREFIX, $psSuffix);
		$aData = cObjStore::get_file( $psSol, $sFile);
		if (!$aData) $aData=[];
		if (!isset($aData[$psInstrument])) $aData[$psInstrument] = [];
		if (!isset($aData[$psInstrument])) $aData[$psInstrument][$psProduct] = 0;
		$aData[$psInstrument][$psProduct] = $aData[$psInstrument][$psProduct] + 1;
		cObjStore::put_file( $psSol, $sFile, $aData);
        cDebug::leave();
	}
		
	//********************************************************************
	static function update_instr_index( $psSol, $psInstrument, $psProduct, $poData, $psSuffix ){
        cDebug::enter();
		$sFile = self::get_filename(self::INSTR_PREFIX, $psSuffix);
		$sFolder="$psSol/$psInstrument";
		$aData = cObjStore::get_file( $sFolder, $sFile);
		if (!$aData) $aData=[];
		$aData[$psProduct] = $poData;
		cObjStore::put_file( $sFolder, $sFile, $aData);
        cDebug::leave();
	}

	//######################################################################
	//# reindex functions
	//######################################################################
	static function reindex( $poInstrData, $psSuffix, $psProdFile){
        cDebug::enter();
		$aData = [];

		$toppath = cObjStore::$rootFolder."/".cObjStore::$OBJDATA_REALM;
		
		//find the highlight files - tried to do this cleverly, but was more lines of code - so brute force it is
		$aSols = scandir($toppath);
		foreach ($aSols as $sSol)
			if (preg_match("/\d+/", $sSol)){
				$solPath = "$toppath/$sSol";
				$aInstrs = scandir($solPath);
				foreach ($aInstrs as $sInstr)
					if (! preg_match("/[\[\.]/", $sInstr)){
						$instrPath = "$solPath/$sInstr";
						$aProducts = scandir($instrPath);
						foreach ($aProducts as $sProduct)
							if (! preg_match("/[\[\.]/", $sProduct)){
								$prodPath = "$instrPath/$sProduct";
								$aFiles = scandir($prodPath);
								foreach ($aFiles as $sFile)
									if ( $sFile === $psProdFile){
										if (!isset($aData[$sSol])) $aData[$sSol] = [];
										if (!isset($aData[$sSol][$sInstr])) $aData[$sSol][$sInstr] = [];
										$aData[$sSol][$sInstr][$sProduct] = $poInstrData;
									}
							}
					}
			}
			
		self::write_index_files( $aData,$psSuffix);
        cDebug::leave();
	}
	
	//***********************************************************************************************************
	public static function write_index_files($paData, $psSuffix){
        cDebug::enter();
		$aTopSols = [];
		foreach ($paData as  $sSol=>$aSolData)	{
			$aTopSols[$sSol] = 1;
			foreach ($aSolData as $sInstr=>$aInstrData)
				cObjStore::put_file( "$sSol/$sInstr", self::get_filename(self::INSTR_PREFIX, $psSuffix), $aInstrData);				
			cObjStore::put_file( $sSol, self::get_filename(self::SOL_PREFIX, $psSuffix), $aSolData);				
		}
		cObjStore::put_file( "", self::get_filename(self::TOP_PREFIX, $psSuffix), $aTopSols);
        cDebug::leave();
	}
}
?>