<?php
require_once  cAppGlobals::$ckPhpInc . "/debug.php";
require_once  cAppGlobals::$spaceInc . "/curiosity/curiosity.php";
require_once  cAppGlobals::$spaceInc . "/misc/pichighlight.php";
require_once  cAppGlobals::$ckPhpInc . "/common.php";
require_once  cAppGlobals::$ckPhpInc . "/facebook.php";

//###########################################################################
//#
//###########################################################################
class cFacebookTags {
    //*******************************************************************
    public static function make_fb_detail_tags() {

        $sSol = cHeader::get(cSpaceUrlParams::SOL);
        $sInstrument = cHeader::get(cSpaceUrlParams::INSTRUMENT);
        $sProduct = cHeader::get(cSpaceUrlParams::PRODUCT);

        cDebug::write("getting product details for $sSol, $sInstrument, $sProduct");
        $oInstrumentData = cCuriosity::getProductDetails($sSol, $sInstrument, $sProduct);
        cDebug::write("<img src='" . $oInstrumentData["d"]["i"] . "'>");
        cDebug::vardump($oInstrumentData);
        $aFBApp = cFacebook_ServerSide::getAppID();
?>
        <html>

        <head>
            <title>Curiosity Browser Detail </title>
            <meta property="og:title" content="Curiosity Browser Detail">
            <meta property="og:image" content="<?= $oInstrumentData["d"]["i"] ?>">
            <meta property="og:type" content="article">
            <meta property="fb:app_id" content="<?= $aFBApp["I"] ?>">
            <meta property="og:site_name" content="Curiosity Browser">
            <meta property="og:description" content="sol:<?= $sSol ?>:i=<?= $sInstrument ?>:p=<?= $sProduct ?>. Data courtesy MSSS/MSL/NASA/JPL-Caltech.">
            <meta property="og:url" content="http://www.mars-browser.co.uk<?= $_SERVER["REQUEST_URI"] ?>">
        </head>

        </html>
    <?php
    }

    //*******************************************************************
    public static function make_fb_sol_high_tags() {
        $sSol = cHeader::get(cSpaceUrlParams::SOL);

        cDebug::write("getting highlight details for $sSol");
        $sFilename  = cSpaceImageHighlight::get_sol_high_mosaic($sSol);
        cDebug::write("<img src='$sFilename'>");
    ?>
        <html>

        <head>
            <title>Curiosity Browser Highlights</title>
            <meta property="og:title" content="Curiosity Browser - Highlights">
            <meta property="og:image" content="<?= $sFilename ?>">
            <meta property="og:description" content="Highlights for sol:<?= $sSol ?>, Image Data courtesy MSSS/MSL/NASA/JPL-Caltech.">
            <meta property="og:url" content="http://www.mars-browser.co.uk<?= $_SERVER["REQUEST_URI"] ?>">
        </head>

        </html>
<?php
    }
}
