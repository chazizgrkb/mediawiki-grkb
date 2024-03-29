<?php
/**
 * Base code for MediaWiki installer.
 *
 * @file
 * @ingroup Deployment
 */

/**
 * This documentation group collects source code files with deployment functionality.
 *
 * @defgroup Deployment Deployment
 */

/**
 * Base installer class.
 *
 * This class provides the base for installation and update functionality
 * for both MediaWiki core and extensions.
 *
 * @ingroup Deployment
 * @since 1.17
 */
abstract class Installer {

	// This is the absolute minimum PHP version we can support
	const MINIMUM_PHP_VERSION = '7.0.0'; // don't care enough to test it on PHP 5, shit's old. -grkb 5/9/2022

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * Cached DB installer instances, access using getDBInstaller().
	 *
	 * @var array
	 */
	protected $dbInstallers = array();

	/**
	 * Minimum memory size in MB.
	 *
	 * @var integer
	 */
	protected $minMemorySize = 50;

	/**
	 * Cached Title, used by parse().
	 *
	 * @var Title
	 */
	protected $parserTitle;

	/**
	 * Cached ParserOptions, used by parse().
	 *
	 * @var ParserOptions
	 */
	protected $parserOptions;

	/**
	 * Known database types. These correspond to the class names <type>Installer,
	 * and are also MediaWiki database types valid for $wgDBtype.
	 *
	 * To add a new type, create a <type>Installer class and a Database<type>
	 * class, and add a config-type-<type> message to MessagesEn.php.
	 *
	 * @var array
	 */
	protected static $dbTypes = array(
		'mysql',
		'mysqli',
		'postgres',
		'oracle',
		'sqlite',
		'ibm_db2',
	);

	/**
	 * A list of environment check methods called by doEnvironmentChecks().
	 * These may output warnings using showMessage(), and/or abort the
	 * installation process by returning false.
	 *
	 * @var array
	 */
	protected $envChecks = array(
		'envCheckDB',
		'envCheckRegisterGlobals',
		'envCheckBrokenXML',
		'envCheckPHP531',
		'envCheckMagicQuotes',
		'envCheckMagicSybase',
		'envCheckMbstring',
		'envCheckZE1',
		'envCheckSafeMode',
		'envCheckXML',
		'envCheckPCRE',
		'envCheckMemory',
		'envCheckCache',
		'envCheckModSecurity',
		'envCheckDiff3',
		'envCheckGraphics',
		'envCheckServer',
		'envCheckPath',
		'envCheckExtension',
		'envCheckShellLocale',
		'envCheckUploadsDirectory',
		'envCheckLibicu',
		'envCheckSuhosinMaxValueLength',
		'envCheckCtype',
	);

	/**
	 * MediaWiki configuration globals that will eventually be passed through
	 * to LocalSettings.php. The names only are given here, the defaults
	 * typically come from DefaultSettings.php.
	 *
	 * @var array
	 */
	protected $defaultVarNames = array(
		'wgSitename',
		'wgPasswordSender',
		'wgLanguageCode',
		'wgRightsIcon',
		'wgRightsText',
		'wgRightsUrl',
		'wgMainCacheType',
		'wgEnableEmail',
		'wgEnableUserEmail',
		'wgEnotifUserTalk',
		'wgEnotifWatchlist',
		'wgEmailAuthentication',
		'wgDBtype',
		'wgDiff3',
		'wgImageMagickConvertCommand',
		'IP',
		'wgServer',
		'wgScriptPath',
		'wgScriptExtension',
		'wgMetaNamespace',
		'wgDeletedDirectory',
		'wgEnableUploads',
		'wgLogo',
		'wgShellLocale',
		'wgSecretKey',
		'wgUseInstantCommons',
		'wgUpgradeKey',
		'wgDefaultSkin',
		'wgResourceLoaderMaxQueryLength',
	);

	/**
	 * Variables that are stored alongside globals, and are used for any
	 * configuration of the installation process aside from the MediaWiki
	 * configuration. Map of names to defaults.
	 *
	 * @var array
	 */
	protected $internalDefaults = array(
		'_UserLang' => 'en',
		'_Environment' => false,
		'_CompiledDBs' => array(),
		'_SafeMode' => false,
		'_RaiseMemory' => false,
		'_UpgradeDone' => false,
		'_InstallDone' => false,
		'_Caches' => array(),
		'_InstallPassword' => '',
		'_SameAccount' => true,
		'_CreateDBAccount' => false,
		'_NamespaceType' => 'site-name',
		'_AdminName' => '', // will be set later, when the user selects language
		'_AdminPassword' => '',
		'_AdminPassword2' => '',
		'_AdminEmail' => '',
		'_Subscribe' => false,
		'_SkipOptional' => 'continue',
		'_RightsProfile' => 'wiki',
		'_LicenseCode' => 'none',
		'_CCDone' => false,
		'_Extensions' => array(),
		'_MemCachedServers' => '',
		'_UpgradeKeySupplied' => false,
		'_ExistingDBSettings' => false,
	);

	/**
	 * The actual list of installation steps. This will be initialized by getInstallSteps()
	 *
	 * @var array
	 */
	private $installSteps = array();

	/**
	 * Extra steps for installation, for things like DatabaseInstallers to modify
	 *
	 * @var array
	 */
	protected $extraInstallSteps = array();

	/**
	 * Known object cache types and the functions used to test for their existence.
	 *
	 * @var array
	 */
	protected $objectCaches = array(
		'xcache' => 'xcache_get',
		'apc' => 'apc_fetch',
		'wincache' => 'wincache_ucache_get'
	);

	/**
	 * User rights profiles.
	 *
	 * @var array
	 */
	public $rightsProfiles = array(
		'wiki' => array(),
		'no-anon' => array(
			'*' => array( 'edit' => false )
		),
		'fishbowl' => array(
			'*' => array(
				'createaccount' => false,
				'edit' => false,
			),
		),
		'private' => array(
			'*' => array(
				'createaccount' => false,
				'edit' => false,
				'read' => false,
			),
		),
	);

