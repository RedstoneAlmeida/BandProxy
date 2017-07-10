<?php
/**
 * Created by PhpStorm.
 * User: ASUS
 * Date: 09/07/2017
 * Time: 18:10
 */


namespace {
    function safe_var_dump(){
        static $cnt = 0;
        foreach(func_get_args() as $var){
            switch(true){
                case is_array($var):
                    echo str_repeat("  ", $cnt) . "array(" . count($var) . ") {" . PHP_EOL;
                    foreach($var as $key => $value){
                        echo str_repeat("  ", $cnt + 1) . "[" . (is_int($key) ? $key : '"' . $key . '"') . "]=>" . PHP_EOL;
                        ++$cnt;
                        safe_var_dump($value);
                        --$cnt;
                    }
                    echo str_repeat("  ", $cnt) . "}" . PHP_EOL;
                    break;
                case is_int($var):
                    echo str_repeat("  ", $cnt) . "int(" . $var . ")" . PHP_EOL;
                    break;
                case is_float($var):
                    echo str_repeat("  ", $cnt) . "float(" . $var . ")" . PHP_EOL;
                    break;
                case is_bool($var):
                    echo str_repeat("  ", $cnt) . "bool(" . ($var === true ? "true" : "false") . ")" . PHP_EOL;
                    break;
                case is_string($var):
                    echo str_repeat("  ", $cnt) . "string(" . strlen($var) . ") \"$var\"" . PHP_EOL;
                    break;
                case is_resource($var):
                    echo str_repeat("  ", $cnt) . "resource() of type (" . get_resource_type($var) . ")" . PHP_EOL;
                    break;
                case is_object($var):
                    echo str_repeat("  ", $cnt) . "object(" . get_class($var) . ")" . PHP_EOL;
                    break;
                case is_null($var):
                    echo str_repeat("  ", $cnt) . "NULL" . PHP_EOL;
                    break;
            }
        }
    }
    function dummy(){
    }
}

namespace bandproxy {

    use bandproxy\utils\MainLogger;
    use raklib\RakLib;
    use bandproxy\utils\Terminal;
    use bandproxy\utils\Utils;

    const VERSION = "0.1-beta";

    if(!extension_loaded("phar")){
        echo "[CRITICAL] Unable to find the Phar extension." . PHP_EOL;
        echo "[CRITICAL] Please use the installer provided on the homepage." . PHP_EOL;
        exit(1);
    }
    if(\Phar::running(true) !== ""){
        @define('bandproxy\PATH', \Phar::running(true) . "/");
    }else{
        @define('bandproxy\PATH', \getcwd() . DIRECTORY_SEPARATOR);
    }
    if(version_compare("7.0", PHP_VERSION) > 0){
        echo "[CRITICAL] You must use PHP >= 7.0" . PHP_EOL;
        echo "[CRITICAL] Please use the installer provided on the homepage." . PHP_EOL;
        exit(1);
    }
    if(!extension_loaded("pthreads")){
        echo "[CRITICAL] Unable to find the pthreads extension." . PHP_EOL;
        echo "[CRITICAL] Please use the installer provided on the homepage." . PHP_EOL;
        exit(1);
    }
    if(!class_exists("ClassLoader", false)){
        if(!is_file(\bandproxy\PATH . "src/spl/ClassLoader.php")){
            echo "[CRITICAL] Unable to find the PocketMine-SPL library." . PHP_EOL;
            echo "[CRITICAL] Please use provided builds or clone the repository recursively." . PHP_EOL;
            echo "[NEKO] if you're using a non-compiled src, clone the SPL library from PocketMine GitHub." . PHP_EOL;
            exit(1);
        }
        require_once(\bandproxy\PATH . "src/spl/ClassLoader.php");
        require_once(\bandproxy\PATH . "src/spl/BaseClassLoader.php");
    }

    $autoloader = new \BaseClassLoader();
    $autoloader->addPath(\bandproxy\PATH . "src");
    $autoloader->addPath(\bandproxy\PATH . "src" . DIRECTORY_SEPARATOR . "spl");
    $autoloader->register(true);

