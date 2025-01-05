<?php

use Illuminate\Database\Capsule\Manager as CapsuleManager;

class cMissionManifest {
    static $CapsuleManager = null;

    static function init() {
        //check that database class has been loaded - redundant as composer would throw an error if it wasnt
        $classname = "Illuminate\Database\Capsule\Manager";
        if (!class_exists($classname))
            cDebug::error("check composer - unable to find class $classname");
        cDebug::extra_debug("found class $classname");

        //connect the ORM to a SQL lite database
        if (self::$CapsuleManager == null) {

            $oManager = new CapsuleManager();
            $sDBFile = cAppGlobals::$dbRoot . '/maniorm.db';
            $oManager->addConnection([
                'driver' => 'sqlite',
                'database' => $sDBFile,
            ]);
            $oManager->setAsGlobal();
            $oManager->bootEloquent();
            self::$CapsuleManager = $oManager;
            cDebug::extra_debug("booted eloquent - db is $sDBFile");
        }
    }
}
cMissionManifest::init();
