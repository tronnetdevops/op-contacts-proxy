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
		
		}
		
		public function check_for_requests(){
			
			if (isset($_GET['cb_op_action_oauth'])){
				global $current_user;
				get_currentuserinfo();
				
				if (!($current_user instanceof WP_User)){
					echo "Please <a href='/wp-login.php'>login</a> before attempting to authenticate to google!";
					die();
				}
				
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
				
				$nonce = session_id() .'_UID_'. $current_user->ID;
				$_SESSION['cb_op_nonce'] = $nonce;
				
				$auth_url = $client->createAuthUrl();
				$auth_url .= '&state=cb_op_action_oauth_NONCE_'.$nonce;
				
				header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
				die();
			} else if (isset($_GET['state'])){
				$action = explode('_NONCE_', $_GET['state']);
				if ($action[0] == 'cb_op_action_oauth'){
					$nonce = $action[1];
					
					$parsedNonce = explode('_UID_', $nonce);
			
					session_start($parsedNonce[0]);
					$user_id = $parsedNonce[1];
			
					if ($nonce == $_SESSION['cb_op_nonce']) {
						set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/includes');
		
						require_once( dirname(__FILE__) . '/includes/google-api-php-client/autoload.php');
			
						$client = new Google_Client();
						$client->setApplicationName("OP Contacts Proxy");
						$client->setAuthConfigFile( dirname(__FILE__) . '/includes/data/google_auth.json');
						$client->setRedirectUri('http://wpdemo.tronnet.me/');
						$client->addScope("https://www.google.com/m8/feeds");
			
						$client->authenticate($_GET['code']);
			
						$access_token = $client->getAccessToken();
						$access_token_decoded = json_decode($access_token, true);
			
						update_user_meta($user_id, '_cb_op_google_code', $_GET['code']);
						update_user_meta($user_id, '_cb_op_google_refresh_token', $access_token_decoded['refresh_token'] );
						update_user_meta($user_id, '_cb_op_google_access_token', $access_token);
					}
				}
			} else if ($_REQUEST['cb_op_action_import_contact'] && $_REQUEST['user_id']) {
				// echo dirname(__FILE__) .'/update.txt';
				file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.PHP_EOL."request recieved!".PHP_EOL, FILE_APPEND);
				session_start();
				
				
				file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.PHP_EOL.var_export($_REQUEST, true).PHP_EOL, FILE_APPEND);
				
				
				set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/includes');
			
				require_once( dirname(__FILE__) . '/includes/google-api-php-client/autoload.php');
				
				$user_id = $_REQUEST['user_id'];
				$currentData = self::get_data("cb_op_emails_saved");
				$email = $_REQUEST['email'];
				
				if (!isset($email) || empty($email) || in_array($email, $currentData)){
					file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.'!!Duplicate!!', FILE_APPEND);
					
					die();
				}
				
				$client = new Google_Client();
				$client->setApplicationName("OP Contacts Proxy");
				$client->setAuthConfigFile( dirname(__FILE__) . '/includes/data/google_auth.json');
				$client->setRedirectUri('http://wpdemo.tronnet.me/');
				$client->addScope("https://www.google.com/m8/feeds");
				
				$refresh_token = get_user_meta($user_id, '_cb_op_google_refresh_token', true);
				
				try{
					$client->refreshToken($refresh_token);
					
				} catch (Exception $e){
					file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL."Issues refreshing token: " . $refresh_token.PHP_EOL, FILE_APPEND);
					
					die();
				}
				
				$ret = OPContactProxy::_create_contact($client, $_REQUEST['fname']." ".$_REQUEST['lname'], $_REQUEST['pnum'], $_REQUEST['email']);
				
				file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.var_export($ret, true).PHP_EOL, FILE_APPEND);
				
				$currentData[] = $email;
				
				self::save_data("cb_op_emails_saved", $currentData);
				
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
		
		static private function _create_contact($client, $name, $phoneNumber, $emailAddress) {
	        $doc = new DOMDocument();
	        $doc->formatOutput = true;
	        $entry = $doc->createElement('atom:entry');
	        $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', 'http://www.w3.org/2005/Atom');
	        $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005');
	        $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005');
	        $doc->appendChild($entry);

	        $title = $doc->createElement('title', $name);
	        $entry->appendChild($title);
					
	        $content = $doc->createElement('content', 'some content right here');
	        $entry->appendChild($content);
					
	        $email = $doc->createElement('gd:email');
	        $email->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
	        $email->setAttribute('address', $emailAddress);
	        $entry->appendChild($email);
			
					if ($phoneNumber){
						$contact = $doc->createElement('gd:phoneNumber', $phoneNumber);
						$contact->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
		        $entry->appendChild($contact);
					}
			
		      $industry = $doc->createElement('gd:organization');
					$industry->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
					$industry->setAttribute('label', 'Work');
					$industry->setAttribute('primary', 'true');

		      $orgName = $doc->createElement('gd:orgName', 'Testo Name');
	        $industry->appendChild($orgName);
		      $orgName = $doc->createElement('gd:orgTitle', 'Some Title');
	        $industry->appendChild($orgName);
		      $orgName = $doc->createElement('gd:orgDepartment', 'Industry');
	        $industry->appendChild($orgName);
					
		      $entry->appendChild($industry);

	        $xmlToSend = $doc->saveXML();
					file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.'Request XML'.PHP_EOL.$xmlToSend.PHP_EOL, FILE_APPEND);
					
					file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.'Priming request header', FILE_APPEND);
					

	        $req = new Google_Http_Request('https://www.google.com/m8/feeds/contacts/default/full');
	        $req->setRequestHeaders(array('content-type' => 'application/atom+xml; charset=UTF-8; type=feed'));
	        $req->setRequestMethod('POST');
	        $req->setPostBody($xmlToSend);
					
					file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.'Making request', FILE_APPEND);
					
	        $val = $client->getAuth()->authenticatedRequest($req);

	        $response = $val->getResponseBody();
					
					file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.PHP_EOL.'===RESPONSE==='.PHP_EOL.$response.PHP_EOL.PHP_EOL, FILE_APPEND);
					

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
	add_action( 'wp_loaded', array( 'OPContactProxy', 'check_for_requests' ) );
	
