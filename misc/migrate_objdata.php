<?php
class cMigrateObjdata {
    static $objstoreDB = null;
    const OBJDB_REALM = "objdata";
    const MIGRATED_PHASE = "MIPh";
    const MIGRATED_SOL = "MIS";
    const MIGRATED_PRODUCT = "MIPr";

    //*******************************************************************
    static function init_obj_store_db() {
        cDebug::enter();
        if (self::$objstoreDB == null) {
            self::$objstoreDB = new cObjStoreDB(self::OBJDB_REALM, "migrate");
        }
        cDebug::leave();
    }

    //*******************************************************************
    static function migrate() {
        cDebug::enter();
        cDebug::error("not implemented");
        cDebug::leave();
    }
}
cMigrateObjdata::init_obj_store_db();