	/**
	 * License types.
	 *
	 * @var array
	 */
	public $licenses = array(
		'cc-by' => array(
			'url' => 'http://creativecommons.org/licenses/by/3.0/',
			'icon' => '{$wgStylePath}/common/images/cc-by.png',
		),
		'cc-by-sa' => array(
			'url' => 'http://creativecommons.org/licenses/by-sa/3.0/',
			'icon' => '{$wgStylePath}/common/images/cc-by-sa.png',
		),
		'cc-by-nc-sa' => array(
			'url' => 'http://creativecommons.org/licenses/by-nc-sa/3.0/',
			'icon' => '{$wgStylePath}/common/images/cc-by-nc-sa.png',
		),
		'cc-0' => array(
			'url' => 'https://creativecommons.org/publicdomain/zero/1.0/',
			'icon' => '{$wgStylePath}/common/images/cc-0.png',
		),
		'pd' => array(
			'url' => '',
			'icon' => '{$wgStylePath}/common/images/public-domain.png',
		),
		'gfdl' => array(
			'url' => 'http://www.gnu.org/copyleft/fdl.html',
			'icon' => '{$wgStylePath}/common/images/gnu-fdl.png',
		),
		'none' => array(
			'url' => '',
			'icon' => '',
			'text' => ''
		),
		'cc-choose' => array(
			// Details will be filled in by the selector.
			'url' => '',
			'icon' => '',
			'text' => '',
		),
	);

	/**
	 * URL to mediawiki-announce subscription
	 */
	protected $mediaWikiAnnounceUrl = 'https://lists.wikimedia.org/mailman/subscribe/mediawiki-announce';

	/**
	 * Supported language codes for Mailman
	 */
	protected $mediaWikiAnnounceLanguages = array(
		'ca', 'cs', 'da', 'de', 'en', 'es', 'et', 'eu', 'fi', 'fr', 'hr', 'hu',
		'it', 'ja', 'ko', 'lt', 'nl', 'no', 'pl', 'pt', 'pt-br', 'ro', 'ru',
		'sl', 'sr', 'sv', 'tr', 'uk'
	);

	/**
	 * UI interface for displaying a short message
	 * The parameters are like parameters to wfMsg().
	 * The messages will be in wikitext format, which will be converted to an
	 * output format such as HTML or text before being sent to the user.
	 * @param $msg
	 */
	public abstract function showMessage( $msg /*, ... */ );

	/**
	 * Same as showMessage(), but for displaying errors
	 * @param $msg
	 */
	public abstract function showError( $msg /*, ... */ );

	/**
	 * Show a message to the installing user by using a Status object
	 * @param $status Status
	 */
	public abstract function showStatusMessage( Status $status );

	/**
	 * Constructor, always call this from child classes.
	 */
	public function __construct() {
		global $wgExtensionMessagesFiles, $wgUser;

		// Disable the i18n cache and LoadBalancer
		Language::getLocalisationCache()->disableBackend();
		LBFactory::disableBackend();

		// Load the installer's i18n file.
		$wgExtensionMessagesFiles['MediawikiInstaller'] =
			dirname( __FILE__ ) . '/Installer.i18n.php';

		// Having a user with id = 0 safeguards us from DB access via User::loadOptions().
		$wgUser = User::newFromId( 0 );

		$this->settings = $this->internalDefaults;

		foreach ( $this->defaultVarNames as $var ) {
			$this->settings[$var] = $GLOBALS[$var];
		}

		$compiledDBs = array();
		foreach ( self::getDBTypes() as $type ) {
			$installer = $this->getDBInstaller( $type );

			if ( !$installer->isCompiled() ) {
				continue;
			}
			$compiledDBs[] = $type;

			$defaults = $installer->getGlobalDefaults();

			foreach ( $installer->getGlobalNames() as $var ) {
				if ( isset( $defaults[$var] ) ) {
					$this->settings[$var] = $defaults[$var];
				} else {
					$this->settings[$var] = $GLOBALS[$var];
				}
			}
		}
		$this->setVar( '_CompiledDBs', $compiledDBs );

		$this->parserTitle = Title::newFromText( 'Installer' );
		$this->parserOptions = new ParserOptions; // language will  be wrong :(
		$this->parserOptions->setEditSection( false );
	}

	/**
	 * Get a list of known DB types.
	 *
	 * @return array
	 */
	public static function getDBTypes() {
		return self::$dbTypes;
	}

	/**
	 * Do initial checks of the PHP environment. Set variables according to
	 * the observed environment.
	 *
	 * It's possible that this may be called under the CLI SAPI, not the SAPI
	 * that the wiki will primarily run under. In that case, the subclass should
	 * initialise variables such as wgScriptPath, before calling this function.
	 *
	 * Under the web subclass, it can already be assumed that PHP 5+ is in use
	 * and that sessions are working.
	 *
	 * @return Status
	 */
	public function doEnvironmentChecks() {
		$phpVersion = phpversion();
		if( version_compare( $phpVersion, self::MINIMUM_PHP_VERSION, '>=' ) ) {
			$this->showMessage( 'config-env-php', $phpVersion );
			$good = true;
		} else {
			$this->showMessage( 'config-env-php-toolow', $phpVersion, self::MINIMUM_PHP_VERSION );
			$good = false;
		}

		if( $good ) {
			foreach ( $this->envChecks as $check ) {
				$status = $this->$check();
				if ( $status === false ) {
					$good = false;
				}
			}
		}

		$this->setVar( '_Environment', $good );

		return $good ? Status::newGood() : Status::newFatal( 'config-env-bad' );
	}

	/**
	 * Set a MW configuration variable, or internal installer configuration variable.
	 *
	 * @param $name String
	 * @param $value Mixed
	 */
	public function setVar( $name, $value ) {
		$this->settings[$name] = $value;
	}

	/**
	 * Get an MW configuration variable, or internal installer configuration variable.
	 * The defaults come from $GLOBALS (ultimately DefaultSettings.php).
	 * Installer variables are typically prefixed by an underscore.
	 *
	 * @param $name String
	 * @param $default Mixed
	 *
	 * @return mixed
	 */
	public function getVar( $name, $default = null ) {
		if ( !isset( $this->settings[$name] ) ) {
			return $default;
		} else {
			return $this->settings[$name];
		}
	}

