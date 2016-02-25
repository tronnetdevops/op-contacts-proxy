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
				$clientKey = $code.'-'.$_REQUEST['cid'];
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
					
					$groups = OPContactProxy::_get_contact_groups($client);
					
					$industryGroup = false;
					
					if (isset($_REQUEST['industry'])){
						foreach($groups['entry'] as $group){
							if (strpos($group['title'], $_REQUEST['industry']) !== false){
								$industryGroup = $group;
							}
						}
					}
						
					if (!$industryGroup){						
						$industryGroup = OPContactProxy::_create_contact_group($client, $_REQUEST['industry']);
					}
					
					file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.'Found matching Industry Group: '.var_export($industryGroup, true).PHP_EOL, FILE_APPEND);
				
					$address = false;
					if ( isset($_REQUEST['address'])
								&& isset($_REQUEST['city'])
								&& isset($_REQUEST['state'])
								&& isset($_REQUEST['zip'])
								&& isset($_REQUEST['country'])){
						$address = $_REQUEST['address'] . (isset($_REQUEST['address2']) ? PHP_EOL.$_REQUEST['address2'] : '')
							. PHP_EOL . $_REQUEST['city'] . ', ' . $_REQUEST['state'] . ' ' .  $_REQUEST['zip'];
					}
				
					$name = $_REQUEST['fname'] . (!empty($_REQUEST['mname']) ? " " . $_REQUEST['mname'] : "")." ".$_REQUEST['lname'];
					$email = $_REQUEST['email'];
					$phone = $_REQUEST['pnum'];
					$company = $_REQUEST['company'];
					$title = $_REQUEST['title'];
					$referral = $_REQUEST['referral'];
					$manager = $_REQUEST['manager'];
					$url = $_REQUEST['url'];
					$birthday = date('Y-m-d', strtotime($_REQUEST['birthday']) );
					$comments = "";
						
					if (!empty($_REQUEST['industry'])){
						$comments .= "Industry: ".$_REQUEST['industry'];
					}
					if (!empty($manager)){
						$comments .= PHP_EOL."Manager: ".$manager;
					}
					if (!empty($referral)){
						$comments .= PHP_EOL."Referral: ".$referral;
					}
					if (!empty($_REQUEST['notes'])){
						$comments .= PHP_EOL.PHP_EOL.$_REQUEST['notes'];
					}
					
					$existing = $saveData['contacts'][ $clientKey ];
					if (is_array($existing)){
						$cid = $existing['id'];
						$id = $existing['fullId'];
						
						$ret = OPContactProxy::_create_contact($client, $cid, $id, $name, $email, $phone, $industryGroup, $address, $comments, $company, $title, $birthday, $url, $referral, $manager);
						
						file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.var_export($ret, true).PHP_EOL, FILE_APPEND);
						
					} else {
						$cid = false;
						$id = false;
						
						$ret = OPContactProxy::_create_contact($client, $cid, $id, $name, $email, $phone, $industryGroup, $address, $comments, $company, $title, $birthday, $url, $referral, $manager);
			
						file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.var_export($ret, true).PHP_EOL, FILE_APPEND);
			
						$parsedId = explode('/', $ret['id']);
						$parsedId = $parsedId[ count($parsedId) - 1 ];
						
						$saveData = self::get_data($dataKey);
						
						$saveData['contacts'][ $clientKey ] = array(
							'code' => $code,
							'cid' => $_REQUEST['cid'],
							'data' => $ret,
							'fullId' => $ret['id'],
							'id' => $parsedId
						);
						
						file_put_contents( dirname(__FILE__) .'/update.txt', 'Updating SAVE DATA: '.PHP_EOL.var_export($saveData, true).PHP_EOL, FILE_APPEND);
						
						
						self::save_data($dataKey, $saveData);
					}
					
					file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.'Cool that handles that contact!'.PHP_EOL, FILE_APPEND);
					
				}

				file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.'All accounts have been updated!'.PHP_EOL, FILE_APPEND);
				
				
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
		
		static public function xml2array($xml)
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
		            $arr[$r->getName()][] = OPContactProxy::xml2array($r);
		        }
		    }
		    return $arr;
		}
		
		static private function _get_contact($client, $cid) {
			
      $req = new Google_Http_Request('https://www.google.com/m8/feeds/contacts/default/'.$cid);
      $req->setRequestHeaders(array('content-type' => 'application/atom+xml; charset=UTF-8; type=feed'));
      $req->setRequestMethod('GET');
			
			file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.'Making request', FILE_APPEND);
			
      $val = $client->getAuth()->authenticatedRequest($req);

			try{
      	$response = $val->getResponseBody();
			} catch (Exception $e){
				file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL."Error getting contact: " .PHP_EOL. var_export($e, true).PHP_EOL, FILE_APPEND);
				return;
			}
			
			file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.PHP_EOL.'===RESPONSE==='.PHP_EOL.$response.PHP_EOL.PHP_EOL, FILE_APPEND);
			
      $xmlContact = simplexml_load_string($response);
      $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');
			
			$contact = OPContactProxy::xml2array($xmlContact);
			
			file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.PHP_EOL.'>>>>PARSED<<<<'.PHP_EOL.var_export($contact, true).PHP_EOL.PHP_EOL, FILE_APPEND);
			
			return $contact;
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
			
			$groups = OPContactProxy::xml2array($xmlContact);
			
			file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.PHP_EOL.'>>>>PARSED<<<<'.PHP_EOL.var_export($groups, true).PHP_EOL.PHP_EOL, FILE_APPEND);
			
			return $groups;
		}
		
		static private function _create_contact_group($client, $name) {
			
			file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.'No matching industry group, making a new one: '.PHP_EOL, FILE_APPEND);
			
      $doc = new DOMDocument();
      $doc->formatOutput = true;
      $entry = $doc->createElement('atom:entry');
      $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', 'http://www.w3.org/2005/Atom');
      $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005');
      $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005');
      $doc->appendChild($entry);

      $title = $doc->createElement('title', $name);
      $entry->appendChild($title);
		
			
      $opFlag = $doc->createElement('gd:extendedProperty');
      $opFlag->setAttribute('name', 'op-generated');
      $opFlag->setAttribute('value', 'true');
      $entry->appendChild($opFlag);

      $xmlToSend = $doc->saveXML();
			file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.'Request XML'.PHP_EOL.$xmlToSend.PHP_EOL, FILE_APPEND);
			
			file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.'Priming request header', FILE_APPEND);

      $req = new Google_Http_Request('https://www.google.com/m8/feeds/groups/default/full');
      $req->setRequestHeaders(array('content-type' => 'application/atom+xml; charset=UTF-8; type=feed'));
      $req->setRequestMethod('POST');
      $req->setPostBody($xmlToSend);
			
			file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.'Making request', FILE_APPEND);
			
      $val = $client->getAuth()->authenticatedRequest($req);

      $response = $val->getResponseBody();
			
			file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.PHP_EOL.'===RESPONSE==='.PHP_EOL.$response.PHP_EOL.PHP_EOL, FILE_APPEND);
			

      $xmlContact = simplexml_load_string($response);
      $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

			return OPContactProxy::xml2array($xmlContact);
		}
		
		static private function _create_contact($client, $cid, $id, $name, $emailAddress, $phoneNumber, $industryGroup, $address, $comments, $company, $title, $birthday, $url, $referral, $manager) {
      $doc = new DOMDocument();
      $doc->formatOutput = true;
      $entry = $doc->createElement('atom:entry');
      $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', 'http://www.w3.org/2005/Atom');
      $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005');
      $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005');
      $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gContact', 'http://schemas.google.com/contact/2008');
      $doc->appendChild($entry);
			
			if ($id !== false){
				$idDom = $doc->createElement('id', $id);
	      $entry->appendChild($idDom);
			}
			
			$categoryDom = $doc->createElement('category');
      $categoryDom->setAttribute('term', 'user-tag');
      $categoryDom->setAttribute('label', $industryGroup['title']);
      $entry->appendChild($categoryDom);
			
      $titleDom = $doc->createElement('title', $name);
      $entry->appendChild($titleDom);
			
      $contentDom = $doc->createElement('content', $comments);
      $entry->appendChild($contentDom);
			
      $emailDom = $doc->createElement('gd:email');
      $emailDom->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
      $emailDom->setAttribute('address', $emailAddress);
      $entry->appendChild($emailDom);
	
			if (!empty($phoneNumber)){
				$phoneNumberDom = $doc->createElement('gd:phoneNumber', $phoneNumber);
				$phoneNumberDom->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
        $entry->appendChild($phoneNumberDom);
			}
			
			if (!empty($address)){
				$postalAddressDom = $doc->createElement('gd:postalAddress', $address);
				$postalAddressDom->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
				$postalAddressDom->setAttribute('primary', 'true');
        $entry->appendChild($postalAddressDom);					
			}
						
			if (!empty($referral)){
	      $referralDom = $doc->createElement('gd:extendedProperty');
	      $referralDom->setAttribute('name', 'referral');
	      $referralDom->setAttribute('value', $referral);
	      $entry->appendChild($referralDom);
			}

			if (!empty($manager)){
	      $managerDom = $doc->createElement('gd:extendedProperty');
	      $managerDom->setAttribute('name', 'manager');
	      $managerDom->setAttribute('value', $manager);
	      $entry->appendChild($managerDom);
			}
			
			if (!empty($birthday)){
	      $birthdayDom = $doc->createElement('gContact:birthday');
	      $birthdayDom->setAttribute('when', $birthday);
	      $entry->appendChild($birthdayDom);
			}
			
      $birthdayDom = $doc->createElement('gContact:relation');
      $birthdayDom->setAttribute('label', 'Ontraport Contact');
      $entry->appendChild($birthdayDom);
			
			if (!empty($url)){
	      $websiteDom = $doc->createElement('gContact:website');
	      $websiteDom->setAttribute('href', $url);
				$websiteDom->setAttribute('rel', 'http://schemas.google.com/g/2005#profile');
				$websiteDom->setAttribute('primary', 'true');
	      $entry->appendChild($websiteDom);
			}
			
			if (!empty($company) || !empty($title)){
	      $orgDom = $doc->createElement('gd:organization');
				$orgDom->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
				$orgDom->setAttribute('primary', 'true');
			
				if (isset($company) && !empty($company)){
					file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.'Company: '.PHP_EOL.var_export($company, true).PHP_EOL, FILE_APPEND);
		      $orgNameDom = $doc->createElement('gd:orgName', $company);
		      $orgDom->appendChild($orgNameDom);
				}
				if (isset($title) && !empty($title)){
					file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.'Title: '.PHP_EOL.var_export($title, true).PHP_EOL, FILE_APPEND);
		      $orgTitleDom = $doc->createElement('gd:orgTitle', $title);
		      $orgDom->appendChild($orgTitleDom);
				}
			
	      $entry->appendChild($orgDom);
			}
			
			$groupMemDom = $doc->createElement('gContact:groupMembershipInfo');
      $groupMemDom->setAttribute('href', $industryGroup['id']);
      $groupMemDom->setAttribute('deleted', 'false');
      $entry->appendChild($groupMemDom);
			
      $xmlToSend = $doc->saveXML();
			file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.'Request XML'.PHP_EOL.$xmlToSend.PHP_EOL, FILE_APPEND);
			
			
			if ($cid !== false){
				file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.'%%%% Priming UPDATE request header with CID: ' . $cid, FILE_APPEND);
				
	      $req = new Google_Http_Request('https://www.google.com/m8/feeds/contacts/default/full/'.$cid);
				
	      $req->setRequestHeaders(array('content-type' => 'application/atom+xml; charset=UTF-8; type=feed'));
	      $req->setRequestMethod('PUT');
	      $req->setPostBody($xmlToSend);
			} else {
				file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.'++++ Priming CREATE request header', FILE_APPEND);
	      $req = new Google_Http_Request('https://www.google.com/m8/feeds/contacts/default/full');
				
	      $req->setRequestHeaders(array('content-type' => 'application/atom+xml; charset=UTF-8; type=feed'));
	      $req->setRequestMethod('POST');
	      $req->setPostBody($xmlToSend);
			}

			
			file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.'Making request', FILE_APPEND);
			
      $val = $client->getAuth()->authenticatedRequest($req);

      $response = $val->getResponseBody();
			
			file_put_contents( dirname(__FILE__) .'/update.txt', PHP_EOL.PHP_EOL.'===RESPONSE==='.PHP_EOL.$response.PHP_EOL.PHP_EOL, FILE_APPEND);
			
      $xmlContact = simplexml_load_string($response);
      $xmlContact->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

			return OPContactProxy::xml2array($xmlContact);
		}

	}
	
	
    register_activation_hook( __FILE__, array( 'OPContactProxy', 'myplugin_activate' ) );
    register_deactivation_hook( __FILE__, array( 'OPContactProxy', 'myplugin_deactivate' ) );
	
	add_action( 'plugins_loaded', array( 'OPContactProxy', 'get_instance' ) );
	add_action( 'wp_loaded', array( 'OPContactProxy', 'check_for_requests' ) );
	
