<?php
/**
 * Show VCard Contact Photo for sender or Gravatar Icon for Sender
 *
 * This plugin will show contact picture for message sender.
 * This will only work with Roundcube 0.6-svn  
 *
 * Enable the plugin in config/main.inc.php
 * $rcmail_config['plugins'] = array('show_contactimg');
 *
 * @version 0.4
 * @author Eric Appelt
 * @website http://www.php-lexikon.de
 */

class show_contactimg extends rcube_plugin {
	public $task = 'mail';
	
  // default no pic
  private $nopic = '/images/contactpic.png';
  // the size for picture and gravatar
  private $picsize = '60';
  // set this to true for gravatar fallback
  private $usegravatar = true;	
  private $rc;
	private $contactphoto;
	private $sender;

	function init() {
		$rcmail = rcmail::get_instance();
		$this->rc = $rcmail;
		$this->homedir = $this->home.'/tmp/';

		if($this->rc->action == 'show' || $this->rc->action == 'preview') {
			$this->add_hook('message_load', array($this, 'message_load'));
			$this->add_hook('template_object_messageheaders', array($this, 'html_output'));

			$this->include_stylesheet("skins/default/show_contactimg.css");
		}
	}

	private function show_image($data, $sender_id) {
		if(!preg_match('![^a-z0-9/=+-]!i', $data)) {
			$data = base64_decode($data, true);
			$mimetype = rc_image_content_type($data);

			if(strpos($mimetype, 'jpeg'))
				$filetype = '.jpg';
			elseif(strpos($mimetype, 'gif'))
				$filetype = '.gif';
			elseif(strpos($mimetype, 'png'))
				$filetype = '.png';
			else
				return $this->nopic;

			$picname = $sender_id.$filetype;
			$tmpname = $this->homedir.$picname;
			if(!file_exists($tmpname)) {
				$handle = fopen($tmpname, "w");
				fwrite($handle, $data);
				fclose($handle);
			}
			return $this->urlbase.'tmp/'.$picname;
		}
		else {
			return $this->nopic;
		}
	}

	public function message_load($p) {
		$this->sender = (array )$p['object']->sender;
		$sender_id = md5(strtolower(trim($this->sender['mailto'])));
		$book_types = $this->rc->config->get('autocomplete_addressbooks', array('sql'));

		foreach($book_types as $id) {
			$abook = $this->rc->get_address_book($id, true);
			$existing_contact = $abook->search('email', $this->sender['mailto'], false, true)->records[0]['photo'];
			if($existing_contact) {
			  /** 
         * Add User ID for non Global Contacts to sender_id 
         * for the Case thats different Users has the same Contacts
         * in Addressbook with different Contact Photos.
         */
        if($id != 'global') {
          $sender_id = md5(strtolower(trim($this->sender['mailto'].strrev(trim($this->rc->user->ID)))));
        } 
				$this->contactphoto = $this->show_image($existing_contact, $sender_id);
			}
		}

    if($this->usegravatar == true and !$this->contactphoto) {
      $rcbaseurl = 'http'.(($_SERVER['HTTPS']=='on') ? 's' : '').'://'.$_SERVER['HTTP_HOST'].str_replace($_SERVER['DOCUMENT_ROOT'],'',INSTALL_PATH).'skins/default'.$this->nopic;
      $this->contactphoto = "http://www.gravatar.com/avatar/".$sender_id."?d=".urlencode($rcbaseurl)."&s=".$this->picsize;
    }
    elseif(!$this->contactphoto) {
			$this->contactphoto = $this->nopic;
    }
		return $this->contactphoto;
	}

	private function contactimg() {
		return html::div(array('class' => 'contactphoto'), html::img(array('src' => $this->contactphoto, 'height' => $this->picsize, 'title' => $this->sender['mailto'], 'alt' => $this->sender['mailto'])));
	}

	public function html_output($p) {
		$p['content'] = $this->contactimg($p).$p['content'];

		return $p;
	}
}