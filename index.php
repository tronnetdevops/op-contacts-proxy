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
			
			if (isset($_GET['op_google_contact_integration'])){
				include( dirname(__FILE__) . '/templates/create-user.php' );
				die();
			} else if (isset($_GET['cb_op_action_oauth'])){
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
				
				$nonce = session_id();
				$_SESSION['cb_op_nonce'] = $nonce;
				$_SESSION[ $nonce ] = trim(strtolower($_REQUEST['owner']));
				
				$auth_url = $client->createAuthUrl();
				$auth_url .= '&state=cb_op_action_oauth_NONCE_'.$nonce;
				
				file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.'Got request to authorize: '.$_SESSION[ $nonce ].PHP_EOL, FILE_APPEND);
				file_put_contents( dirname(__FILE__) .'/update.txt', 'Using nonce: '.$nonce.PHP_EOL, FILE_APPEND);
								
				header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
				die();
			} else if (isset($_GET['state'])){
				$action = explode('_NONCE_', $_GET['state']);
				if ($action[0] == 'cb_op_action_oauth'){
					session_start();
					
					file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.PHP_EOL.'Got a nonce return: '.$_GET['state'].PHP_EOL, FILE_APPEND);
					file_put_contents( dirname(__FILE__) .'/update.txt', 'Comparing against: '.$_SESSION['cb_op_nonce'].PHP_EOL, FILE_APPEND);
					
					$nonce = $action[1];
			
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

						$owner = $_SESSION[ $nonce ];

						$dataKey = "cb_op_".$owner;
						$saveData = self::get_data($dataKey);
						
						$saveData['access_tokens'][ $_GET['code'] ] = array(
							'code' => $_GET['code'],
							'refresh_token' => $access_token_decoded['refresh_token'],
							'access_token' => $access_token
						);
						
						file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.'Adding in new access token to user data with key: '.$dataKey.PHP_EOL, FILE_APPEND);
						file_put_contents( dirname(__FILE__) .'/update.txt', var_export( $saveData,true).PHP_EOL, FILE_APPEND);
						
						self::save_data($dataKey, $saveData);
						
						include( dirname(__FILE__) . '/templates/create-user-success.php' );
						die();
					} else {
						echo "There was an error parsing your user nonce. Maybe the network experienced a timeout?";
						die();
					}
				}
			} else if ($_REQUEST['cb_op_action_import_contact'] && $_REQUEST['owner']) {
				file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.PHP_EOL."request recieved!".PHP_EOL, FILE_APPEND);
				session_start();
				
				file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.PHP_EOL.var_export($_REQUEST, true).PHP_EOL, FILE_APPEND);
				
				set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/includes');
			
				require_once( dirname(__FILE__) . '/includes/google-api-php-client/autoload.php');
				
				$owner = trim(strtolower($_REQUEST['owner']));
				$dataKey = "cb_op_".$owner;
				$saveData = self::get_data($dataKey);
				
				file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.PHP_EOL.'CURRENT SAVE DATA:'.PHP_EOL.var_export( $saveData,true).PHP_EOL, FILE_APPEND);
				
				$client = new Google_Client();
				$client->setApplicationName("OP Contacts Proxy");
				$client->setAuthConfigFile( dirname(__FILE__) . '/includes/data/google_auth.json');
				$client->setRedirectUri('http://wpdemo.tronnet.me/');
				$client->addScope("https://www.google.com/m8/feeds");
				
				foreach($saveData['access_tokens'] as $code=>$access_token){

					$refresh_token = $access_token['refresh_token'];
				
					try{
						$client->refreshToken($refresh_token);
					} catch (Exception $e){
						file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL."Issues refreshing token: " . $refresh_token.PHP_EOL, FILE_APPEND);
						continue;
					}
					
					$ret = OPContactProxy::_get_contact_groups($client);
				
					file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.var_export($ret, true).PHP_EOL, FILE_APPEND);
				
					die();
				
					$address = false;
					if ( isset($_REQUEST['address'])
								&& isset($_REQUEST['city'])
								&& isset($_REQUEST['state'])
								&& isset($_REQUEST['zip'])
								&& isset($_REQUEST['country'])){
						$address = $_REQUEST['address'] . (isset($_REQUEST['address2']) ? PHP_EOL.$_REQUEST['address2'] : '')
							. PHP_EOL . $_REQUEST['city'] . ', ' . $_REQUEST['state'] . ' ' .  $_REQUEST['zip'];
					}
				
					$comments = "Industry: ".$_REQUEST['industry'].PHP_EOL.PHP_EOL.$_REQUEST['notes'];
					$name = $_REQUEST['fname'] . (!empty($_REQUEST['mname']) ? " " . $_REQUEST['mname'] : "")." ".$_REQUEST['lname'];
					$email = $_REQUEST['email'];
				
					$ret = OPContactProxy::_create_contact($name, $_REQUEST['email'], $_REQUEST['pnum'], $_REQUEST['industry'], $address, $comments);
				
					file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.var_export($ret, true).PHP_EOL, FILE_APPEND);
				
					$saveData[ 'client' . $_REQUEST['cid'] ] = array(
						'email' => $email,
						'name' => $name,
						'id' => $ret['id']
					);
				}

				self::save_data("cb_op_emails_saved", $saveData);
				
				die();
			}
			
		}
		
		static public function save_data($key, $data){
			if ( get_option( $key ) !== false ) {
			    update_option( $key, $data );
			} else {
			    add_option( $key, $data );
			}
			
			return get_option( $key );
		}
		
		static public function get_data($key){
			return get_option( $key );
		}
		
		public function myplugin_activate() {

		}
		
		public function myplugin_deactivate() {

		}
		
		public static function xml2array($xml)
		{
		    $arr = array();
 
		    foreach ($xml->children() as $r)
		    {
		        $t = array();
		        if(count($r->children()) == 0)
		        {
		            $arr[$r->getName()] = strval($r);
		        }
		        else
		        {
		            $arr[$r->getName()][] = xml2array($r);
		        }
		    }
		    return $arr;
		}

		static private function _get_contact_groups($client) {
			
      $req = new Google_Http_Request('https://www.google.com/m8/feeds/groups/default/full');
      $req->setRequestHeaders(array('content-type' => 'application/atom+xml; charset=UTF-8; type=feed'));
      $req->setRequestMethod('GET');
			
			file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.'Making request', FILE_APPEND);
			
      $val = $client->getAuth()->authenticatedRequest($req);

			try{
      	$response = $val->getResponseBody();
			} catch (Exception $e){
				file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL."Error getting groups: " .PHP_EOL. var_export($e, true).PHP_EOL, FILE_APPEND);
				return;
			}
			
			file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.PHP_EOL.'===RESPONSE==='.PHP_EOL.$response.PHP_EOL.PHP_EOL, FILE_APPEND);
			
      $xmlContact = simplexml_load_string($response);
      $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');
			
			$groups = self::xml2array($xmlContact);
			
			file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.PHP_EOL.'>>>>PARSED<<<<'.PHP_EOL.var_export($groups, true).PHP_EOL.PHP_EOL, FILE_APPEND);
			
		}
		
		static private function _create_contact($client, $name, $emailAddress, $phoneNumber, $address) {
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
					
					if ($address){
						$postalAddress = $doc->createElement('gd:postalAddress', $address);
						$contact->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
		        $entry->appendChild($postalAddress);					
					}
					
	        $industry = $doc->createElement('gd:extendedProperty');
	        $industry->setAttribute('name', 'industry');
	        $industry->setAttribute('value', 'coolInc');
		      $entry->appendChild($industry);
			
		      $industry = $doc->createElement('gd:organization');
					$industry->setAttribute('label', 'Industry');
					$industry->setAttribute('primary', 'true');

		      $orgName = $doc->createElement('gd:orgName', 'Some Industry');
	        $industry->appendChild($orgName);
		      $orgName = $doc->createElement('gd:orgTitle', 'Some Title');
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
	
