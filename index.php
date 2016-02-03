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
			
			if (isset($_GET['cb_op_action_oauth'])){
				session_start();

				set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/includes');
			
				require_once( dirname(__FILE__) . '/includes/google-api-php-client/autoload.php');
				
				$client = new Google_Client();
				$client->setApplicationName("OP Contacts Proxy");
				$client->setAuthConfigFile( dirname(__FILE__) . '/includes/data/google_auth.json');
			
				$client->addScope("https://www.google.com/m8/feeds");
			
				if (! isset($_GET['code'])) {
					$nonce = uniqid();
					$_SESSION['cb_op_nonce'] = $nonce;
					
					$client->setRedirectUri('http://wpdemo.tronnet.me/');
					
					$auth_url = $client->createAuthUrl();
					$auth_url .= '&state=cb_op_action_oauth';
					$auth_url .= '&nonce='.$nonce;
					
					header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
					die();
				}
			} else if ($_GET['state'] == 'cb_op_action_oauth' && $_GET['nonce'] == $_SESSION['cb_op_nonce']) {
				session_start();

				set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/includes');
			
				require_once( dirname(__FILE__) . '/includes/google-api-php-client/autoload.php');
				
				global $current_user;
				get_currentuserinfo();
				
				$client = new Google_Client();
				$client->setApplicationName("OP Contacts Proxy");
				$client->setAuthConfigFile( dirname(__FILE__) . '/includes/data/google_auth.json');
				
				$client->authenticate($_GET['code']);
				
				$client->addScope("https://www.google.com/m8/feeds");
				
				add_user_meta($current_user->ID, '_cb_op_google_code', $_GET['code']);
				add_user_meta($current_user->ID, '_cb_op_google_access_token', $client->getAccessToken());
				
				var_dump($client->getAccessToken());
				die();
					
				// $redirect_uri = 'https://' . $_SERVER['HTTP_HOST'] . $APPPATH;
				// header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
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
		

	}
	
	
    register_activation_hook( __FILE__, array( 'OPContactProxy', 'myplugin_activate' ) );
    register_deactivation_hook( __FILE__, array( 'OPContactProxy', 'myplugin_deactivate' ) );
	
	add_action( 'plugins_loaded', array( 'OPContactProxy', 'get_instance' ) );
	
