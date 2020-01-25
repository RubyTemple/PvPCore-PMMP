<?php
/**
 * Created by PhpStorm.
 * User: jkorn2324
 * Date: 2019-04-08
 * Time: 09:14
 */

declare(strict_types = 1);

namespace jkorn\pvpcore\commands;

use jkorn\pvpcore\commands\parameters\BaseParameter;
use jkorn\pvpcore\commands\parameters\Parameter;
use jkorn\pvpcore\commands\parameters\SimpleParameter;
use jkorn\pvpcore\PvPCore;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\CommandException;

class BaseCommand extends Command
{

    protected $parameters;

    /**
     * BaseCommand constructor.
     * @param $name
     * @param string $description
     * @param null $usageMessage
     */
    public function __construct($name, $description = "", $usageMessage = null)
    {
        parent::__construct($name, $description, $usageMessage);
        parent::setPermission("pvpcore.permission.$name");
        $this->parameters = array();
    }

    /**
     * @param array $params
     */
    public function setParameters(array $params) : void {
        $this->parameters = $params;
    }

    /**
     * @return bool
     */
    private function areParametersCorrect() : bool {
        $result = true;
        if(is_array($this->parameters)){
            $size = count($this->parameters);
            for($v = 0; $v < $size; $v++){
                $group = $this->parameters[$v];
                if(!is_array($group)){
                    $result = false;
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    private function getParamGroupFrom(string $name){
        $paramGroup = null;
        $keys = array_keys($this->parameters);
        foreach($keys as $key){
            if(is_int($key) and is_array($this->parameters[$key])){
                $arr = $this->parameters[$key];
                foreach($arr as $parameter){
                    if($parameter instanceof Parameter){
                        $theName = $parameter->getName();
                        if($theName === $name){
                            $paramGroup = $arr;
                            break;
                        }
                    }
                }
            } else if(is_string($key)){
                $paramGroup = $this->parameters[$key];
            }
        }
        return $paramGroup;
    }

    /**
     * @param string $name
     * @return bool
     */
    private function hasParamGroup(string $name) : bool {
        return $this->getParamGroupFrom($name) != null;
    }

    /**
     * @param CommandSender $sender
     * @param string $paramGroup
     * @return bool
     */
    protected function checkPermissions(CommandSender $sender, string $paramGroup) : bool {
        $result = false;
        if($this->hasParamGroup($paramGroup)) {
            $group = $this->getParamGroupFrom($paramGroup);
            $groupLen = count($group);
            if($groupLen > 0){
                $baseParameter = $group[0];
                if($baseParameter instanceof BaseParameter){
                    if($baseParameter->hasPermission()){
                        $perm = $baseParameter->getPermission();
                        if($sender->hasPermission($perm)){
                            $result = true;
                        }
                    } else {
                        $result = true;
                    }
                }
            } else {
                if($sender->hasPermission(parent::getPermission())){
                    $result = true;
                }
            }
        }
        return $result;
    }

    /**
     * @param array $args
     * @param array $paramGroup
     * @return bool
     */
    protected function hasProperParamTypes(array $args, array $paramGroup) : bool {

        $count = 0;
        $result = true;
        foreach($paramGroup as $parameter){
            $paramArg = $args[$count];
            if($parameter instanceof BaseParameter){
                if(is_string(($paramArg))){
                    if($paramArg !== $parameter->getName()){
                        $result = false;
                        break;
                    }
                } else {
                    $result = false;
                    break;
                }
            } else if ($parameter instanceof SimpleParameter) {
                if(is_string($paramArg)) {
                    if (!$this->hasProperParamType($paramArg, $parameter)) {
                        $result = false;
                        break;
                    }
                } else {
                    $result = false;
                    break;
                }
            }
            $count++;
        }
        return $result;
    }

    /**
     * @param string $s
     * @param SimpleParameter $param
     * @return bool
     */
    public function hasProperParamType(string $s, SimpleParameter $param) : bool {

        $result = false;

        switch($param->getParameterType()){
            case Parameter::PARAMTYPE_INTEGER:
                $result = PvPCore::canParse($s, true);
                break;
            case Parameter::PARAMTYPE_FLOAT:
                $result = PvPCore::canParse($s, false);
                break;
            case Parameter::PARAMTYPE_BOOLEAN:
                $result = $this->isBoolean($s);
                break;
            case Parameter::PARAMTYPE_TARGET:
                $result = true;
                break;
            case Parameter::PARAMTYPE_STRING:
                $result = true;
                break;
            case Parameter::PARAMTYPE_ANY:
                $result = true;
                break;
            default:
        }


        if($result and $param->hasExactValues()) {
            $result = $param->isExactValue($s);
        }


        return $result;
    }

    /**
     * @param string $boolean
     * @return bool
     */
    protected function isBoolean(string $boolean) : bool {
        $isBool = $this->getBoolean($boolean);
        return $isBool !== null;
    }

    /**
     * @param SimpleParameter $param
     * @return string
     */
    protected function getParameterType(SimpleParameter $param) : string {
        $string = $param->getName();
        $result = $string;
        switch($param->getParameterType()){
            case Parameter::PARAMTYPE_INTEGER:
                $result = "[int : $string]";
                break;
            case Parameter::PARAMTYPE_FLOAT:
                $result = "[float : $string]";
                break;
            case Parameter::PARAMTYPE_BOOLEAN:
                $result = "[boolean : $string]";
                break;
            case Parameter::PARAMTYPE_TARGET:
                $result = "[target : $string]";
                break;
            case Parameter::PARAMTYPE_STRING:
                $result = "[string : $string]";
                break;
            case Parameter::PARAMTYPE_ANY:
                $result = "[any : $string]";
                break;
            default:
        }
        return $result;
    }

    /**
     * @param string $s
     * @return bool|null
     */
    protected function getBoolean(string $s) {
        $result = null;
        if($s === "enable" or $s === "on" or $s == "true"){
            $result = true;
        } else if ($s === "disable" or $s === "off" or $s === "false"){
            $result = false;
        }
        return $result;
    }

    /**
     * @param array $args
     * @param array $paramGroup
     * @return bool
     */
    protected function hasProperParamLen(array $args, array $paramGroup) : bool {

        $argsLen = count($args); $minLen = 0; $maxLen = 0;

        foreach($paramGroup as $parameter){
            $addToLen = true;
            if($parameter instanceof SimpleParameter){
                if($parameter->isOptional()){
                    $addToLen = false;
                }
            }
            if($addToLen) {
                $minLen += 1;
            }
            $maxLen += 1;
        }

        if($minLen === $maxLen){
            $result = $argsLen === $maxLen;
        } else {
            $result = $argsLen >= $minLen and $argsLen <= $maxLen;
        }
        return $result;
    }

    /**
     * @param CommandSender $sender
     * @param string $label
     * @param string[] $args
     *
     * @return mixed
     */
    public function canExecute(CommandSender $sender, string $label, array $args) : bool {

        $execute = false;
        $result = false;
        $msg = null;
        if($this->areParametersCorrect()){
            $len = count($args);
            if($len > 0 and $this->hasParamGroup($args[0])){
                $execute = true;
            }
        }

        if($execute){
            if($this->checkPermissions($sender, $args[0])){
                $paramGroup = $this->getParamGroupFrom($args[0]);
                if($this->hasProperParamLen($args, $paramGroup) and $this->hasProperParamTypes($args, $paramGroup)){
                    $result = true;
                } else {
                    $msg = $this->getUsageOf($paramGroup, false);
                }
            } else {
                $msg = $this->getPermissionMessage();
            }
        } else {
            $msg = $this->getFullUsage();
        }

        if($msg !== null) {
            $sender->sendMessage($msg);
        }

        return $result;
    }

    /**
     * @param CommandSender $sender
     * @param string $commandLabel
     * @param string[] $args
     *
     * @return mixed
     * @throws CommandException
     */
    public function execute(CommandSender $sender, string $label, array $args)
    {
        return parent::execute($sender, $label, $args);
    }

    /**
     * @param array $paramGrp
     * @param bool $fullMsg
     * @return String
     */
    protected function getUsageOf(array $paramGrp, bool $fullMsg) : String {
        $theCommandName = parent::getName();
        $str = ($fullMsg ? " - /$theCommandName " : "Usage: /$theCommandName ");
        $count = 0;
        $desc = null;
        $len = count($paramGrp) - 1;

        foreach($paramGrp as $parameter){
            if($parameter instanceof Parameter) {

                if ($count === 0) {
                    $name = $parameter->getName();
                    $s = ($len === 0 ? "" : " ");
                    $str = $str . $name . $s;
                    if($parameter instanceof BaseParameter){
                        $desc = $parameter->getDescription();
                    }
                } else {
                    $space = ($count === $len ? "" : " ");
                    if($parameter instanceof SimpleParameter){
                        $str = $str . $this->getParameterType($parameter) . $space;
                    }
                }
                $count++;
            }
        }

        if($desc !== null){
            $str = $str . " - " . $desc;
        }
        return $str;
    }

    /**
     * @return string
     */
    protected function getFullUsage() : string {

        $array = array();

        $size = count($this->parameters);

        for($i = 0; $i < $size; $i++){
            $arr = $this->parameters[$i];
            if(is_array($arr) and count($arr) > 0){
                $first = $arr[0];
                if($first instanceof Parameter){
                    $name = $first->getName();
                    array_push($array, $name);
                }
            }
        }

        $result = "All the " . parent::getName() . " commands:\n";
        $count = 0;
        $len = count($array) - 1;
        foreach($array as $string){
            if(is_string($string)){
                $newLine = "\n";
                if($count == $len){
                    $newLine = "";
                }
                $result = $result . $this->getUsageOf($this->getParamGroupFrom($string), true) . $newLine;
                $count++;
            }
        }

        return $result;
    }
}
