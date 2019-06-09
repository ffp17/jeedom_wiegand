<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";
/*
if (init('apikey') != config::byKey('api') || config::byKey('api') == '') {
    connection::failed();
    echo 'Clef API non valide, vous n\'etes pas autorisé à effectuer cette action (jeeApi)';
    die();
}
*/
$ip = init('ip');
$cmd = init('cmd');
$value = init('value');
$name = init('name');
//$model = init('model');

$readerid = $name;

//  /plugins/badger/core/api/jeebadger.php?apikey=...&name=BADGER1&ip=192.168.0.177&model=wiegand1&cmd=tag&value=70605040
$datetime = date('Y-m-d H:i:s');

if($cmd == "test")
{
    log::add('badger', 'info', 'Command TEST ');
    return true;
}

$elogicReader = badger::byLogicalId($readerid, 'badger');
if (!is_object($elogicReader)) {

    if (config::byKey('allowAllinclusion', 'badger') != 1) {
        // Gestion des lecteurs inconnus
        log::add('badger', 'error', 'Lecteur inconnu detecté : '.$readerid);
        return true;
    }
    // Ajout du lecteur de badge si il n'existe pas et discover actif
    $elogicReader = new badger();
    $elogicReader->setEqType_name('badger');
    $elogicReader->setLogicalId($readerid);
    $elogicReader->setName($name);
    $elogicReader->setConfiguration('ip',$ip);
    //$elogicReader->setConfiguration('modelReader',$model);
    $elogicReader->setConfiguration('type','reader');
    $elogicReader->setConfiguration('tagcount',0);
    if ( $model == 'wiegand2')
        $elogicReader->setConfiguration('pincount',0);
    $elogicReader->setCategory('security', 1);      
    $elogicReader->save();


} else {
    // Test si lecteur desactivé
    if ( $elogicReader->getIsEnable()==false )
        return true;

    // Mise a jours des infos du lecteur de badge 
    if ($ip != $elogicReader->getConfiguration('ip'))  {
        $elogicReader->setConfiguration('ip',$ip);
        //$elogicReader->setConfiguration('modelReader',$model);
        
        $elogicReader->save();
    }   

    // Test si lecteur bloqué
        
    $tagcounter = intval($elogicReader->getConfiguration('tagcount','0'));
    $taglimit = intval($elogicReader->getConfiguration('tagtrylimit','1'));
    $pincounter = intval($elogicReader->getConfiguration('pincount','0'));
    $pinlimit = intval($elogicReader->getConfiguration('pintrylimit','1'));
    $timebloc = intval($elogicReader->getConfiguration('retrytimer','1'));

    if (( $tagcounter >= $taglimit ) | ( $pincounter >= $pinlimit ))
    {
        $latsuse = new DateTime($elogicReader->getConfiguration('lastuse',$datetime));
        $now = new DateTime("now");
        $interval = date_diff($latsuse, $now);
        

        if ( $interval->i < $timebloc )
        {
            log::add('badger', 'error', 'Lecteur bloqué');
            return true;
        }

    }

}

$readername = $elogicReader->getName();

if($cmd == "tag")
{
    $badgeid = "BADGE ".$value;

    // Badge Présenté au lecteurs , ajout de ce badge si il n'existe pas et include actif
    $elogic = badger::byLogicalId($badgeid, 'badger');
    $elogicReader->setConfiguration('IDBadge',$value);
    $elogicReader->save();
    $cmd = badgerCmd::byEqLogicIdCmdName($elogicReader->getId(),'IDBadge');
    $cmd->event($value);
    $cmd->evnet($datetime); 
}

return true;
?>