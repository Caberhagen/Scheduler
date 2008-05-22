<?php
/*    
	This file is part of STFC.
	Copyright 2006-2007 by Michael Krauss (info@stfc2.de) and Tobias Gafner
		
	STFC is based on STGC,
	Copyright 2003-2007 by Florian Brede (florian_brede@hotmail.com) and Philipp Schmidt
	
    STFC is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 3 of the License, or
    (at your option) any later version.

    STFC is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
include('|script_dir|/game/include/sql.php');
include('|script_dir|/game/include/global.php');
include('|script_dir|/game/include/functions.php');
include('|script_dir|/game/include/libs/world.php');

$game = new game();
$db = new sql($config['server'].":".$config['port'], $config['game_database'], $config['user'], $config['password']); // create sql-object for db-connection

$result=$db->query('SELECT * FROM user WHERE (num_sitting/(num_hits+1))>0.35 AND (num_sitting>50 OR (num_hits<10 AND num_sitting>30))');
$db->query('UPDATE user SET num_hits=0, num_sitting=0');
while ($user=$db->fetchrow($result))
{
	$val=($user['num_sitting']+1)/$user['num_hits'];
	$text='fast ausschlie&szlig;lich gesittet';
	if ($val<0.8) $text='stark &uuml;berm&auml;&szlig;ig gesittet';
	if ($val<0.6) $text='�berm&auml;&szlig;ig gesittet';
	if ($val<0.45) $text='etwas zuviel gesittet';
	$message='Hallo '.$user['user_name'].',<br>dein Account kann <b>ab jetzt</b> f&uuml;r <b>einen Tag</b> nicht gesittet werden, weil er innerhalb der letzten 24 Stunden <b>'.$text.'</b> wurde.<br>Diese Nachricht wurde automatisch generiert, Beschwerden beim STFC2-Team bringen nichts.<br>~ Sitting-Abuse-Automatik';
	SystemMessage($user['user_id'],'Sittingsperre',$message);
	$db->query('UPDATE user SET num_sitting=-1 WHERE user_id='.$user['user_id']);
       echo $user['user_id'];
};




?>
