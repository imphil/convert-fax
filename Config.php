<?php
/*
 * Comfort Pro Fax Converter
 *
 * Copyright 2010-2011  Philipp Wagner <mail@philipp-wagner.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Configuration
 *
 * Singleton class to access the configuration stored in an INI file.
 *
 * @author Philipp Wagner <mail@philipp-wagner.com>
 */
class Config
{
    private $configData;
    
    private static $instance = null;

    /**
     * Private constructor - use getInstance() to access this class.
     */
    private function __construct() {}

    public function setConfigFile($configFile)
    {
        if (!file_exists($configFile)) {
            throw new Exception("Unable to read config file $configFile");
        }
        $this->readConfig($configFile);
    }

    /**
     * Get an reference to this object (singleton)
     * 
     * @return Config
     */
    public function &getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Config();
        }
        return self::$instance;
    }

    private function readConfig($configFile)
    {
        $this->configData = parse_ini_file($configFile, true);
    }
    
    public function getValue($key)
    {
        $parts = explode('/', $key);
        $d = $this->configData;
        foreach ($parts as $part) {
            if (!isset($d[$part])) {
                throw new Exception("Config key $key not found.");
            }
            $d = $d[$part];
        }
        return $d;
    }
}
?>
