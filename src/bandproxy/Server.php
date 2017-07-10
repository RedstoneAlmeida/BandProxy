<?php
/**
 * Created by PhpStorm.
 * User: ASUS
 * Date: 09/07/2017
 * Time: 19:04
 */

namespace bandproxy;


use bandproxy\command\CommandReader;
use bandproxy\command\CommandSender;
use bandproxy\utils\MainLogger;

class Server
{

    public $isRunning;

    /** @var CommandReader  */
    public $console;

    public function __construct(MainLogger $logger)
    {
        $this->isRunning = true;

        //$this->console = new CommandReader($logger);

        $result = json_decode(file_get_contents("http://mcapi.ca/query/rw.factions.live:33823/mcpe"), true);
        $plugins = "";
        foreach($result["plugins"] as $p){
            $plugins .= $p.",";
        }
        $plugins = substr($plugins, 0, -1);

        $logger->info($result["hostname"] . ":" . $result["port"] . " - " . $result["software"] . " : " . $result["version"]);
        $logger->info(" ");
        $logger->info("Plugins(".count($result["plugins"])."): ".$plugins);
    }

    /**
     * @var \ClassLoader
     */
    private $autoloader;

    public static $instance = null;

    /**
     * @return Server
     */
    public static function getInstance(){
        return self::$instance;
    }

    public function getLoader(){
        return $this->autoloader;
    }

    public function shutdown(){
        $this->isRunning = false;
    }

}