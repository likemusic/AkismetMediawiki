<?php

	/**
	* Class for converting Mediawiki Objects to Parameters for Akismet server
	*/
	class AkismetMediawiki
	{
		const CommentType = 'wiki';

		/**
		* Plugin version.
		* Used in http user agent header field.
		* 
		* @var string|numeric
		*/
		protected $PluginVersion = '0.1b';

		/**
		* Class for interact with Akismet server
		* 
		* @var AkismetService
		*/
		protected $AkismetService;
		
		/**
		* Used for select function depends on curent mediawiki version
		* 
		* @var bool
		*/
		protected $IsVersionEqualOrGreater_1_21;

		protected static $instance;  //object instance

		// The protected construct prevents instantiating the class externally
		private function __construct() {
			global $wgVersion,  $wgCanonicalServer, $wgAkismetApiKey;
			$HttpUserAgent = 'Mediawiki/'.$wgVersion.' | '.__CLASS__.'/'.$this->PluginVersion;
			$this->AkismetService = new AkismetService( $wgAkismetApiKey,  $HttpUserAgent, $wgCanonicalServer );
			$this->IsVersionEqualOrGreater_1_21 = version_compare( $wgVersion, '1.21', '>=' );
		} 

		// The clone and wakeup methods prevents external instantiation of copies of the Singleton class
		private function __clone() { }  
		private function __wakeup() { } 

		/**
		* return an instance of the object
		* @return self
		*/
		public static function getInstance() { 
			if ( is_null(self::$instance) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public function isSpam( EditPage $editor, $text, User $user ) {
			$Permalink = $editor->getTitle()->getCanonicalURL();
			$UserIp = $user->getRequest()->getIP();
			$Referrer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : null ;
			$UserAgent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null;

			$AkismetParams = $this->getAkismetParams( $text, $Permalink, $UserIp, $user, $Referrer, $UserAgent );
			$AkismetService = $this->AkismetService;
			$IsSpam = $AkismetService->checkComment( $AkismetParams );
			return $IsSpam;
		}

		public function submitSpam( WikiPage &$WikiPage ) {
			if ( $this->IsVersionEqualOrGreater_1_21 ) {
				$CommentContent = $WikiPage->getContent();
			} else {
				$CommentContent = $WikiPage->getText();
			}
			//$CommentContent = $WikiPage->getRawText();
			//$CommentContent = $WikiPage->getParserOutput();
			//$CommentContent = $WikiPage->prepareTextForEdit( $text );

			$Permalink = $WikiPage->getTitle()->getCanonicalURL();
			$this->getUserInfo( $WikiPage, $User, $UserIp );
			$AkismetParams = $this->getAkismetParams( $CommentContent, $Permalink, $UserIp, $User );
			return $this->AkismetService->submitSpam( $AkismetParams );
		}

		protected function getUserInfo( $WikiPage, &$User, &$UserIp ) {
			global $wgAkismetAuthorIsCreator;
			/**
			* Revision
			* 
			* @var Revision
			*/
			$Revision = null;

			if( $wgAkismetAuthorIsCreator ) {
				$Revision = $WikiPage->getOldestRevision();
			}
			else {
				$Revision = $WikiPage->getRevision();
			}

			$UserId = $Revision->getUser();
			if ( $UserId == 0) {
				$User = null;
				$UserIp = $Revision->getUserText();

			} else {
				$User = User::newFromId( $UserId );
				$UserIp = $this->getUserIp( $UserId );
			}
		}

		/**
		* Get Params for AkismetService object methods
		* 
		* @param string $CommentContent
		* @param string $Permalink The permanent location of the entry the comment was submitted to.
		* @param string $UserIp - IP address of the comment submitter.
		* @param User $User
		* @param string $Referrer The content of the HTTP_REFERER header should be sent here.
		* @param string $UserAgent User agent string of the web browser submitting the comment.
		* Typically the HTTP_USER_AGENT cgi variable.
		* @return AkismetComment
		*/
		protected function getAkismetParams( $CommentContent, $Permalink, $UserIp=null, User $User=null,
			$Referrer = null, $UserAgent = null ) {

			$Params = new AkismetComment();
			if( $UserIp !== null ) {
				$Params->user_ip = $UserIp;
			}

			if ( $UserAgent !== null ) {
				$Params->user_agent = $UserAgent;
			}	

			if ( $Referrer !== null ) {
				$Params->refferer = $UserAgent;
			}

			$Params->permalink = $Permalink;

			/**
			* May be blank, comment, trackback, pingback, or a made up value like "registration".
			* 
			* @var string
			*/
			$Params->comment_type = self::CommentType;

			if( $User !== null ) {
				if( !$User->isAnon() ) {
					/**
					* Name submitted with the comment
					* 
					* @var string
					*/
					$Params->comment_author = $User->getName(); //or getRealName() ???

					/**
					* Email address submitted with the comment
					* 
					* @var string
					*/
					$Params->comment_author_email = $User->getEmail();

					/**
					* URL submitted with comment
					* 
					* @var string
					*/
					$Params->comment_author_url = $User->getUserPage()->getCanonicalURL();
				}
			}

			$Params->comment_content = $CommentContent;
			/**
			$Params = array(
			//'blog' => $blog,//not set - in constructor
			'user_ip' => $user_ip, // from current request | or from recent changes table for seleted user
			'user_agent' => $user_agent, //from current request or none
			'referrer' => $referrer, //from current request or none
			'permalink' => $permalink, //from PageTitle
			'comment_type' => $comment_type, //const
			'comment_author' => $comment_author,
			'comment_author_email' => $comment_author_email,
			'comment_author_url' => $comment_author_url,
			'comment_content' => $comment_content
			);
			*/
			return $Params;
		}

		protected function getUserIp( $UserId ) {
			$dbr = wfGetDB( DB_SLAVE );
			$DbIdentifier = $dbr->addIdentifierQuotes('rc_user');
			$DbIdentifierValue = $dbr->addQuotes( $UserId );
			
			$Cond = $DbIdentifier.' = '.$DbIdentifierValue;
			$RecentChange = RecentChange::newFromConds( $Cond );
			if( $RecentChange !== null ) {
				$UserIp = $RecentChange->getAttribute('rc_ip');
			}
			else {
				$UserIp = null;
			}
			return $UserIp;
		}
	}
?>