	/**
	 * Get an instance of DatabaseInstaller for the specified DB type.
	 *
	 * @param $type Mixed: DB installer for which is needed, false to use default.
	 *
	 * @return DatabaseInstaller
	 */
	public function getDBInstaller( $type = false ) {
		if ( !$type ) {
			$type = $this->getVar( 'wgDBtype' );
		}

		$type = strtolower( $type );

		if ( !isset( $this->dbInstallers[$type] ) ) {
			$class = ucfirst( $type ). 'Installer';
			$this->dbInstallers[$type] = new $class( $this );
		}

		return $this->dbInstallers[$type];
	}

	/**
	 * Determine if LocalSettings.php exists. If it does, return its variables,
	 * merged with those from AdminSettings.php, as an array.
	 *
	 * @return Array
	 */
	public static function getExistingLocalSettings() {
		global $IP;

		// You might be wondering why this is here. Well if you don't do this
		// then some poorly-formed extensions try to call their own classes
		// after immediately registering them. We really need to get extension
		// registration out of the global scope and into a real format.
		// @see https://bugzilla.wikimedia.org/67440
		global $wgAutoloadClasses;

		wfSuppressWarnings();
		$_lsExists = file_exists( "$IP/LocalSettings.php" );
		wfRestoreWarnings();

		if( !$_lsExists ) {
			return false;
		}
		unset($_lsExists);

		require( "$IP/includes/DefaultSettings.php" );
		require( "$IP/LocalSettings.php" );
		if ( file_exists( "$IP/AdminSettings.php" ) ) {
			require( "$IP/AdminSettings.php" );
		}
		return get_defined_vars();
	}

	/**
	 * Get a fake password for sending back to the user in HTML.
	 * This is a security mechanism to avoid compromise of the password in the
	 * event of session ID compromise.
	 *
	 * @param $realPassword String
	 *
	 * @return string
	 */
	public function getFakePassword( $realPassword ) {
		return str_repeat( '*', strlen( $realPassword ) );
	}

	/**
	 * Set a variable which stores a password, except if the new value is a
	 * fake password in which case leave it as it is.
	 *
	 * @param $name String
	 * @param $value Mixed
	 */
	public function setPassword( $name, $value ) {
		if ( !preg_match( '/^\*+$/', $value ) ) {
			$this->setVar( $name, $value );
		}
	}

	/**
	 * On POSIX systems return the primary group of the webserver we're running under.
	 * On other systems just returns null.
	 *
	 * This is used to advice the user that he should chgrp his mw-config/data/images directory as the
	 * webserver user before he can install.
	 *
	 * Public because SqliteInstaller needs it, and doesn't subclass Installer.
	 *
	 * @return mixed
	 */
	public static function maybeGetWebserverPrimaryGroup() {
		if ( !function_exists( 'posix_getegid' ) || !function_exists( 'posix_getpwuid' ) ) {
			# I don't know this, this isn't UNIX.
			return null;
		}

		# posix_getegid() *not* getmygid() because we want the group of the webserver,
		# not whoever owns the current script.
		$gid = posix_getegid();
		$getpwuid = posix_getpwuid( $gid );
		$group = $getpwuid['name'];

		return $group;
	}

	/**
	 * Convert wikitext $text to HTML.
	 *
	 * This is potentially error prone since many parser features require a complete
	 * installed MW database. The solution is to just not use those features when you
	 * write your messages. This appears to work well enough. Basic formatting and
	 * external links work just fine.
	 *
	 * But in case a translator decides to throw in a #ifexist or internal link or
	 * whatever, this function is guarded to catch the attempted DB access and to present
	 * some fallback text.
	 *
	 * @param $text String
	 * @param $lineStart Boolean
	 * @return String
	 */
	public function parse( $text, $lineStart = false ) {
		global $wgParser;

		try {
			$out = $wgParser->parse( $text, $this->parserTitle, $this->parserOptions, $lineStart );
			$html = $out->getText();
		} catch ( DBAccessError $e ) {
			$html = '<!--DB access attempted during parse-->  ' . htmlspecialchars( $text );

			if ( !empty( $this->debug ) ) {
				$html .= "<!--\n" . $e->getTraceAsString() . "\n-->";
			}
		}

		return $html;
	}

	/**
	 * @return ParserOptions
	 */
	public function getParserOptions() {
		return $this->parserOptions;
	}

	public function disableLinkPopups() {
		$this->parserOptions->setExternalLinkTarget( false );
	}

	public function restoreLinkPopups() {
		global $wgExternalLinkTarget;
		$this->parserOptions->setExternalLinkTarget( $wgExternalLinkTarget );
	}

	/**
	 * Install step which adds a row to the site_stats table with appropriate
	 * initial values.
	 *
	 * @param $installer DatabaseInstaller
	 *
	 * @return Status
	 */
	public function populateSiteStats( DatabaseInstaller $installer ) {
		$status = $installer->getConnection();
		if ( !$status->isOK() ) {
			return $status;
		}
		$status->value->insert( 'site_stats', array(
			'ss_row_id' => 1,
			'ss_total_views' => 0,
			'ss_total_edits' => 0,
			'ss_good_articles' => 0,
			'ss_total_pages' => 0,
			'ss_users' => 0,
			'ss_images' => 0 ),
			__METHOD__, 'IGNORE' );
		return Status::newGood();
	}

	/**
	 * Exports all wg* variables stored by the installer into global scope.
	 */
	public function exportVars() {
		foreach ( $this->settings as $name => $value ) {
			if ( substr( $name, 0, 2 ) == 'wg' ) {
				$GLOBALS[$name] = $value;
			}
		}
	}

