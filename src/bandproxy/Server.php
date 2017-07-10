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
use bandproxy\source\ResourceMaker;
use bandproxy\utils\MainLogger;
use bandproxy\utils\Utils;

class Server
{

    public $isRunning;

    /** @var CommandReader  */
    public $console;

    /** @var  \ThreadedLogger */
    public $logger;

    /** @var String */
    public $dataPath;

    public function __construct(\ThreadedLogger $logger, $dataPath)
    {
        $this->isRunning = true;
        $this->logger = $logger;
        $this->dataPath = realpath($dataPath);
        try {
            $this->console = new CommandReader($logger);

            register_shutdown_function([$this, "crashDump"]);
        } catch (\Throwable $e){
            $this->exceptionHandler($e);
        }
        $resouce = new ResourceMaker();
        $result = json_decode(file_get_contents("http://mcapi.ca/query/{$resouce->getData()->get("hostname")}/mcpe"), true);
        $plugins = "";
        foreach($result["plugins"] as $p){
            $plugins .= $p.",";
        }
        $plugins = substr($plugins, 0, -1);

        $logger->info($result["hostname"] . ":" . $result["port"] . " - " . $result["software"] . " : " . $result["version"]);
        $logger->info(" ");
        $logger->info("Plugins(".count($result["plugins"])."): ".$plugins);
    }

    public function getDataPath(){
        return $this->dataPath;
    }

    public function exceptionHandler(\Throwable $e, $trace = null){
        if($e === null){
            return;
        }

        global $lastError;

        if($trace === null){
            $trace = $e->getTrace();
        }

        $errstr = $e->getMessage();
        $errfile = $e->getFile();
        $errno = $e->getCode();
        $errline = $e->getLine();

        $type = ($errno === E_ERROR or $errno === E_USER_ERROR) ? \LogLevel::ERROR : (($errno === E_USER_WARNING or $errno === E_WARNING) ? \LogLevel::WARNING : \LogLevel::NOTICE);
        if(($pos = strpos($errstr, "\n")) !== false){
            $errstr = substr($errstr, 0, $pos);
        }

        $errfile = cleanPath($errfile);

        if($this->logger instanceof MainLogger){
            $this->logger->logException($e, $trace);
        }

        $lastError = [
            "type" => $type,
            "message" => $errstr,
            "fullFile" => $e->getFile(),
            "file" => $errfile,
            "line" => $errline,
            "trace" => @getTrace(1, $trace)
        ];

        global $lastExceptionError, $lastError;
        $lastExceptionError = $lastError;
        //$this->crashDump();
    }

    /**
     * @return MainLogger|\ThreadedLogger
     */
    public function getLogger(){
        return $this->logger;
    }

    public function crashDump(){
        if($this->isRunning === false){
            return;
        }
        $this->hasStopped = false;

        ini_set("error_reporting", 0);
        ini_set("memory_limit", -1); //Fix error dump not dumped on memory problems
        $this->logger->emergency("criando fragmento de erro");
        try{
            $dump = new CrashDump($this);
        }catch(\Throwable $e){
            $this->logger->critical("erro: {$e->getMessage()}");
            return;
        }

        $this->logger->emergency("pasta: {$dump->getPath()}");

        //$this->checkMemory();
        //$dump .= "Memory Usage Tracking: \r\n" . chunk_split(base64_encode(gzdeflate(implode(";", $this->memoryStats), 9))) . "\r\n";

        $this->shutdown();
        $this->isRunning = false;
        @kill(getmypid());
        exit(1);
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