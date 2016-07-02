<?php

	/**
	* Class with hook funcitons
	*/
	class AkismetHooks
	{
		const BYPASS_RIGHT_NAME = 'bypassakismet';
		/**
		Currently dont understand when when $title is Upper or Lower first char - check both
		@todo check algorithn "Upper or Lower first char"
		*/
		/** @const */
		protected static $DeleteReasonMessageKeys = array(
			'deletereason-dropdown',
			'Deletereason-dropdown'
		);

		/**
		* Hook function for MessagesPreLoad
		* 
		* @param string $title title of the message (string)
		* @param string $message value (string), change it to the message you want to define
		* @return bool
		*/
		public static function onMessagesPreLoad( $title, &$message ) {
			global $wgAkismetAddDeleteReason;

			if( !$wgAkismetAddDeleteReason ) {
				return true;	
			}

			if(self::IsDeleteReasonMessage( $title ))
			{
				self::AddDeleteReasonItem( $title, $message);
			}

			return true;
		}

		static protected function IsDeleteReasonMessage( $title ) {
			if ( in_array( $title, self::$DeleteReasonMessageKeys) ) {
				return true;
			} else {
				return false;
			}
		}

		static protected function AddDeleteReasonItem( $title, &$message ) {
			global $wgLang;

			$OldValue = $wgLang->getMessage( $title );
			$NewMessageItem = wfMessage( 'delete-reason' )->plain();	
			$NewValue = $OldValue."\n**".$NewMessageItem;
			$message = $NewValue;
		}

		/**
		* Hook function for ArticleDelete
		* 
		* @param WikiPage|Article $article the article that was deleted. WikiPage in MW >= 1.18, Article in 1.17.x and earlier.
		* @param User $user the user (object) deleting the article
		* @param string $reason the reason (string) the article is being deleted
		* @param string $error if the requested article deletion was prohibited, the (raw HTML) error message to display (added in 1.13)
		* @return bool
		*/
		public static function onArticleDelete( WikiPage &$article, User &$user, &$reason, &$error ) {
			global $wgAkismetAddDeleteReason;
			wfProfileIn( __METHOD__ );
			
			if ( !$wgAkismetAddDeleteReason ) {
				wfProfileOut( __METHOD__ );
				return true;
			} 

			$ret = true;
			if ( self::IsAkismetReason( $reason ) )
			{
				try
				{
					$AkismetMediawiki = AkismetMediawiki::getInstance();
					$AkismetMediawiki->submitSpam( $article );
				}
				catch(Exception $e)
				{
					$ret = false;
					$error = $e->getMessage('error-on-submit-spam');
				}
			}
			wfProfileOut( __METHOD__ );
			return $ret;
		}

		protected static function IsAkismetReason( $reason ) {
			$SelectedReason = self::GetSelectedReason( $reason );
			$AkismetMessageItem = wfMessage( 'delete-reason' )->plain();	
			if( $SelectedReason == $AkismetMessageItem ) {
				return true;
			}
			else {
				return false;
			}
		}

		protected static function GetSelectedReason( $reason ) {
			/*
			Reason format
			[reason:] content: «Page content» (bla-bla)
			No [reason:] if selected first item "*Common delete reasons"
			*/
			$Pos = mb_strpos($reason, '«');
			$Substr = mb_substr($reason,0,$Pos);
			$Substr = trim($Substr);
			$Pos = mb_strpos($reason, ':');

			if( $Pos == ( mb_strlen( $Substr ) - 1 ) ) {
				$SelectedReason = null;	
			} else {
				$SelectedReason = mb_substr($Substr,0,$Pos);
			}
			return $SelectedReason;
		}

		/**
		* Hook function for EditFilter
		* 
		* @param EditPage $editor EditPage instance (object)
		* @param string $text Contents of the edit box
		* @param string $section Section number being edited
		* @param string $error  Error message to return
		* @param string $summary edit summary provided for edit
		*/
		public static function onEditFilter( EditPage $editor, $text, $section, &$error, $summary ) {
			global $wgAkismetEnableEditFilter, $wgUser;
			wfProfileIn( __METHOD__ );
			if ( (!$wgAkismetEnableEditFilter) || $wgUser->isAllowed( self::BYPASS_RIGHT_NAME ) ) {
				wfProfileOut( __METHOD__ );
				return true;
			}

			try {
				$ret = self::DetectSpam( $editor, $text, $wgUser, $error );
			}
			catch(Exception $e) {
				$ret = false;
				$error = $e->getMessage('error-on-check-edit');
			}
			wfProfileOut( __METHOD__ );
			return $ret;
		}

		/**
		* Hook function for EditFilterMerged
		* 
		* @param EditPage $editor EditPage instance (object)
		* @param string $text content of the revised article
		* @param string $error error message to return (wikitext)
		* @param string $summary
		* @return bool
		*/
		public static function onEditFilterMerged(EditPage $editor, $text, &$error, $summary ) {
			global $wgAkismetEnableEditFilterMerged, $wgUser;
			wfProfileIn( __METHOD__ );
			if ( ( !$wgAkismetEnableEditFilterMerged ) || $wgUser->isAllowed( self::BYPASS_RIGHT_NAME ) ) {
				wfProfileOut( __METHOD__ );
				return true;
			}
			
			try {
				$ret = self::DetectSpam( $editor, $text, $wgUser, $error );
			}
			catch(Exception $e) {
				$ret = false;
				$error = $e->getMessage('error-on-check-edit');
			}
			wfProfileOut( __METHOD__ );
			return $ret;
		}

		static protected function DetectSpam( &$editor, &$text, &$wgUser, &$error ) {
			global $wgAkismetOnSpamShowEditor, $wgAkismetOnSpamShowWikiMessage;

			$AkismetMediaWiki = AkismetMediawiki::getInstance();
			$IsSpam = $AkismetMediaWiki->isSpam( $editor, $text, $wgUser);
			if ( $IsSpam  ) {
				if( $wgAkismetOnSpamShowEditor ) {
					if ( $wgAkismetOnSpamShowWikiMessage ) {
						$error = wfMessage( 'spam-detected' )->text();
					}
					else {
						$error = wfMessage( 'spamprotectiontext' )->text();
					}
					$ret = true;	
				}
				else {
					$editor->spamPageWithContent();
					$ret=false;
				}
			}
			else {
				$ret = true;	
			}
			return $ret;;
		}
	}

?>
