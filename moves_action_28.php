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



class moves_action_28 extends moves_common {
    function _action_main() {
        if($this->dest['user_id'] == $this->move['user_id']) {
            $this->log(MV_M_NOTICE, 'dest owner equals from move owner on private move');
        }
        
        $sql = 'UPDATE ship_fleets
                SET planet_id = '.$this->move['dest'].',
                    move_id = 0
                WHERE fleet_id IN ('.$this->fleet_ids_str.')';

        if(!$this->db->query($sql)) {
            return $this->log(MV_M_DATABASE, 'Could not update fleets data! SKIP');
        }

        return MV_EXEC_OK;
    }
}

?>
