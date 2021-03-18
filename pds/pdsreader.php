<?php
/**************************************************************************
Copyright (C) Chicken Katsu 2014 -2015

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
**************************************************************************/

require_once("$phpinc/ckinc/debug.php");
require_once("$phpinc/ckinc/http.php");
require_once("$phpinc/ckinc/objstore.php");
require_once("$phpinc/ckinc/gz.php");
require_once("$phpinc/ckinc/common.php");
require_once("$phpinc/ckinc/hash.php");
require_once("$spaceinc/pds/lbl.php");


class cPDS_Reader{
	public static $force_delete = false;
	public $columns_object_name = "INDEX_TABLE";
	public $TAB_INDEX_ENTRY = "^INDEX_TABLE";
	public $TAB_PRODUCT_COLUMN = "PRODUCT_ID";
	public $PDS_URL = "";
	private $COLUMN_NAMES = ["PATH_NAME", "FILE_NAME", "INSTRUMENT_ID", "PLANET_DAY_NUMBER", "PRODUCT_ID", "IMAGE_TIME"];

	const MAX_TAB_LINES = "10000";
	const TAB_FOLDER = "[PDSTAB]";
	const TAB_ID="[PDSTAB]";
	
	//**********************************************************************
	public  function fetch_volume_lbl( $psVolume, $psIndex){
		$sLBLUrl = $this->PDS_URL."/$psVolume/INDEX/$psIndex.LBL";
		$sOutFile = "$psVolume.LBL";
		
		cDebug::write("fetching volume LBL $sLBLUrl");
		$oHttp = new cHttp();
		$sFile = $oHttp->large_url_path($sOutFile);
		if (!file_exists("$sFile.gz")){
			cDebug::write("$sFile.gz doesnt exist" );
			
			$sLBLFile = $oHttp->fetch_large_url($sLBLUrl, $sOutFile, false);
			cDebug::write("fetched to $sLBLFile" );
			
			cDebug::write("compressing $sLBLFile");
			cGzip::compress_file($sLBLFile);
			cDebug::write("output filename is $sLBLFile");
		}
		
		//------------------------------------------------------------------
		//parse the lbl file
		return self::parse_LBL("$sFile.gz");
	}
	
		//**********************************************************************
	public static function fetch_lbl( $psUrl){
		global $root;
		//create a unique hash for the 
		cHash::$CACHE_EXPIRY = cHash::FOREVER;		//cache forever
		//cHash::$show_filenames = true;

		$sHashUrl = cHash::hash($psUrl);
		$sHashLBL = cHash::hash("PDSOBJ-$psUrl");
		
		if (self::$force_delete){
			cDebug::write("deleting cached file for $psUrl");
			cHash::delete_hash($sHashLBL);
			self::$force_delete = false;
		}

		
		if (!cHash::exists($sHashLBL)){
			//--- fetch the raw LBL file
			$sFolder = cHash::make_hash_folder($sHashUrl);
			$sUrlFilename = cHash::getPath($sHashUrl);
			$oHttp = new cHttp();
			$oHttp->fetch_to_file($psUrl, $sUrlFilename, false);
			
			//---parse into a LBL obj
			cDebug::write("Parsing http");
			$oLBL = new cPDS_LBL();
			$oLBL->parseFile($sUrlFilename);
			
			//--- store LBL obj
			cHash::pr__put_obj($sHashLBL, $oLBL);
			
			//--- delete url hash
			unlink($sUrlFilename);
		}else{
			cDebug::write("file exists on disk $sHashLBL");
			$oLBL = cHash::pr__get_obj($sHashLBL);
		}
		//$oLBL->__dump();
		return $oLBL;
	}
	
	//**********************************************************************
	public  function parse_LBL( $psFilename){
		$oLBL = new cPDS_LBL();
		$oLBL->parseFile($psFilename);
		cDebug::write("parse file OK");
		return $oLBL;
	}
	
	//**********************************************************************
	//* TAB functions
	//**********************************************************************
	public function set_product_column($psCol){
		$this->TAB_PRODUCT_COLUMN = $psCol;
		$this->COLUMN_NAMES[] = $psCol;
	}
	
	//**********************************************************************
	public  function fetch_tab($poLbl, $psVolume){
		//--------error checking------------------------------------
		if ($poLbl == null)	cDebug::error("must supply a LBL file");
		if ($psVolume == null)	cDebug::error("must supply a volume identifier");
	
		$sTBLFileName = $poLbl->get($this->TAB_INDEX_ENTRY);
		if ($sTBLFileName == null)	cDebug::error("unable to determine TAB filename - was the LBL Parsed correctly?");
		
		//ok get ito the guts of it
		$sTABUrl = $this->PDS_URL."/$psVolume/INDEX/$sTBLFileName";
		$sOutFile = "$psVolume.TAB";
		$sTABFile = $this->pr__do_fetch_tab($sTABUrl, $sOutFile);
		
		//-------------------------------------------------------------------------------
		//parse the TAB file - cant return them as an array as it kills memory so split em up
		cDebug::write("<b>write columns data from tab file</b>");
		$this->pr__parse_tab($poLbl, $sTABFile, $psVolume);
		
		//------------and create Index files
		cDebug::write("<b>creating index files</b>");
		$this->pr__create_tab_index_files($psVolume);
	}
	
	
	//**********************************************************************
	public  function delete_tab_col_files($psInstr){
		$iCount = self::get_tab_data_count($psInstr);
		if ($iCount == null) return;
		
		for ($i=0; $i <= $iCount; $i++){
			$sFolder = self::TAB_ID.$psInstr.$i;
			$sHash = cHash::hash($sFolder);
			cHash::delete_hash($sHash);
		}
		cObjStore::kill_file(self::TAB_FOLDER, $psInstr);

	}

