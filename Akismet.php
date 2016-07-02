<?php
	error_reporting(E_ALL);
	ini_set('display_errors', 1);

	/**
	* Akismet - antispam extention for Mediawiki
	* 
	* @version 0.1
	* @author Valerij Ivashchenko <likemusic@yandex.ru>
	* @link http://www.mediawiki.org/wiki/Extension:Akismet Documentation
	*/

	# To activate this extension, add the following into your LocalSettings.php file:
	# require_once("$IP/extensions/Akismet/Akismet.php");
	# $wgAkismetApiKey = 'c6de7b25471b';
	#
	# Optional set $wgAkismetAddDeleteReason and $wgAkismetEnableEditFilterMerged values:
	# $wgAkismetAddDeleteReason = false;
	# $wgAkismetEnableEditFilterMerged = true;

	/**
	* Options:
	* 
	* $wgAkismetApiKey
	*   - Api key given on http://akismet.com/
	* $wgAkismetAddDeleteReason
	*   - Add "Spam" item in delete reason select box. Default is true.
	* $wgAkismetEnableEditFilter
	*   - enable checking edits by Akismet service. Default is true.
	* $wgAkismetEnableEditFilterMerged
	*   - enable checking edits by Akismet service. Default is false.
	**/

	//standart block
	if ( !defined( 'MEDIAWIKI' ) ) {
		die( "This is not a valid access point.\n" );
	}

	$wgAkismetApiKey = 'c6de7b25471b'; //$Ключ Api полученный на akismet.com

	if ( !isset($wgAkismetAddDeleteReason ) ) {
		$wgAkismetAddDeleteReason = true; //Add "Spam Akismet" reason on delete page
	}

	if ( !isset($wgAkismetEnableEditFilterMerged ) ) {
		$wgAkismetEnableEditFilterMerged = true; //check edits by akismet server
	}
	if ( !isset($wgAkismetEnableEditFilter ) ) {
		$wgAkismetEnableEditFilter = true; //check edits by akismet server
	}

	//Constants
	define( 'AKISMET_EXT_DIR', dirname( __FILE__ ) );
	define( 'AKISMET_EXT_NAME', 'Akismet' );
	define( 'AKISMET_EXT_VERSION', 0.1 );

	#Extension credits
	$wgExtensionCredits['antispam'][] = array(
		'path' => __FILE__, // File name for the extension itself, required for getting the revision number from SVN - string, adding in 1.15
		'name' => AKISMET_EXT_NAME,
		// Description of what the extension does - string - wiki syntax
		'description' => "Filter articles for Spam using Akismet service. Delete spam with notify Akismet.",
		'description' => 'Denies edits from suspicious comment spammers on Akismet\'s.',
		'descriptionmsg' => "akismet-desc", // Same as above but name of a message, for i18n - string, added in 1.12.0
		'version' => AKISMET_EXT_VERSION,
		//wiki syntax
		'author' => array(
			'Valerij Ivashchenko',
			'2by2host.com team'
		), 
		'url' => '' // URL of extension (usually instructions) - string
	);

	//Preparing classes for autoloading
	$wgAutoloadClasses['AkismetHooks'] = AKISMET_EXT_DIR . '/AkismetHooks.php';
	$wgAutoloadClasses['AkismetMediawiki'] = AKISMET_EXT_DIR . '/AkismetMediawiki.php';

	//LightAkismet classes
	$wgAutoloadClasses['AkismetComment'] = AKISMET_EXT_DIR . '/LightAkismet/AkismetComment.php';
	$wgAutoloadClasses['AkismetService'] = AKISMET_EXT_DIR . '/LightAkismet/AkismetService.php';
	$wgAutoloadClasses['AkismetServiceSingleton'] = AKISMET_EXT_DIR . '/LightAkismet/AkismetServiceSingleton.php';

	//Internationalisation file
	$wgExtensionMessagesFiles[AKISMET_EXT_NAME] = AKISMET_EXT_DIR . '/' . AKISMET_EXT_NAME . '.i18n.php';

	/*---------------------
	* Add Delete Reason 
	*------------------------*/
	if ( $wgAkismetAddDeleteReason ) {
		$wgHooks['MessagesPreLoad'][] = 'AkismetHooks::onMessagesPreLoad';
		$wgHooks['ArticleDelete'][]   = 'AkismetHooks::onArticleDelete';
	}

	/*---------------------
	* Add Edit Filter
	*------------------------*/
	if ( $wgAkismetEnableEditFilter ) {
		$wgHooks['EditFilter'][] = 'AkismetHooks::onEditFilter';
	}

	if ( $wgAkismetEnableEditFilterMerged ) {
		$wgHooks['EditFilterMerged'][] = 'AkismetHooks::onEditFilterMerged';
	}

?>