	/**
	 * Environment check for DB types.
	 * @return bool
	 */
	protected function envCheckDB() {
		global $wgLang;

		$allNames = array();

		foreach ( self::getDBTypes() as $name ) {
			$allNames[] = wfMsg( "config-type-$name" );
		}

		// cache initially available databases to make sure that everything will be displayed correctly
		// after a refresh on env checks page
		$databases = $this->getVar( '_CompiledDBs-preFilter' );
		if ( !$databases ) {
			$databases = $this->getVar( '_CompiledDBs' );
			$this->setVar( '_CompiledDBs-preFilter', $databases );
		}

		$databases = array_flip ( $databases );
		foreach ( array_keys( $databases ) as $db ) {
			$installer = $this->getDBInstaller( $db );
			$status = $installer->checkPrerequisites();
			if ( !$status->isGood() ) {
				$this->showStatusMessage( $status );
			}
			if ( !$status->isOK() ) {
				unset( $databases[$db] );
			}
		}
		$databases = array_flip( $databases );
		if ( !$databases ) {
			$this->showError( 'config-no-db', $wgLang->commaList( $allNames ) );
			// @todo FIXME: This only works for the web installer!
			return false;
		}
		$this->setVar( '_CompiledDBs', $databases );
	}

	/**
	 * Environment check for register_globals.
	 */
	protected function envCheckRegisterGlobals() {
		if( wfIniGetBool( 'register_globals' ) ) {
			$this->showMessage( 'config-register-globals' );
		}
	}

	/**
	 * Some versions of libxml+PHP break < and > encoding horribly
	 */
	protected function envCheckBrokenXML() {
		$test = new PhpXmlBugTester();
		if ( !$test->ok ) {
			$this->showError( 'config-brokenlibxml' );
			return false;
		}
	}

	/**
	 * Test PHP (probably 5.3.1, but it could regress again) to make sure that
	 * reference parameters to __call() are not converted to null
	 */
	protected function envCheckPHP531() {
		$test = new PhpRefCallBugTester;
		$test->execute();
		if ( !$test->ok ) {
			$this->showError( 'config-using531', phpversion() );
			return false;
		}
	}

	/**
	 * Environment check for magic_quotes_runtime.
	 */
	protected function envCheckMagicQuotes() {
		if( wfIniGetBool( "magic_quotes_runtime" ) ) {
			$this->showError( 'config-magic-quotes-runtime' );
			return false;
		}
	}

	/**
	 * Environment check for magic_quotes_sybase.
	 */
	protected function envCheckMagicSybase() {
		if ( wfIniGetBool( 'magic_quotes_sybase' ) ) {
			$this->showError( 'config-magic-quotes-sybase' );
			return false;
		}
	}

	/**
	 * Environment check for mbstring.func_overload.
	 */
	protected function envCheckMbstring() {
		if ( wfIniGetBool( 'mbstring.func_overload' ) ) {
			$this->showError( 'config-mbstring' );
			return false;
		}
	}

	/**
	 * Environment check for zend.ze1_compatibility_mode.
	 */
	protected function envCheckZE1() {
		if ( wfIniGetBool( 'zend.ze1_compatibility_mode' ) ) {
			$this->showError( 'config-ze1' );
			return false;
		}
	}

	/**
	 * Environment check for safe_mode.
	 */
	protected function envCheckSafeMode() {
		if ( wfIniGetBool( 'safe_mode' ) ) {
			$this->setVar( '_SafeMode', true );
			$this->showMessage( 'config-safe-mode' );
		}
	}

	/**
	 * Environment check for the XML module.
	 */
	protected function envCheckXML() {
		if ( !function_exists( "utf8_encode" ) ) {
			$this->showError( 'config-xml-bad' );
			return false;
		}
	}

	/**
	 * Environment check for the PCRE module.
	 *
	 * @note If this check were to fail, the parser would
	 *   probably throw an exception before the result
	 *   of this check is shown to the user.
	 * @return bool
	 */
	protected function envCheckPCRE() {
		if ( !function_exists( 'preg_match' ) ) {
			$this->showError( 'config-pcre' );
			return false;
		}
		wfSuppressWarnings();
		$regexd = preg_replace( '/[\x{0430}-\x{04FF}]/iu', '', '-АБВГД-' );
		// Need to check for \p support too, as PCRE can be compiled
		// with utf8 support, but not unicode property support.
		// check that \p{Zs} (space separators) matches
		// U+3000 (Ideographic space)
		$regexprop = preg_replace( '/\p{Zs}/u', '', "-\xE3\x80\x80-" );
		wfRestoreWarnings();
		if ( $regexd != '--' || $regexprop != '--' ) {
			$this->showError( 'config-pcre-no-utf8' );
			return false;
		}
	}

	/**
	 * Environment check for available memory.
	 */
	protected function envCheckMemory() {
		$limit = ini_get( 'memory_limit' );

		if ( !$limit || $limit == -1 ) {
			return true;
		}

		$n = wfShorthandToInteger( $limit );

		if( $n < $this->minMemorySize * 1024 * 1024 ) {
			$newLimit = "{$this->minMemorySize}M";

			if( ini_set( "memory_limit", $newLimit ) === false ) {
				$this->showMessage( 'config-memory-bad', $limit );
			} else {
				$this->showMessage( 'config-memory-raised', $limit, $newLimit );
				$this->setVar( '_RaiseMemory', true );
			}
		} else {
			return true;
		}
	}

	/**
	 * Environment check for compiled object cache types.
	 */
	protected function envCheckCache() {
		$caches = array();
		foreach ( $this->objectCaches as $name => $function ) {
			if ( function_exists( $function ) ) {
				if ( $name == 'xcache' && !wfIniGetBool( 'xcache.var_size' ) ) {
					continue;
				}
				$caches[$name] = true;
			}
		}

		if ( !$caches ) {
			$this->showMessage( 'config-no-cache' );
		}

		$this->setVar( '_Caches', $caches );
	}

	/**
	 * Scare user to death if they have mod_security
	 */
	protected function envCheckModSecurity() {
		if ( self::apacheModulePresent( 'mod_security' ) ) {
			$this->showMessage( 'config-mod-security' );
		}
	}

	/**
	 * Search for GNU diff3.
	 */
	protected function envCheckDiff3() {
		$names = array( "gdiff3", "diff3", "diff3.exe" );
		$versionInfo = array( '$1 --version 2>&1', 'GNU diffutils' );

		$diff3 = self::locateExecutableInDefaultPaths( $names, $versionInfo );

		if ( $diff3 ) {
			$this->setVar( 'wgDiff3', $diff3 );
		} else {
			$this->setVar( 'wgDiff3', false );
			$this->showMessage( 'config-diff3-bad' );
		}
	}

