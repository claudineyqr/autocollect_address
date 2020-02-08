<?php

class autocollect_address extends rcube_plugin
{
    public $task = 'mail|settings';
  
    /**
     * Initialize plugin
     */
    public function init()
    {
        $this->add_hook('message_sent', array($this, 'register_recipients'));
        $this->add_hook('preferences_list', array($this, 'settings_table'));
        $this->add_hook('preferences_save', array($this, 'save_prefs'));
       
        $this->add_texts('localization/', false);
        $this->load_config('config/config.inc.php');
    }

    /**
     * Collect the email address of a just-sent email recipients into
     * the automatic addressbook (if it's not already in another
     * addressbook). 
     *
     * @param array $p Hash array containing header and body of sent mail
     * @return nothing
     */
    public function register_recipients($p)
    {
        $rcmail = rcmail::get_instance();
    
        if (!$rcmail->config->get('use_auto_collect', true)) {
            return;
        }
    
        $headers = $p['headers'];

        $all_recipients = array_merge(
            rcube_mime::decode_address_list($headers['To'], null, true, $headers['charset']),
            rcube_mime::decode_address_list($headers['Cc'], null, true, $headers['charset']),
            rcube_mime::decode_address_list($headers['Bcc'], null, true, $headers['charset'])
        );

        require_once dirname(__FILE__) . '/autocollect_address_backend.php';
        $CONTACTS = new autocollect_address_backend($rcmail->db, $rcmail->user->ID);
    
        foreach ($all_recipients as $recipient) {
            // Bcc and Cc can be empty
            if ($recipient['mailto'] != '') {
                $contact = array(
                    'email' => $recipient['mailto'],
                    'name' => $recipient['name']
                    );

                // use email address part for name
                if (empty($contact['name']) || $contact['name'] == $contact['email']) {
                    $contact['name'] = ucfirst(preg_replace('/[\.\-]/', ' ',
                                               substr($contact['email'], 0, strpos($contact['email'], '@'))));
                }

                /* We only want to add the contact to the collected contacts
                 * address book if it is not already in an addressbook, so we
                 * first lookup in every address source.
                 */
                $book_types = (array)$rcmail->config->get('autocomplete_addressbooks', 'sql');
                $address_book_identifier = $rcmail->config->get('default_addressbook', 0);

                foreach ($book_types as $id) {
                    $abook = $rcmail->get_address_book($id);
                    $previous_entries = $abook->search('email', $contact['email'], false, false);
      
                    if ($previous_entries->count) {
                        break;
                    }
                }
                if (!$previous_entries->count) {
                    $plugin = $rcmail->plugins->exec_hook('contact_create', array('record' => $contact,
                                                                                  'source' => $address_book_identifier));
                    if (!$plugin['abort']) {
                        $CONTACTS->insert($contact, false);
                    }
                }
            }
        }
    }

    /**
     * Adds a check-box to enable/disable automatic address collection.
     *
     * @param array $args Hash array containing section and preference blocks
     * @return array Hash array containing preference blocks with addressbook preferences
     */
    public function settings_table($args) 
    {
        if ($args['section'] == 'addressbook') {
            $use_auto_collect = rcmail::get_instance()->config->get('use_auto_collect', true);
            $field_id = 'rcmfd_use_auto_collect';

            $checkbox = new html_checkbox(array(
                'name' => '_use_auto_collect', 
                'id' => $field_id, 'value' => 1
            ));
            $args['blocks']['automaticallycollect']['name'] = $this->gettext('automaticallycollect');
            $args['blocks']['automaticallycollect']['options']['use_subscriptions'] = array(
                'title' => html::label($field_id, rcube_utils::rep_specialchars_output($this->gettext('useautocollect'))),
                'content' => $checkbox->show($use_auto_collect ? 1 : 0),
            );           
        }
        return $args;
    }

    /**
     * Save preferences
     *
     * @param array $args Hash array with prefs to be saved 
     * @return array $args Hash array with result: boolean, abort: boolean, prefs: array 
     */
    public function save_prefs($args) 
    {
        if ($args['section'] == 'addressbook') {
            $args['prefs']['use_auto_collect'] = isset($_POST['_use_auto_collect']) ? true : false;
        }
        return $args;
    }
}
