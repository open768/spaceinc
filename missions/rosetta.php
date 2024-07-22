<?php
/**************************************************************************
Copyright (C) Chicken Katsu 2013 - 2024
This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED


**************************************************************************/
require_once("$phpInc/ckinc/debug.php");
require_once("$phpInc/ckinc/http.php");
require_once("$spaceInc/missions/rover.php");

//################################################################################
//################################################################################
	
class cRosettaOrbiterInstruments extends cRoverInstruments{
	protected function prAddInstruments(){
		self::pr_add("ALICE",	"A",	"Ultraviolet Imaging Spectrometer",	"red");
		self::pr_add("CONSERT",	"CN",	"Comet Nucleus Sounding Experiment by Radiowave Transmission",	"green");
		self::pr_add("COSIMA",	"CS",	"Cometary Secondary Ion Mass Analyser",	"steelblue");
		self::pr_add("GIADA",	"G",	"Grain Impact Analyser and Dust Accumulator",	"lime");
		self::pr_add("MIDAS",	"MS",	"Micro-Imaging Dust Analysis System",	"blue");
		self::pr_add("MIRO",	"MO",	"Microwave Instrument for the Rosetta Orbiter",	"white");
		self::pr_add("OSIRIS",	"O",	"Optical, Spectroscopic, and Infrared Remote Imaging System",	"yellow");
		self::pr_add("ROSINA",	"RS",	"Rosetta Orbiter Spectrometer for Ion and Neutral Analysis",	"cyan");
		self::pr_add("RPC",		"RPC",	"Rosetta Plasma Consortium",	"tomato");
		self::pr_add("RSI",		"RSI",	"Radio Science Investigation",	"tomato");
		self::pr_add("VIRTIS",	"V",	"Visible and Infrared Thermal Imaging Spectrometer",	"tomato");
		self::pr_add("RPC",		"RPC",	"Entry, Descent, and Landing",	"tomato");
	}
}
class cRosettaLanderInstruments extends cRoverInstruments{
	protected function prAddInstruments(){
		self::pr_add("APXS",	"A",	"Alpha X-ray Spectrometer",	"red");
		self::pr_add("CIVA",	"CI",	"Panoramic cameras",	"green");
		self::pr_add("CONSERT",	"CN",	"Comet Nucleus Sounding Experiment by Radiowave Transmission",	"steelblue");
		self::pr_add("COSAC",	"CS",	"Cometary Sampling and Composition experiment",	"lime");
		self::pr_add("MUPUS",	"M",	"Multi-Purpose Sensors for Surface and Subsurface Science",	"blue");
		self::pr_add("PTOLEMY",	"P",	"MODULUS PTOLEMY - gas analyser",	"white");
		self::pr_add("ROLIS",	"ROL",	"Rosetta Lander Imaging System",	"yellow");
		self::pr_add("ROMAP",	"ROM",	"Rosetta Lander Magnetometer and Plasma Monitor",	"cyan");
		self::pr_add("SD2",		"SD2",	"Sample and Distribution Device",	"tomato");
		self::pr_add("SESAME",	"SES",	"Surface Electrical, Seismic and Acoustic Monitoring Experiments",	"tomato");
	}
}

//################################################################################
//################################################################################
class cRosetta extends cRoverManifest{
	const INSTRUMENT_URL = "http://www.rssd.esa.int/index.php?project=PSA&page=rosetta";
	protected function pr_generate_manifest(){
		cDebug::error("not implemented");
	}
	
	protected function pr_generate_details($psSol, $psInstr){
		cDebug::error("not implemented");
	}
}

?>