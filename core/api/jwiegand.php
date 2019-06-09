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

//  /plugins/jwiegand/core/api/jwiegand.php?apikey=...&name=BADGER1&ip=192.168.0.177&model=wiegand1&cmd=tag&value=70605040
$datetime = date('Y-m-d H:i:s');

if($cmd == "test")
{
    log::add('jwiegand', 'info', 'Command TEST ');
    return true;
}

$elogicReader = jwiegand::byLogicalId($readerid, 'jwiegand');
if (!is_object($elogicReader)) {

    if (config::byKey('allowAllinclusion', 'jwiegand') != 1) {
        // Gestion des lecteurs inconnus
        log::add('jwiegand', 'error', 'Lecteur inconnu detecté : '.$readerid);
        return true;
    }
    // Ajout du lecteur de badge si il n'existe pas et discover actif
    $elogicReader = new jwiegand();
    $elogicReader->setEqType_name('jwiegand');
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

    if (( $tagcounter >= $taglimit ) | ( $pincounter >= $pinlimit ) | ($tagtrylimit != 0))
    {
        $latsuse = new DateTime($elogicReader->getConfiguration('lastuse',$datetime));
        $now = new DateTime("now");
        $interval = date_diff($latsuse, $now);
        

        if ( $interval->i < $timebloc )
        {
            log::add('jwiegand', 'error', 'Lecteur bloqué');
            return true;
        }

    }

}

$readername = $elogicReader->getName();

if($cmd == "tag")
{
    $badgeid = "BADGE ".$value;

    // Badge Présenté au lecteurs , ajout de ce badge si il n'existe pas et include actif
    $elogic = jwiegand::byLogicalId($badgeid, 'jwiegand');
    $elogicReader->setConfiguration('IDBadge',$value);
    $elogicReader->save();
    $cmd = jwiegandCmd::byEqLogicIdCmdName($elogicReader->getId(),'IDBadge');
    $cmd->event($value);

    if (!is_object($elogic)) {
        
    
        if ((config::byKey('allowAllinclusion', 'jwiegand') != 1) | ($taglimit != 0)) {
            // Gestion des tags inconnus
            log::add('jwiegand', 'error', 'Badge : '.$value.' inconnu présenté sur le lecteur :'.$readername);
        
            $tagcounter = intval($elogicReader->getConfiguration('tagcount','0'));
            $tagcounter++;

            $elogicReader->setConfiguration('tagcount',strval($tagcounter));
            $elogicReader->setConfiguration('lastuse',$datetime);
            $elogicReader->save();

            $taglimit = intval($elogicReader->getConfiguration('tagtrylimit','0'));
            if ( $tagcounter >= $taglimit )
            {
                //$elogicReader->setIsEnable(false);
                //$elogicReader->save();
                $cmd = jwiegandCmd::byEqLogicIdCmdName($elogicReader->getId(),'TagTryLimit');
                if (!is_object( $cmd )){
                    log::add('jwiegand', 'error', 'Reader : '.$elogicReader->getName().' commande TagTryLimit introuvable.');
                    return false;
                }
                $cmd->event($datetime); 
            }


            return true;
        }

        // Ajout du badge si il n'existe pas et include actif
        $elogic = new jwiegand();
        $elogic->setEqType_name('jwiegand');
        $elogic->setLogicalId($badgeid);
        $elogic->setName($badgeid);
        $elogic->setConfiguration('modelTag','Tag RFID');
        $elogic->setConfiguration('type','badge');
        $elogic->setConfiguration('value',$value);
        $elogic->setCategory('security', 1);        
        $elogic->save();

        return true;
    }
    else  
    {
        // le badge existe process des commandes
    // Test si badge desactivé
    if ( $elogic->getIsEnable()==false )
        return true;
        
        log::add('jwiegand', 'info', 'Badge :'.$elogic->getName().' présenté sur le lecteur : '.$readername);
        
        // reset compteur code faux
        if ($elogicReader->getConfiguration('tagcount','0')!='0' )
        {
            $elogicReader->setConfiguration('tagcount','0');
            $elogicReader->save();
        }

        $cmd = jwiegandCmd::byEqLogicIdCmdName($elogic->getId(),'BadgerID');
        if (!is_object( $cmd )){
            log::add('jwiegand', 'error', 'Badge : '.$elogic->getName().' commande BadgerID introuvable.');
            return false;
        }
        $cmd->event($readername);   

        $cmd = jwiegandCmd::byEqLogicIdCmdName($elogic->getId(),'Presentation');
        if (!is_object( $cmd )){
            log::add('jwiegand', 'error', 'Badge : '.$elogic->getName().' commande Presentation introuvable.');
            return false;
        }           
        $cmd->setCollectDate($datetime);
        $cmd->event($datetime.' - '.$readername);               

    }   
}

return true;
?>