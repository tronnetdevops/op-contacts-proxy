<?php
	/**
	 * Plugin Name: Ontraport Contact Ingetration
	 * Plugin URI: http://tronnet.me
	 * Version: 0.9.8
	 * Author: Tronnet DevOps
	 * Author URI: http://tronnet.me
	 */
	
	
	class OPContactProxy {
		/**
		 * A reference to an instance of this class.
		 */
		private static $instance;
		
		/**
		 * The array of templates that this plugin tracks.
		 */
		protected $templates;
		
		/**
		 * Returns an instance of this class. 
		 */
		public static function get_instance() {
			if( null == self::$instance ) {
				self::$instance = new OPContactProxy();
			} 
			return self::$instance;
		} 
		
		/**
		 * Initializes the plugin by setting filters and administration functions.
		 */
		private function __construct() {
			
			global $current_user;
			get_currentuserinfo();
			
			$refresh_token = get_user_meta($current_user->ID, '_cb_op_google_refresh_token', true);
			
			echo $current_user->ID;
			
			var_dump($refresh_token);

			die();
			
			if (isset($_GET['cb_op_action_oauth'])){
				session_start();

				set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/includes');
			
				require_once( dirname(__FILE__) . '/includes/google-api-php-client/autoload.php');
				
				$client = new Google_Client();
				$client->setApplicationName("OP Contacts Proxy");
				$client->setAuthConfigFile( dirname(__FILE__) . '/includes/data/google_auth.json');
				$client->setRedirectUri('http://wpdemo.tronnet.me/');
				$client->addScope("https://www.google.com/m8/feeds");
				$client->setApprovalPrompt('force');
				$client->setAccessType('offline');
				
				$nonce = uniqid();
				$_SESSION['cb_op_nonce'] = $nonce;
				
				$auth_url = $client->createAuthUrl();
				$auth_url .= '&state=cb_op_action_oauth';
				$auth_url .= '&nonce='.$nonce;
				
				header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
				die();
			} else if ($_GET['state'] == 'cb_op_action_oauth' && $_GET['nonce'] == $_SESSION['cb_op_nonce']) {
				session_start();

				set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/includes');
			
				require_once( dirname(__FILE__) . '/includes/google-api-php-client/autoload.php');
				
				global $current_user;
				get_currentuserinfo();
				
				$client = new Google_Client();
				$client->setApplicationName("OP Contacts Proxy");
				$client->setAuthConfigFile( dirname(__FILE__) . '/includes/data/google_auth.json');
				$client->setRedirectUri('http://wpdemo.tronnet.me/');
				$client->addScope("https://www.google.com/m8/feeds");
				
				$client->authenticate($_GET['code']);
				
				$access_token = $client->getAccessToken();
				$access_token_decoded = json_decode($access_token, true);
				
				add_user_meta($current_user->ID, '_cb_op_google_code', $_GET['code']);
				add_user_meta($current_user->ID, '_cb_op_google_refresh_token', $access_token_decoded['refresh_token'] );
				add_user_meta($current_user->ID, '_cb_op_google_access_token', $access_token);
				
				var_dump($client->getAccessToken());
				die();
				
				// $redirect_uri = 'https://' . $_SERVER['HTTP_HOST'] . $APPPATH;
				// header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
			} else if ($_GET['cb_op_action_import_contact']) {
				session_start();

				set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/includes');
			
				require_once( dirname(__FILE__) . '/includes/google-api-php-client/autoload.php');
				
				global $current_user;
				get_currentuserinfo();
				
				$client = new Google_Client();
				$client->setApplicationName("OP Contacts Proxy");
				$client->setAuthConfigFile( dirname(__FILE__) . '/includes/data/google_auth.json');
				$client->setRedirectUri('http://wpdemo.tronnet.me/');
				$client->addScope("https://www.google.com/m8/feeds");
				
				$refresh_token = get_user_meta($current_user->ID, '_cb_op_google_refresh_token', true);

				$client->refreshToken($refresh_token);
				
				$ret = $this->_create_contact("Tron HammerMan", "8055555555", "thetron@tronet.me");
				
				var_dump($ret);
				
				die();
			}
			
		}
		
		public function save_data($key, $data){
			if ( get_option( $key ) !== false ) {
			    update_option( $key, $data );
			} else {
			    add_option( $key, $data );
			}
			
			return get_option( $key );
		}
		
		public function get_data($key){
			return get_option( $key );
		}
		
		public function myplugin_activate() {

		}
		
		public function myplugin_deactivate() {

		}
		
		
		private function _create_contact($name, $phoneNumber, $emailAddress) {
	        $doc = new DOMDocument();
	        $doc->formatOutput = true;
	        $entry = $doc->createElement('atom:entry');
	        $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', 'http://www.w3.org/2005/Atom');
	        $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005');
	        $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005');
	        $doc->appendChild($entry);

	        $title = $doc->createElement('title', $name);
	        $entry->appendChild($title);

	        $email = $doc->createElement('gd:email');
	        $email->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
	        $email->setAttribute('address', $emailAddress);
	        $entry->appendChild($email);

	        $contact = $doc->createElement('gd:phoneNumber', $phoneNumber);
	        $contact->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
	        $entry->appendChild($contact);

	        $xmlToSend = $doc->saveXML();

	        $req = new Google_Http_Request('https://www.google.com/m8/feeds/contacts/default/full');
	        $req->setRequestHeaders(array('content-type' => 'application/atom+xml; charset=UTF-8; type=feed'));
	        $req->setRequestMethod('POST');
	        $req->setPostBody($xmlToSend);

	        $val = $client->getAuth()->authenticatedRequest($req);

	        $response = $val->getResponseBody();

	        $xmlContact = simplexml_load_string($response);
	        $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

	        $xmlContactsEntry = $xmlContact;

	        $contactDetails = array();

	        $contactDetails['id'] = (string) $xmlContactsEntry->id;
	        $contactDetails['name'] = (string) $xmlContactsEntry->title;

	        foreach ($xmlContactsEntry->children() as $key => $value) {
	            $attributes = $value->attributes();

	            if ($key == 'link') {
	                if ($attributes['rel'] == 'edit') {
	                    $contactDetails['editURL'] = (string) $attributes['href'];
	                } elseif ($attributes['rel'] == 'self') {
	                    $contactDetails['selfURL'] = (string) $attributes['href'];
	                }
	            }
	        }

	        $contactGDNodes = $xmlContactsEntry->children('http://schemas.google.com/g/2005');

	        foreach ($contactGDNodes as $key => $value) {
	            $attributes = $value->attributes();

	            if ($key == 'email') {
	                $contactDetails[$key] = (string) $attributes['address'];
	            } else {
	                $contactDetails[$key] = (string) $value;
	            }
	        }
			
			return $contactDetails;
		}

	}
	
	
    register_activation_hook( __FILE__, array( 'OPContactProxy', 'myplugin_activate' ) );
    register_deactivation_hook( __FILE__, array( 'OPContactProxy', 'myplugin_deactivate' ) );
	
	add_action( 'plugins_loaded', array( 'OPContactProxy', 'get_instance' ) );
	