	//**********************************************************************
	private  function pr__create_tab_index_files($psVolume){
		
		$iCount = self::pr__get_tab_data_count($psVolume);
			
		for ($i=0; $i<=$iCount; $i++){
			$aTabData = self::pr__get_tab_col_file($psVolume, $i);
			if ($aTabData == null) cDebug::error("unable to get tab column file #$i for $psVolume");

			$aData = [];
			cDebug::write("<b>creating index files $psVolume $i</b>");

			//aggregate the index into Sols
			foreach ($aTabData as $aLine){
				$sSol = "".(int) $aLine["PLANET_DAY_NUMBER"];
				$sInstr = $aLine["INSTRUMENT_ID"];
				$sProduct = $aLine[$this->TAB_PRODUCT_COLUMN];
				$sPath = $aLine["PATH_NAME"];
				$sFile = $aLine["FILE_NAME"];
				
				if (!isset($aData[$sSol])) $aData[$sSol] = [];
				if (!isset($aData[$sSol][$sInstr])) $aData[$sSol][$sInstr] = [];
				$aData[$sSol][$sInstr][$sProduct] = ["v"=>$psVolume, "p"=>$sPath, "f"=>$sFile];
			}
			
			//write out the files at the end of reading all the data
			cPds::write_index_data($aData);
		}
	}

	//######################################################################
	//# PRIVATES
	//######################################################################
	private  function pr__do_fetch_tab( $psUrl, $psOutFile){
		//get the file
		cDebug::write("fetching TAB $psUrl");
		$oHttp = new cHttp();
		$sFile = $oHttp->large_url_path($psOutFile);
		if (!file_exists("$sFile.gz")){
			$sFile = $oHttp->fetch_large_url($psUrl, $psOutFile, false);
			cGzip::compress_file($sFile);
		}else
			cDebug::write("file exists on disk $sFile.gz");
		
		return "$sFile.gz";
	}
	
	//**********************************************************************
	private  function pr__write_TAB_columns($psVolume, $piTabIndex, $paData){
		$sFolder = self::TAB_ID.$psVolume.$piTabIndex;
		cDebug::write("writing out tab file $sFolder");
		$sHash = cHash::hash($sFolder);
		cHash::pr__put_obj($sHash, $paData, true);
	}
	
	//**********************************************************************
	// parse the column data from the TAB files into output files
	// cant return  an array as these are HUGE files that eat up all available memory
	// so chunks the data into files
	private  function pr__parse_tab( $poLBL, $psTabFile, $psVolume){
		$iTabIndex=0;
		$iTabLine=0;
		
		// get the columns to be used for indexing
		$aCols = self::pr__get_tab_columns($poLBL);
		
		//open the tab file
		$aOut = [];
		$iCount = 0;
		try{
			$fHandle = gzopen($psTabFile, 'rb');
			while(!gzeof($fHandle)){
				$sLine = gzgets($fHandle);
				if (trim($sLine) == "") continue;
				$aLine = self::pr__extract_tab_line($sLine, $aCols);
				$aOut[] = $aLine;
				
				//chunk the data
				$iTabLine++;
				if ($iTabLine >= self::MAX_TAB_LINES){
					self::pr__write_TAB_columns($psVolume,$iTabIndex, $aOut);
					cDebug::write("processed $iCount lines");
					unset($aOut);gc_collect_cycles();
					$aOut=[];
					$iTabLine=0;
					$iTabIndex++;
				}
				$iCount ++;
			}
			
			self::pr__write_TAB_columns($psVolume,$iTabIndex, $aOut);
			
		}catch (Exception $e){
			cDebug::error ($e->getMessage());
		}
		gzclose($fHandle);
		
		cDebug::write("Processed $iCount lines -  into $iTabIndex files");
		cDebug::write("writing count file");
		cObjStore::put_file(self::TAB_FOLDER, $psVolume, $iTabIndex);

		return $iTabIndex;
	}
	
	//**********************************************************************
	private  function pr__get_tab_columns($poLBL){
		$aResult = [];
		//get the column names of interest
		$oINDEXLBL = $poLBL->get($this->columns_object_name);
		if (!$oINDEXLBL){
			//cDebug::write("column names:");
			$poLBL->__dump();
			cDebug::error("couldnt find column ". $this->$columns_object_name);
			return;
		}
		
		//$oINDEXLBL->dump_array("COLUMN", "NAME");
		$aCols = $oINDEXLBL->get("COLUMN");
		
		foreach ($this->COLUMN_NAMES as $sName){
			foreach ($aCols as $oCol)
				if ($oCol->get("NAME") === $sName){
					$aResult[$sName] = $oCol;
					continue;
				}
		}
		return $aResult;
	}
	
	//**********************************************************************
	private  function pr__extract_tab_line($psLine, $paCols){
		$aOut = [];
		foreach ($paCols as $sName=>$oColLBL){
			$iStart = $oColLBL->get("START_BYTE");
			$iCount = $oColLBL->get("BYTES");
			
			$sExtract = substr($psLine, $iStart-1, $iCount);
			$aOut[$sName] = trim($sExtract);
		}
		return $aOut;
	}
	//**********************************************************************
	private  static function pr__get_tab_data_count($psInstr){
		return cObjStore::get_file(self::TAB_FOLDER, $psInstr);
	}
	//**********************************************************************
	private  static function pr__get_tab_col_file($psInstr, $piIndex){
		$sFolder = self::TAB_ID.$psInstr.$piIndex;
		$sHash = cHash::hash($sFolder);
		return cHash::pr__get_obj($sHash);
	}
	
}
?>