	/**
	 * Environment check for ImageMagick and GD.
	 */
	protected function envCheckGraphics() {
		$names = array( wfIsWindows() ? 'convert.exe' : 'convert' );
		$convert = self::locateExecutableInDefaultPaths( $names, array( '$1 -version', 'ImageMagick' ) );

		$this->setVar( 'wgImageMagickConvertCommand', '' );
		if ( $convert ) {
			$this->setVar( 'wgImageMagickConvertCommand', $convert );
			$this->showMessage( 'config-imagemagick', $convert );
			return true;
		} elseif ( function_exists( 'imagejpeg' ) ) {
			$this->showMessage( 'config-gd' );
			return true;
		} else {
			$this->showMessage( 'config-no-scaling' );
		}
	}

	/**
	 * Environment check for the server hostname.
	 */
	protected function envCheckServer() {
		$server = $this->envGetDefaultServer();
		$this->showMessage( 'config-using-server', $server );
		$this->setVar( 'wgServer', $server );
	}

	/**
	 * Helper function to be called from envCheckServer()
	 * @return String
	 */
	protected abstract function envGetDefaultServer();

	/**
	 * Environment check for setting $IP and $wgScriptPath.
	 * @return bool
	 */
	protected function envCheckPath() {
		global $IP;
		$IP = dirname( dirname( dirname( __FILE__ ) ) );
		$this->setVar( 'IP', $IP );

		$this->showMessage( 'config-using-uri', $this->getVar( 'wgServer' ), $this->getVar( 'wgScriptPath' ) );
		return true;
	}

	/**
	 * Environment check for setting the preferred PHP file extension.
	 */
	protected function envCheckExtension() {
		// @todo FIXME: Detect this properly
		if ( defined( 'MW_INSTALL_PHP5_EXT' ) ) {
			$ext = 'php5';
		} else {
			$ext = 'php';
		}
		$this->setVar( 'wgScriptExtension', ".$ext" );
	}

	/**
	 * TODO: document
	 * @return bool
	 */
	protected function envCheckShellLocale() {
		$os = php_uname( 's' );
		$supported = array( 'Linux', 'SunOS', 'HP-UX', 'Darwin' ); # Tested these

		if ( !in_array( $os, $supported ) ) {
			return true;
		}

		# Get a list of available locales.
		$ret = false;
		$lines = wfShellExec( '/usr/bin/locale -a', $ret );

		if ( $ret ) {
			return true;
		}

		$lines = wfArrayMap( 'trim', explode( "\n", $lines ) );
		$candidatesByLocale = array();
		$candidatesByLang = array();

		foreach ( $lines as $line ) {
			if ( $line === '' ) {
				continue;
			}

			if ( !preg_match( '/^([a-zA-Z]+)(_[a-zA-Z]+|)\.(utf8|UTF-8)(@[a-zA-Z_]*|)$/i', $line, $m ) ) {
				continue;
			}

			list( $all, $lang, $territory, $charset, $modifier ) = $m;

			$candidatesByLocale[$m[0]] = $m;
			$candidatesByLang[$lang][] = $m;
		}

		# Try the current value of LANG.
		if ( isset( $candidatesByLocale[ getenv( 'LANG' ) ] ) ) {
			$this->setVar( 'wgShellLocale', getenv( 'LANG' ) );
			return true;
		}

		# Try the most common ones.
		$commonLocales = array( 'en_US.UTF-8', 'en_US.utf8', 'de_DE.UTF-8', 'de_DE.utf8' );
		foreach ( $commonLocales as $commonLocale ) {
			if ( isset( $candidatesByLocale[$commonLocale] ) ) {
				$this->setVar( 'wgShellLocale', $commonLocale );
				return true;
			}
		}

		# Is there an available locale in the Wiki's language?
		$wikiLang = $this->getVar( 'wgLanguageCode' );

		if ( isset( $candidatesByLang[$wikiLang] ) ) {
			$m = reset( $candidatesByLang[$wikiLang] );
			$this->setVar( 'wgShellLocale', $m[0] );
			return true;
		}

		# Are there any at all?
		if ( count( $candidatesByLocale ) ) {
			$m = reset( $candidatesByLocale );
			$this->setVar( 'wgShellLocale', $m[0] );
			return true;
		}

		# Give up.
		return true;
	}

	/**
	 * TODO: document
	 */
	protected function envCheckUploadsDirectory() {
		global $IP;

		$dir = $IP . '/images/';
		$url = $this->getVar( 'wgServer' ) . $this->getVar( 'wgScriptPath' ) . '/images/';
		$safe = !$this->dirIsExecutable( $dir, $url );

		if ( $safe ) {
			return true;
		} else {
			$this->showMessage( 'config-uploads-not-safe', $dir );
		}
	}

	/**
	 * Checks if suhosin.get.max_value_length is set, and if so, sets
	 * $wgResourceLoaderMaxQueryLength to that value in the generated
	 * LocalSettings file
	 */
	protected function envCheckSuhosinMaxValueLength() {
		$maxValueLength = ini_get( 'suhosin.get.max_value_length' );
		if ( $maxValueLength > 0 ) {
			if( $maxValueLength < 1024 ) {
				# Only warn if the value is below the sane 1024
				$this->showMessage( 'config-suhosin-max-value-length', $maxValueLength );
			}
		} else {
			$maxValueLength = -1;
		}
		$this->setVar( 'wgResourceLoaderMaxQueryLength', $maxValueLength );
	}

