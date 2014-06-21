<?php
/* vim:set softtabstop=4 shiftwidth=4 expandtab: */
/**
 *
 * LICENSE: GNU General Public License, version 2 (GPLv2)
 * Copyright 2001 - 2014 Ampache.org
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; version 2
 * of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 */

/**
 * Playlist Class
 *
 * This class handles playlists in ampache. it references the playlist* tables
 *
 */
class Playlist extends playlist_object
{
    /* Variables from the database */
    public $genre;
    public $date;

    public $f_link;
    public $f_name_link;

    /* Generated Elements */
    public $items = array();

    /**
     * Constructor
     * This takes a playlist_id as an optional argument and gathers the information
     * if not playlist_id is passed returns false (or if it isn't found
     */
    public function __construct($id)
    {
        $info = $this->get_info($id);

        foreach ($info as $key=>$value) {
            $this->$key = $value;
        }

    } // Playlist

    /**
     * gc
     *
     * Clean dead items out of playlists
     */
    public static function gc()
    {
        Dba::write("DELETE FROM `playlist_data` USING `playlist_data` LEFT JOIN `song` ON `song`.`id` = `playlist_data`.`object_id` WHERE `song`.`file` IS NULL AND `playlist_data`.`object_type`='song'");
        Dba::write("DELETE FROM `playlist` USING `playlist` LEFT JOIN `playlist_data` ON `playlist_data`.`playlist` = `playlist`.`id` WHERE `playlist_data`.`object_id` IS NULL");
    }

    /**
     * build_cache
     * This is what builds the cache from the objects
     */
    public static function build_cache($ids)
    {
        if (!count($ids)) { return false; }

        $idlist = '(' . implode(',',$ids) . ')';

        $sql = "SELECT * FROM `playlist` WHERE `id` IN $idlist";
        $db_results = Dba::read($sql);

        while ($row = Dba::fetch_assoc($db_results)) {
            parent::add_to_cache('playlist',$row['id'],$row);
        }

    } // build_cache

    /**
     * get_playlists
     * Returns a list of playlists accessible by the current user.
     */
    public static function get_playlists()
    {
        $sql = 'SELECT `id` from `playlist`';
        $sql_order = ' ORDER BY `name`';

        if (!Access::check('interface','100')) {
            $sql .= " WHERE `type`='public' OR " .
            "`user`='" . $GLOBALS['user']->id . "'";
        }

        $sql .= $sql_order;

        $db_results = Dba::read($sql);

        $results = array();

        while ($row = Dba::fetch_assoc($db_results)) {
            $results[] = $row['id'];
        }

        return $results;
    } // get_playlists

    /**
     * format
     * This takes the current playlist object and gussies it up a little
     * bit so it is presentable to the users
     */
    public function format()
    {
        parent::format();
        $this->f_link = AmpConfig::get('web_path') . '/playlist.php?action=show_playlist&playlist_id=' . $this->id;
        $this->f_name_link = '<a href="' . $this->f_link . '">' . $this->f_name . '</a>';

    } // format

    /**
     * get_track
     * Returns the single item on the playlist and all of it's information, restrict
     * it to this Playlist
     */
    public function get_track($track_id)
    {
        $sql = "SELECT * FROM `playlist_data` WHERE `id` = ? AND `playlist` = ?";
        $db_results = Dba::read($sql, array($track_id, $this->id));

        $row = Dba::fetch_assoc($db_results);

        return $row;

    } // get_track

    /**
     * get_items
     * This returns an array of playlist songs that are in this playlist.
     * Because the same song can be on the same playlist twice they are
     * keyed by the uid from playlist_data
     */
    public function get_items()
    {
        $results = array();

        $sql = "SELECT `id`,`object_id`,`object_type`,`track` FROM `playlist_data` WHERE `playlist`= ? ORDER BY `track`";
        $db_results = Dba::read($sql, array($this->id));

        while ($row = Dba::fetch_assoc($db_results)) {
            $results[] = array(
                'object_type' => $row['object_type'],
                'object_id' => $row['object_id'],
                'track' => $row['track'],
                'track_id' => $row['id']
            );
        } // end while

        return $results;

    } // get_items

    /**
     * get_random_items
     * This is the same as before but we randomize the buggers!
     */
    public function get_random_items($limit='')
    {
        $results = array();

        $limit_sql = $limit ? 'LIMIT ' . intval($limit) : '';

        $sql = "SELECT `object_id`,`object_type` FROM `playlist_data` " .
            "WHERE `playlist` = ? ORDER BY RAND() $limit_sql";
        $db_results = Dba::read($sql, array($this->id));

        while ($row = Dba::fetch_assoc($db_results)) {

            $results[] = array(
                'object_type' => $row['object_type'],
                'object_id' => $row['object_id']
            );
        } // end while

        return $results;

    } // get_random_items

    /**
     * get_songs
     * This is called by the batch script, because we can't pass in Dynamic objects they pulled once and then their
     * target song.id is pushed into the array
     */
    public function get_songs()
    {
        $results = array();

        $sql = "SELECT * FROM `playlist_data` WHERE `playlist` = ? ORDER BY `track`";
        $db_results = Dba::read($sql, array($this->id));

        while ($r = Dba::fetch_assoc($db_results)) {
            $results[] = $r['object_id'];
        } // end while

        return $results;

    } // get_songs

    /**
     * get_song_count
     * This simply returns a int of how many song elements exist in this playlist
     * For now let's consider a dyn_song a single entry
     */
    public function get_song_count()
    {
        $sql = "SELECT COUNT(`id`) FROM `playlist_data` WHERE `playlist` = ?";
        $db_results = Dba::read($sql, array($this->id));

        $results = Dba::fetch_row($db_results);

        return $results['0'];

    } // get_song_count

