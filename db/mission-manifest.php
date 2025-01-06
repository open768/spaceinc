<?php

use Illuminate\Database\Capsule\Manager as CapsuleManager;


class cMissionManifest {
    const DBNAME = "manifest_orm.db";

    static $capsuleManager = null;

    static function init() {
        //check that database class has been loaded - redundant as composer would throw an error if it wasnt
        $classname = "Illuminate\Database\Capsule\Manager";
        if (!class_exists($classname))
            cDebug::error("check composer - unable to find class $classname");
        cDebug::extra_debug("found class $classname");

        //create sqlite database if it does not exist
        $oDB = new cSqlLite(self::DBNAME);
        $sDBPath = $oDB->path;

        //connect the ORM to a SQL lite database
        if (self::$capsuleManager == null) {

            $oManager = new CapsuleManager();
            $oManager->addConnection([
                'driver' => 'sqlite',
                'database' => $sDBPath,
            ]);
            $oManager->setAsGlobal();
            $oManager->bootEloquent();
            self::$capsuleManager = $oManager;
            cDebug::extra_debug("started eloquent - db is $sDBPath");
        }
    }
}
cMissionManifest::init();