	/**
	 * Convert a hex string representing a Unicode code point to that code point.
	 * @param $c String
	 * @return string
	 */
	protected function unicodeChar( $c ) {
		$c = hexdec($c);
		if ($c <= 0x7F) {
			return chr($c);
		} elseif ($c <= 0x7FF) {
			return chr(0xC0 | $c >> 6) . chr(0x80 | $c & 0x3F);
		} elseif ($c <= 0xFFFF) {
			return chr(0xE0 | $c >> 12) . chr(0x80 | $c >> 6 & 0x3F)
				. chr(0x80 | $c & 0x3F);
		} elseif ($c <= 0x10FFFF) {
			return chr(0xF0 | $c >> 18) . chr(0x80 | $c >> 12 & 0x3F)
				. chr(0x80 | $c >> 6 & 0x3F)
				. chr(0x80 | $c & 0x3F);
		} else {
			return false;
		}
	}


	/**
	 * Check the libicu version
	 */
	protected function envCheckLibicu() {
		$utf8 = function_exists( 'utf8_normalize' );
		$intl = function_exists( 'normalizer_normalize' );

		/**
		 * This needs to be updated something that the latest libicu
		 * will properly normalize.  This normalization was found at
		 * http://www.unicode.org/versions/Unicode5.2.0/#Character_Additions
		 * Note that we use the hex representation to create the code
		 * points in order to avoid any Unicode-destroying during transit.
		 */
		$not_normal_c = $this->unicodeChar("FA6C");
		$normal_c = $this->unicodeChar("242EE");

		$useNormalizer = 'php';
		$needsUpdate = false;

		/**
		 * We're going to prefer the pecl extension here unless
		 * utf8_normalize is more up to date.
		 */
		if( $utf8 ) {
			$useNormalizer = 'utf8';
			$utf8 = utf8_normalize( $not_normal_c, UtfNormal::UNORM_NFC );
			if ( $utf8 !== $normal_c ) $needsUpdate = true;
		}
		if( $intl ) {
			$useNormalizer = 'intl';
			$intl = normalizer_normalize( $not_normal_c, Normalizer::FORM_C );
			if ( $intl !== $normal_c ) $needsUpdate = true;
		}

		// Uses messages 'config-unicode-using-php', 'config-unicode-using-utf8', 'config-unicode-using-intl'
		if( $useNormalizer === 'php' ) {
			$this->showMessage( 'config-unicode-pure-php-warning' );
		} else {
			$this->showMessage( 'config-unicode-using-' . $useNormalizer );
			if( $needsUpdate ) {
				$this->showMessage( 'config-unicode-update-warning' );
			}
		}
	}

	protected function envCheckCtype() {
		if ( !function_exists( 'ctype_digit' ) ) {
			$this->showError( 'config-ctype' );
			return false;
		}
	}

	/**
	 * Get an array of likely places we can find executables. Check a bunch
	 * of known Unix-like defaults, as well as the PATH environment variable
	 * (which should maybe make it work for Windows?)
	 *
	 * @return Array
	 */
	protected static function getPossibleBinPaths() {
		return array_merge(
			array( '/usr/bin', '/usr/local/bin', '/opt/csw/bin',
				'/usr/gnu/bin', '/usr/sfw/bin', '/sw/bin', '/opt/local/bin' ),
			explode( PATH_SEPARATOR, getenv( 'PATH' ) )
		);
	}

	/**
	 * Search a path for any of the given executable names. Returns the
	 * executable name if found. Also checks the version string returned
	 * by each executable.
	 *
	 * Used only by environment checks.
	 *
	 * @param $path String: path to search
	 * @param $names Array of executable names
	 * @param $versionInfo Boolean false or array with two members:
	 *		 0 => Command to run for version check, with $1 for the full executable name
	 *		 1 => String to compare the output with
	 *
	 * If $versionInfo is not false, only executables with a version
	 * matching $versionInfo[1] will be returned.
	 */
	public static function locateExecutable( $path, $names, $versionInfo = false ) {
		if ( !is_array( $names ) ) {
			$names = array( $names );
		}

		foreach ( $names as $name ) {
			$command = $path . DIRECTORY_SEPARATOR . $name;

			wfSuppressWarnings();
			$file_exists = file_exists( $command );
			wfRestoreWarnings();

			if ( $file_exists ) {
				if ( !$versionInfo ) {
					return $command;
				}

				$file = str_replace( '$1', wfEscapeShellArg( $command ), $versionInfo[0] );
				if ( strstr( wfShellExec( $file ), $versionInfo[1] ) !== false ) {
					return $command;
				}
			}
		}
		return false;
	}

	/**
	 * Same as locateExecutable(), but checks in getPossibleBinPaths() by default
	 * @see locateExecutable()
	 * @param $names
	 * @param $versionInfo bool
	 * @return bool|string
	 */
	public static function locateExecutableInDefaultPaths( $names, $versionInfo = false ) {
		foreach( self::getPossibleBinPaths() as $path ) {
			$exe = self::locateExecutable( $path, $names, $versionInfo );
			if( $exe !== false ) {
				return $exe;
			}
		}
		return false;
	}

	/**
	 * Checks if scripts located in the given directory can be executed via the given URL.
	 *
	 * Used only by environment checks.
	 */
	public function dirIsExecutable( $dir, $url ) {
		$scriptTypes = array(
			'php' => array(
				"<?php echo 'ex' . 'ec';",
				"#!/var/env php5\n<?php echo 'ex' . 'ec';",
			),
		);

		// it would be good to check other popular languages here, but it'll be slow.

		wfSuppressWarnings();

		foreach ( $scriptTypes as $ext => $contents ) {
			foreach ( $contents as $source ) {
				$file = 'exectest.' . $ext;

				if ( !file_put_contents( $dir . $file, $source ) ) {
					break;
				}

				try {
					$text = Http::get( $url . $file, array( 'timeout' => 3 ) );
				}
				catch( MWException $e ) {
					// Http::get throws with allow_url_fopen = false and no curl extension.
					$text = null;
				}
				unlink( $dir . $file );

				if ( $text == 'exec' ) {
					wfRestoreWarnings();
					return $ext;
				}
			}
		}

		wfRestoreWarnings();

		return false;
	}

	/**
	 * Checks for presence of an Apache module. Works only if PHP is running as an Apache module, too.
	 *
	 * @param $moduleName String: Name of module to check.
	 * @return bool
	 */
	public static function apacheModulePresent( $moduleName ) {
		if ( function_exists( 'apache_get_modules' ) && in_array( $moduleName, apache_get_modules() ) ) {
			return true;
		}
		// try it the hard way
		ob_start();
		phpinfo( INFO_MODULES );
		$info = ob_get_clean();
		return strpos( $info, $moduleName ) !== false;
	}

