<?php
/**
 * Show VCard Contact Photo for sender
 *
 * This plugin will show contact picture for message sender.
 * This will only work with Roundcube 0.6-svn  
 *
 * Enable the plugin in config/main.inc.php
 * $rcmail_config['plugins'] = array('show_contactimg');
 *
 * @version 0.2
 * @author Eric Appelt
 * @website http://www.php-lexikon.de
 */

class show_contactimg extends rcube_plugin {
	public $task = 'mail';
	public $nopic = '/images/contactpic.png';
	public $rc;

	private $contactphoto;
	private $sender;

	function init() {
		$rcmail = rcmail::get_instance();
		$this->rc = $rcmail;
		$this->homedir = $this->home.'/tmp/';

		if($this->rc->action == 'show' || $this->rc->action == 'preview') {
			$this->add_hook('message_load', array($this, 'message_load'));
			$this->add_hook('template_object_messageheaders', array($this, 'html_output'));

			// add style for placing photo
			$this->include_stylesheet("skins/default/show_contactimg.css");
		}
	}

	function show_image($data, $sender_id) {
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

	function message_load($p) {
		$this->sender = (array )$p['object']->sender;
		$sender_id = md5(strtolower($this->sender['mailto']));
		$book_types = $this->rc->config->get('autocomplete_addressbooks', array('sql'));

		foreach($book_types as $id) {
			$abook = $this->rc->get_address_book($id, true);
			$existing_contact = $abook->search('email', $this->sender['mailto'], false, true)->records[0]['photo'];
			if($existing_contact) {
				$this->contactphoto = $this->show_image($existing_contact, $sender_id);
			}
		}
		if(!$this->contactphoto)
			$this->contactphoto = $this->nopic;

		return $this->contactphoto;
	}

	function contactimg() {
		return html::div(array('class' => 'contactphoto'), html::img(array('src' => $this->contactphoto, 'height' => '60', 'title' => $this->sender['mailto'], 'alt' => $this->sender['mailto'])));
	}

	function html_output($p) {
		$p['content'] = $this->contactimg($p).$p['content'];

		return $p;
	}
}