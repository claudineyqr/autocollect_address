<?php

class autocollect_address_backend extends rcube_contacts
{
    function __construct($dbconn, $user)
    {
        parent::__construct($dbconn, $user);
        $this->db_name = rcmail::get_instance()->db->table_name('contacts');
    }
}