	/**
	 * ParserOptions are constructed before we determined the language, so fix it
	 *
	 * @param $lang Language
	 */
	public function setParserLanguage( $lang ) {
		$this->parserOptions->setTargetLanguage( $lang );
		$this->parserOptions->setUserLang( $lang );
	}

	/**
	 * Overridden by WebInstaller to provide lastPage parameters.
	 * @param $page string
	 * @return string
	 */
	protected function getDocUrl( $page ) {
		return "{$_SERVER['PHP_SELF']}?page=" . urlencode( $page );
	}

	/**
	 * Finds extensions that follow the format /extensions/Name/Name.php,
	 * and returns an array containing the value for 'Name' for each found extension.
	 *
	 * @return array
	 */
	public function findExtensions() {
		if( $this->getVar( 'IP' ) === null ) {
			return false;
		}

		$exts = array();
		$extDir = $this->getVar( 'IP' ) . '/extensions';
		$dh = opendir( $extDir );

		while ( ( $file = readdir( $dh ) ) !== false ) {
			if( !is_dir( "$extDir/$file" ) ) {
				continue;
			}
			if( file_exists( "$extDir/$file/$file.php" ) ) {
				$exts[] = $file;
			}
		}
		natcasesort( $exts );

		return $exts;
	}

	/**
	 * Installs the auto-detected extensions.
	 *
	 * @return Status
	 */
	protected function includeExtensions() {
		global $IP;
		$exts = $this->getVar( '_Extensions' );
		$IP = $this->getVar( 'IP' );

		/**
		 * We need to include DefaultSettings before including extensions to avoid
		 * warnings about unset variables. However, the only thing we really
		 * want here is $wgHooks['LoadExtensionSchemaUpdates']. This won't work
		 * if the extension has hidden hook registration in $wgExtensionFunctions,
		 * but we're not opening that can of worms
		 * @see https://bugzilla.wikimedia.org/show_bug.cgi?id=26857
		 */
		global $wgAutoloadClasses;
		$wgAutoloadClasses = array();

		require( "$IP/includes/DefaultSettings.php" );

		foreach( $exts as $e ) {
			require_once( "$IP/extensions/$e/$e.php" );
		}

		$hooksWeWant = isset( $wgHooks['LoadExtensionSchemaUpdates'] ) ?
			$wgHooks['LoadExtensionSchemaUpdates'] : array();

		// Unset everyone else's hooks. Lord knows what someone might be doing
		// in ParserFirstCallInit (see bug 27171)
		$GLOBALS['wgHooks'] = array( 'LoadExtensionSchemaUpdates' => $hooksWeWant );

		return Status::newGood();
	}

	/**
	 * Get an array of install steps. Should always be in the format of
	 * array(
	 *   'name'     => 'someuniquename',
	 *   'callback' => array( $obj, 'method' ),
	 * )
	 * There must be a config-install-$name message defined per step, which will
	 * be shown on install.
	 *
	 * @param $installer DatabaseInstaller so we can make callbacks
	 * @return array
	 */
	protected function getInstallSteps( DatabaseInstaller $installer ) {
		$coreInstallSteps = array(
			array( 'name' => 'database',   'callback' => array( $installer, 'setupDatabase' ) ),
			array( 'name' => 'tables',     'callback' => array( $installer, 'createTables' ) ),
			array( 'name' => 'interwiki',  'callback' => array( $installer, 'populateInterwikiTable' ) ),
			array( 'name' => 'stats',      'callback' => array( $this, 'populateSiteStats' ) ),
			array( 'name' => 'keys',       'callback' => array( $this, 'generateKeys' ) ),
			array( 'name' => 'sysop',      'callback' => array( $this, 'createSysop' ) ),
			array( 'name' => 'mainpage',   'callback' => array( $this, 'createMainpage' ) ),
		);

		// Build the array of install steps starting from the core install list,
		// then adding any callbacks that wanted to attach after a given step
		foreach( $coreInstallSteps as $step ) {
			$this->installSteps[] = $step;
			if( isset( $this->extraInstallSteps[ $step['name'] ] ) ) {
				$this->installSteps = array_merge(
					$this->installSteps,
					$this->extraInstallSteps[ $step['name'] ]
				);
			}
		}

		// Prepend any steps that want to be at the beginning
		if( isset( $this->extraInstallSteps['BEGINNING'] ) ) {
			$this->installSteps = array_merge(
				$this->extraInstallSteps['BEGINNING'],
				$this->installSteps
			);
		}

		// Extensions should always go first, chance to tie into hooks and such
		if( count( $this->getVar( '_Extensions' ) ) ) {
			array_unshift( $this->installSteps,
				array( 'name' => 'extensions', 'callback' => array( $this, 'includeExtensions' ) )
			);
			$this->installSteps[] = array(
				'name' => 'extension-tables',
				'callback' => array( $installer, 'createExtensionTables' )
			);
		}
		return $this->installSteps;
	}

	/**
	 * Actually perform the installation.
	 *
	 * @param $startCB Array A callback array for the beginning of each step
	 * @param $endCB Array A callback array for the end of each step
	 *
	 * @return Array of Status objects
	 */
	public function performInstallation( $startCB, $endCB ) {
		$installResults = array();
		$installer = $this->getDBInstaller();
		$installer->preInstall();
		$steps = $this->getInstallSteps( $installer );
		foreach( $steps as $stepObj ) {
			$name = $stepObj['name'];
			call_user_func_array( $startCB, array( $name ) );

			// Perform the callback step
			$status = call_user_func( $stepObj['callback'], $installer );

			// Output and save the results
			call_user_func( $endCB, $name, $status );
			$installResults[$name] = $status;

			// If we've hit some sort of fatal, we need to bail.
			// Callback already had a chance to do output above.
			if( !$status->isOk() ) {
				break;
			}
		}
		if( $status->isOk() ) {
			$this->setVar( '_InstallDone', true );
		}
		return $installResults;
	}

