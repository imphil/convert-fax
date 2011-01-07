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

// Add Zend Framework to include path
$zfPath = '3rdparty/zendframework/library';
set_include_path($zfPath.PATH_SEPARATOR.get_include_path());

include 'Zend/Loader/Autoloader.php';
$autoloader = Zend_Loader_Autoloader::getInstance();
    
function clear_comfort_pro_inbox(Config &$config)
{
    $username = $config->getValue('comfort-pro/username');
    $password = $config->getValue('comfort-pro/password');
    $adminUrl = $config->getValue('comfort-pro/url');
    
    $client = new Zend_Http_Client();
    $client->setCookieJar();

    // Step 0: Logout (otherwise the Comfort Pro sometimes seems to get confused)
    $client->setUri("$adminUrl/home.asp?state=2");
    $client->request();

    // Step 1: Extract login ID and SID, set cookies
    $client->setUri("$adminUrl/home-login.asp?state=0");
    $response = $client->request();
    preg_match('/<INPUT TYPE=HIDDEN NAME=\'login\' VALUE=\'(.{9})\'>/',
              $response->getBody(), $matches);
    $loginId = $matches[1];
    $sid = preg_match("/setCookie\('sid','(.+)'\)/",
                      $response->getBody(), $matches);
    $sid = $matches[1];

    $client->setCookie('sid', $sid);
    $client->setCookie('usr', $username);

    // Step 2: Login
    $client->setUri("$adminUrl/goform/home_login_set");
    $client->setParameterPost('pw', '');
    $client->setParameterPost('un', $username);
    $client->setParameterPost('login', md5($username.'*'.$password.$loginId));
    $response = $client->request(Zend_Http_Client::POST);

    // Step 3: Delete existing messages
    $client->setUri("$adminUrl/vphone/vp-list-mailin.asp");
    $client->setParameterGet('mode', '12');
    $client->setParameterGet('entry', '');
    $client->setParameterGet('rnr', '  0');
    $client->setParameterGet('type', '');
    $client->setParameterGet('read', '');
    $response = $client->request();
}
