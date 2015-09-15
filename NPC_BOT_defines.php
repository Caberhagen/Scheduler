<?php
/*
    This file is part of STFC.it
    Copyright 2008-2013 by Andrea Carolfi (carolfi@stfc.it) and
    Cristiano Delogu (delogu@stfc.it).

    STFC.it is based on STFC,
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

// Constants for StartBuild and Install common methods 
define('BUILD_SUCCESS',0);
define('BUILD_ERR_QUEUE', -1);
define('BUILD_ERR_RESOURCES', -2);
define('BUILD_ERR_REQUIRED', -3);
define('BUILD_ERR_ENERGY', -4);
define('BUILD_ERR_DB', -5);
define('BUILD_ERR_MAXLEVEL',-6);
define('INSTALL_LOG_FILE_NPC', $game_path.'logs/NPC_installation.log');


// Constant for Ramona Ferengi BOT
define('PICK_RESOURCES_FROM_PLANET',1); // 1 = remove resources from BOT's planet
                                        // 0 = left BOT's planet untouched

define('MALL_RESOURCES_AVAILABLE',0);   // 1 = resourses available at the Feregi's Mall
                                        // 0 = no resources available


// Constant for SevenOfNine Borg BOT
define ('BORG_SIGNATURE', 'We are the Borg. Lower your shields and surrender your ships.<br>We will add your biological and technological distinctiveness to our own.<br>Your culture will adapt to service us. Resistance is futile.');

define ('BORG_SPHERE', 'Borg Sphere');

define ('BORG_CUBE', 'Borg Cube');

define ('BORG_TACT', 'Borg Tact Cube');

define ('BORG_UM0', 'Borg Unimatrix 0');

define ('BORG_RACE','6'); // Well, this one should be defined in global.php among the other races */

define ('BORG_QUADRANT', 2); // Default Borg belong to Delta quadrant

define ('BORG_CYCLE', 1200); // One attack each how many tick?

define ('BORG_CHANCE', 80); // Attack is not sistematic, leave a little chance

define ('BORG_MINATTACK', 10); // Attack only players with at least n planets

define ('BORG_BIGPLAYER', 12000); // Send a cube instead of spheres to player above this points

define ('BORG_MAXPLANETS', 0); // Start to assimilate below this planets amount; Set to ZERO to stop Borg activity


// Constant for Mayflower Settlers BOT

define('LC_FIRST_CONTACT', 1);
define('LC_DIPLO_SPEECH', 2);

define('LC_SUP_TECH', 3);
define('LC_SUP_MEDI', 4);
define('LC_SUP_DFNS', 5);
define('LC_SUP_AUTO', 6);
define('LC_SUP_MINE',7);
define('LC_MIL_ORBITAL', 8); 

define('LC_REL_MULTIC', 10);
define('LC_REL_TSUPER', 11);
define('LC_REL_CHAMPN', 12);
define('LC_REL_INNVTR', 13);
define('LC_REL_OPPSTR', 14);
define('LC_REL_PLURLS', 15);
define('LC_REL_PRESTG', 16);
define('LC_REL_DEFNDR', 17);
define('LC_REL_CMPTNT', 18);
define('LC_REL_LIBERT', 19);
define('LC_REL_LEADER', 20);
define('LC_REL_MECENA', 21);
define('LC_REL_WORSHP', 22);
define('LC_REL_PREDAT', 23);
define('LC_REL_STRNGR', 24);
define('LC_REL_UNABLE', 25);
define('LC_REL_EXPLOI', 26);
define('LC_REL_PREY',   27);

define('LC_COLO_FOUNDER', 30);
define('LC_COLO_GIVER', 31);

define('STL_MAX_ORBITAL', 120);
?>