    try{
        if(!class_exists(RakLib::class)){
            throw new \Exception;
        }
    }catch(\Exception $e){
        echo "[CRITICAL] Unable to find the RakLib library." . PHP_EOL;
        echo "[NEKO] if you're using a non-compiled src, clone the RakLib library from PocketMine GitHub." . PHP_EOL;
        exit(1);
    }
    set_time_limit(0); //Who set it to 30 seconds?!?!
    error_reporting(-1);
    set_error_handler(function($severity, $message, $file, $line){
        if((error_reporting() & $severity)){
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }
    });
    ini_set("allow_url_fopen", 1);
    ini_set("display_errors", 1);
    ini_set("display_startup_errors", 1);
    ini_set("default_charset", "utf-8");
    ini_set("memory_limit", -1);
    define('bandproxy\START_TIME', microtime(true));
    $opts = getopt("", ["data:", "plugins:", "no-wizard", "enable-profiler"]);
    define('bandproxy\DATA', isset($opts["data"]) ? $opts["data"] . DIRECTORY_SEPARATOR : \getcwd() . DIRECTORY_SEPARATOR);
    define('bandproxy\PLUGIN_PATH', isset($opts["plugins"]) ? $opts["plugins"] . DIRECTORY_SEPARATOR : \getcwd() . DIRECTORY_SEPARATOR . "plugins" . DIRECTORY_SEPARATOR);
    Terminal::init();
    define('bandproxy\ANSI', Terminal::hasFormattingCodes());
    if(!file_exists(\bandproxy\DATA)){
        mkdir(\bandproxy\DATA, 0777, true);
    }
    //Logger has a dependency on timezone, so we'll set it to UTC until we can get the actual timezone.
    date_default_timezone_set("UTC");

    $logger = new MainLogger(\bandproxy\DATA . "server.log", \bandproxy\ANSI);

    echo Terminal::$FORMAT_RESET . PHP_EOL;
    new Server($logger, \Phar::running(true) . "/");

    ThreadManager::init();


    function cleanPath($path){
        return rtrim(str_replace(["\\", ".php", "phar://", rtrim(str_replace(["\\", "phar://"], ["/", ""], \bandproxy\PATH), "/"), rtrim(str_replace(["\\", "phar://"], ["/", ""], \bandproxy\PLUGIN_PATH), "/")], ["/", "", "", "", ""], $path), "/");
    }

    function getTrace($start = 0, $trace = null){
        if($trace === null){
            if(function_exists("xdebug_get_function_stack")){
                $trace = array_reverse(xdebug_get_function_stack());
            }else{
                $e = new \Exception();
                $trace = $e->getTrace();
            }
        }
        $messages = [];
        $j = 0;
        for($i = (int) $start; isset($trace[$i]); ++$i, ++$j){
            $params = "";
            if(isset($trace[$i]["args"]) or isset($trace[$i]["params"])){
                if(isset($trace[$i]["args"])){
                    $args = $trace[$i]["args"];
                }else{
                    $args = $trace[$i]["params"];
                }
                foreach($args as $name => $value){
                    $params .= (is_object($value) ? get_class($value) . " object" : gettype($value) . " " . (is_array($value) ? "Array()" : Utils::printable(@strval($value)))) . ", ";
                }
            }
            $messages[] = "#$j " . (isset($trace[$i]["file"]) ? cleanPath($trace[$i]["file"]) : "") . "(" . (isset($trace[$i]["line"]) ? $trace[$i]["line"] : "") . "): " . (isset($trace[$i]["class"]) ? $trace[$i]["class"] . (($trace[$i]["type"] === "dynamic" or $trace[$i]["type"] === "->") ? "->" : "::") : "") . $trace[$i]["function"] . "(" . Utils::printable(substr($params, 0, -2)) . ")";
        }
        return $messages;
    }

    function kill($pid){
        switch(Utils::getOS()){
            case "win":
                exec("taskkill.exe /F /PID " . ((int) $pid) . " > NUL");
                break;
            case "mac":
            case "linux":
            default:
                if(function_exists("posix_kill")){
                    posix_kill($pid, SIGKILL);
                }else{
                    exec("kill -9 " . ((int)$pid) . " > /dev/null 2>&1");
                }
        }
    }

}