	/**
	 * Generate $wgSecretKey. Will warn if we had to use an insecure random source.
	 *
	 * @return Status
	 */
	public function generateKeys() {
		$keys = array( 'wgSecretKey' => 64 );
		if ( strval( $this->getVar( 'wgUpgradeKey' ) ) === '' ) {
			$keys['wgUpgradeKey'] = 16;
		}
		return $this->doGenerateKeys( $keys );
	}

	/**
	 * Generate a secret value for variables using our CryptRand generator.
	 * Produce a warning if the random source was insecure.
	 *
	 * @param $keys Array
	 * @return Status
	 */
	protected function doGenerateKeys( $keys ) {
		$status = Status::newGood();

		$strong = true;
		foreach ( $keys as $name => $length ) {
			$secretKey = MWCryptRand::generateHex( $length, true );
			if ( !MWCryptRand::wasStrong() ) {
				$strong = false;
			}

			$this->setVar( $name, $secretKey );
		}

		if ( !$strong ) {
			$names = array_keys( $keys );
			$names = preg_replace( '/^(.*)$/', '\$$1', $names );
			global $wgLang;
			$status->warning( 'config-insecure-keys', $wgLang->listToText( $names ), count( $names ) );
		}

		return $status;
	}

	/**
	 * Create the first user account, grant it sysop and bureaucrat rights
	 *
	 * @return Status
	 */
	protected function createSysop() {
		$name = $this->getVar( '_AdminName' );
		$user = User::newFromName( $name );

		if ( !$user ) {
			// We should've validated this earlier anyway!
			return Status::newFatal( 'config-admin-error-user', $name );
		}

		if ( $user->idForName() == 0 ) {
			$user->addToDatabase();

			try {
				$user->setPassword( $this->getVar( '_AdminPassword' ) );
			} catch( PasswordError $pwe ) {
				return Status::newFatal( 'config-admin-error-password', $name, $pwe->getMessage() );
			}

			$user->addGroup( 'sysop' );
			$user->addGroup( 'bureaucrat' );
			if( $this->getVar( '_AdminEmail' ) ) {
				$user->setEmail( $this->getVar( '_AdminEmail' ) );
			}
			$user->saveSettings();

			// Update user count
			$ssUpdate = new SiteStatsUpdate( 0, 0, 0, 0, 1 );
			$ssUpdate->doUpdate();
		}
		$status = Status::newGood();

		if( $this->getVar( '_Subscribe' ) && $this->getVar( '_AdminEmail' ) ) {
			$this->subscribeToMediaWikiAnnounce( $status );
		}

		return $status;
	}

	/**
	 * @param $s Status
	 */
	private function subscribeToMediaWikiAnnounce( Status $s ) {
		$params = array(
			'email'    => $this->getVar( '_AdminEmail' ),
			'language' => 'en',
			'digest'   => 0
		);

		// Mailman doesn't support as many languages as we do, so check to make
		// sure their selected language is available
		$myLang = $this->getVar( '_UserLang' );
		if( in_array( $myLang, $this->mediaWikiAnnounceLanguages ) ) {
			$myLang = $myLang == 'pt-br' ? 'pt_BR' : $myLang; // rewrite to Mailman's pt_BR
			$params['language'] = $myLang;
		}

		if( MWHttpRequest::canMakeRequests() ) {
			$res = MWHttpRequest::factory( $this->mediaWikiAnnounceUrl,
				array( 'method' => 'POST', 'postData' => $params ) )->execute();
			if( !$res->isOK() ) {
				$s->warning( 'config-install-subscribe-fail', $res->getMessage() );
			}
		} else {
			$s->warning( 'config-install-subscribe-notpossible' );
		}
	}

	/**
	 * Insert Main Page with default content.
	 *
	 * @param $installer DatabaseInstaller
	 * @return Status
	 */
	protected function createMainpage( DatabaseInstaller $installer ) {
		$status = Status::newGood();
		try {
			$page = WikiPage::factory( Title::newMainPage() );
			$page->doEdit( wfMsgForContent( 'mainpagetext' ) . "\n\n" .
							wfMsgForContent( 'mainpagedocfooter' ),
							'',
							EDIT_NEW,
							false,
							User::newFromName( 'MediaWiki default' ) );
		} catch (MWException $e) {
			//using raw, because $wgShowExceptionDetails can not be set yet
			$status->fatal( 'config-install-mainpage-failed', $e->getMessage() );
		}

		return $status;
	}

	/**
	 * Override the necessary bits of the config to run an installation.
	 */
	public static function overrideConfig() {
		define( 'MW_NO_SESSION', 1 );

		// Don't access the database
		$GLOBALS['wgUseDatabaseMessages'] = false;
		// Debug-friendly
		$GLOBALS['wgShowExceptionDetails'] = true;
		// Don't break forms
		$GLOBALS['wgExternalLinkTarget'] = '_blank';

		// Extended debugging
		$GLOBALS['wgShowSQLErrors'] = true;
		$GLOBALS['wgShowDBErrorBacktrace'] = true;

		// Allow multiple ob_flush() calls
		$GLOBALS['wgDisableOutputCompression'] = true;

		// Use a sensible cookie prefix (not my_wiki)
		$GLOBALS['wgCookiePrefix'] = 'mw_installer';

		// Some of the environment checks make shell requests, remove limits
		$GLOBALS['wgMaxShellMemory'] = 0;
	}

	/**
	 * Add an installation step following the given step.
	 *
	 * @param $callback Array A valid installation callback array, in this form:
	 *    array( 'name' => 'some-unique-name', 'callback' => array( $obj, 'function' ) );
	 * @param $findStep String the step to find. Omit to put the step at the beginning
	 */
	public function addInstallStep( $callback, $findStep = 'BEGINNING' ) {
		$this->extraInstallSteps[$findStep][] = $callback;
	}

	/**
	 * Disable the time limit for execution.
	 * Some long-running pages (Install, Upgrade) will want to do this
	 */
	protected function disableTimeLimit() {
		wfSuppressWarnings();
		set_time_limit( 0 );
		wfRestoreWarnings();
	}
}
