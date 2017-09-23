<?php

// Engine EventSystem.

namespace DisEngine;

// Controls server event raising system.
abstract class ServerEngine {
    // Creates and raises new SyncEvent for specific action
    public static function raiseEvent($className, $eventType){
        $event = new SyncEvent($className.'-'.$eventType);
        $event->fire();
    }
}

?>