    /**
    * get_total_duration
    * Get the total duration of all songs.
    */
    public function get_total_duration()
    {
        $songs = self::get_songs();
        $idlist = '(' . implode(',', $songs) . ')';

        $sql = "SELECT SUM(`time`) FROM `song` WHERE `id` IN $idlist";
        $db_results = Dba::read($sql);

        $results = Dba::fetch_row($db_results);

        return $results['0'];

    } // get_total_duration

    /**
     * get_users
     * This returns the specified users playlists as an array of
     * playlist ids
     */
    public static function get_users($user_id)
    {
        $results = array();

        $sql = "SELECT `id` FROM `playlist` WHERE `user` = ? ORDER BY `name`";
        $db_results = Dba::read($sql, array($user_id));

        while ($row = Dba::fetch_assoc($db_results)) {
            $results[] = $row['id'];
        }

        return $results;

    } // get_users

    /**
      * update
     * This function takes a key'd array of data and runs updates
     */
    public function update($data)
    {
        if ($data['name'] != $this->name) {
            $this->update_name($data['name']);
        }
        if ($data['pl_type'] != $this->type) {
            $this->update_type($data['pl_type']);
        }

    } // update

    /**
     * update_type
     * This updates the playlist type, it calls the generic update_item function
     */
    private function update_type($new_type)
    {
        if ($this->_update_item('type',$new_type,'50')) {
            $this->type = $new_type;
        }

    } // update_type

    /**
     * update_name
     * This updates the playlist name, it calls the generic update_item function
     */
    private function update_name($new_name)
    {
        if ($this->_update_item('name',$new_name,'50')) {
            $this->name = $new_name;
        }

    } // update_name

    /**
     * _update_item
     * This is the generic update function, it does the escaping and error checking
     */
    private function _update_item($field,$value,$level)
    {
        if ($GLOBALS['user']->id != $this->user AND !Access::check('interface',$level)) {
            return false;
        }

        $sql = "UPDATE `playlist` SET `$field` = ? WHERE `id` = ?";
        $db_results = Dba::write($sql, array($value, $this->id));

        return $db_results;

    } // update_item

    /**
     * update_track_number
     * This takes a playlist_data.id and a track (int) and updates the track value
     */
    public function update_track_number($track_id, $index)
    {
        $sql = "UPDATE `playlist_data` SET `track` = ? WHERE `id` = ?";
        Dba::write($sql, array($index, $track_id));

    } // update_track_number

    /**
     * add_songs
     * This takes an array of song_ids and then adds it to the playlist
     */
    public function add_songs($song_ids=array(),$ordered=false)
    {
        /* We need to pull the current 'end' track and then use that to
         * append, rather then integrate take end track # and add it to
         * $song->track add one to make sure it really is 'next'
         */
        $sql = "SELECT `track` FROM `playlist_data` WHERE `playlist` = ? ORDER BY `track` DESC LIMIT 1";
        $db_results = Dba::read($sql, array($this->id));
        $data = Dba::fetch_assoc($db_results);
        $base_track = $data['track'];
        debug_event('add_songs', 'Track number: '.$base_track, '5');

        $i = 0;
        foreach ($song_ids as $song_id) {
            /* We need the songs track */
            $song = new Song($song_id);

            // Based on the ordered prop we use track + base or just $i++
            if (!$ordered) {
                $track    = $song->track + $base_track;
            } else {
                $i++;
                $track = $base_track + $i;
            }

            /* Don't insert dead songs */
            if ($song->id) {
                $sql = "INSERT INTO `playlist_data` (`playlist`,`object_id`,`object_type`,`track`) " .
                    " VALUES (?, ?, 'song', ?)";
                Dba::write($sql, array($this->id, $song->id, $track));
            } // if valid id

        } // end foreach songs

    } // add_songs

    /**
     * create
     * This function creates an empty playlist, gives it a name and type
     * Assumes $GLOBALS['user']->id as the user
     */
    public static function create($name,$type)
    {
        $sql = "INSERT INTO `playlist` (`name`,`user`,`type`,`date`) VALUES (?, ?, ?, ?)";
        Dba::write($sql, array($name, $GLOBALS['user']->id, $type, time()));

        $insert_id = Dba::insert_id();
        return $insert_id;

    } // create

    /**
     * set_items
     * This calls the get_items function and sets it to $this->items which is an array in this object
     */
    public function set_items()
    {
        $this->items = $this->get_items();

    } // set_items

    /**
     * delete_track
     * this deletes a single track, you specify the playlist_data.id here
     */
    public function delete_track($id)
    {
        $sql = "DELETE FROM `playlist_data` WHERE `playlist_data`.`playlist` = ? AND `playlist_data`.`id` = ? LIMIT 1";
        Dba::write($sql, array($this->id, $id));

        return true;

    } // delete_track

    /**
    * delete_track_number
    * this deletes a single track by it's track #, you specify the playlist_data.track here
    */
    public function delete_track_number($track)
    {
        $sql = "DELETE FROM `playlist_data` WHERE `playlist_data`.`playlist` = ? AND `playlist_data`.`track` = ? LIMIT 1";
        Dba::write($sql, array($this->id, $track));

        return true;

    } // delete_track_number

    /**
     * delete
     * This deletes the current playlist and all associated data
     */
    public function delete()
    {
        $sql = "DELETE FROM `playlist_data` WHERE `playlist` = ?";
        Dba::write($sql, array($this->id));

        $sql = "DELETE FROM `playlist` WHERE `id` = ?";
        Dba::write($sql, array($this->id));

        $sql = "DELETE FROM `object_count` WHERE `object_type`='playlist' AND `object_id` = ?";
        Dba::write($sql, array($this->id));

        return true;

    } // delete

} // class Playlist