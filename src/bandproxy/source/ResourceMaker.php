<?php
/**
 * Created by PhpStorm.
 * User: ASUS
 * Date: 09/07/2017
 * Time: 23:50
 */

namespace bandproxy\source;


use bandproxy\utils\Config;

class ResourceMaker
{
    /**
     * @var Config
     */
    public $data;

    public function __construct()
    {
        @mkdir(\Phar::running(true) . "/", 777);
        $this->data = new Config(\bandproxy\DATA . "bandproxy.yml", Config::YAML);
        if(!$this->data->exists("hostname"))
            $this->data->set("hostname", "play.lbsg.net:19132");
        $this->data->save();
    }

    public function getData(){
        return $this->data;
    }

}