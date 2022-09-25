<?php
/**
 * A few constants that might be needed during LocalSettings.php.
 *
 * Note: these constants must all be resolvable at compile time by HipHop,
 * since this file will not be executed during request startup for a compiled
 * MediaWiki.
 *
 * @file
 */

/**@{
 * Database related constants
 */
const DBO_DEBUG = 1;
const DBO_NOBUFFER = 2;
const DBO_IGNORE = 4;
const DBO_TRX = 8;
const DBO_DEFAULT = 16;
const DBO_PERSISTENT = 32;
const DBO_SYSDBA = 64; //for oracle maintenance
const DBO_DDLMODE = 128; // when using schema files: mostly for Oracle
const DBO_SSL = 256;
const DBO_COMPRESS = 512;
/**@}*/

/**@{
 * Valid database indexes
 * Operation-based indexes
 */
const DB_SLAVE = -1;     # Read from the slave (or only server)
const DB_MASTER = -2;    # Write to master (or only server)
const DB_LAST = -3;     # Whatever database was used last
/**@}*/

# Obsolete aliases
const DB_READ = -1;
const DB_WRITE = -2;


/**@{
 * Virtual namespaces; don't appear in the page database
 */
const NS_MEDIA = -2;
const NS_SPECIAL = -1;
/**@}*/

/**@{
 * Real namespaces
 *
 * Number 100 and beyond are reserved for custom namespaces;
 * DO NOT assign standard namespaces at 100 or beyond.
 * DO NOT Change integer values as they are most probably hardcoded everywhere
 * see bug #696 which talked about that.
 */
const NS_MAIN = 0;
const NS_TALK = 1;
const NS_USER = 2;
const NS_USER_TALK = 3;
const NS_PROJECT = 4;
const NS_PROJECT_TALK = 5;
const NS_FILE = 6;
const NS_FILE_TALK = 7;
const NS_MEDIAWIKI = 8;
const NS_MEDIAWIKI_TALK = 9;
const NS_TEMPLATE = 10;
const NS_TEMPLATE_TALK = 11;
const NS_HELP = 12;
const NS_HELP_TALK = 13;
const NS_CATEGORY = 14;
const NS_CATEGORY_TALK = 15;

/**
 * NS_IMAGE and NS_IMAGE_TALK are the pre-v1.14 names for NS_FILE and
 * NS_FILE_TALK respectively, and are kept for compatibility.
 *
 * When writing code that should be compatible with older MediaWiki
 * versions, either stick to the old names or define the new constants
 * yourself, if they're not defined already.
 */
const NS_IMAGE = NS_FILE;
const NS_IMAGE_TALK = NS_FILE_TALK;
/**@}*/

/**@{
 * Cache type
 */
const CACHE_ANYTHING = -1;  // Use anything, as long as it works
const CACHE_NONE = 0;       // Do not cache
const CACHE_DB = 1;         // Store cache objects in the DB
const CACHE_MEMCACHED = 2;  // MemCached, must specify servers in $wgMemCacheServers
const CACHE_ACCEL = 3;      // APC, XCache or WinCache
const CACHE_DBA = 4;        // Use PHP's DBA extension to store in a DBM-style database
/**@}*/

/**@{
 * Media types.
 * This defines constants for the value returned by File::getMediaType()
 */
const MEDIATYPE_UNKNOWN = 'UNKNOWN';     // unknown format
const MEDIATYPE_BITMAP = 'BITMAP';      // some bitmap image or image source (like psd, etc). Can't scale up.
const MEDIATYPE_DRAWING = 'DRAWING';     // some vector drawing (SVG, WMF, PS, ...) or image source (oo-draw, etc). Can scale up.
const MEDIATYPE_AUDIO = 'AUDIO';       // simple audio file (ogg, mp3, wav, midi, whatever)
const MEDIATYPE_VIDEO = 'VIDEO';       // simple video file (ogg, mpg, etc; no not include formats here that may contain executable sections or scripts!)
const MEDIATYPE_MULTIMEDIA = 'MULTIMEDIA';  // Scriptable Multimedia (flash, advanced video container formats, etc)
const MEDIATYPE_OFFICE = 'OFFICE';      // Office Documents, Spreadsheets (office formats possibly containing apples, scripts, etc)
const MEDIATYPE_TEXT = 'TEXT';        // Plain text (possibly containing program code or scripts)
const MEDIATYPE_EXECUTABLE = 'EXECUTABLE';  // binary executable
const MEDIATYPE_ARCHIVE = 'ARCHIVE';     // archive file (zip, tar, etc)
/**@}*/

/**@{
 * Antivirus result codes, for use in $wgAntivirusSetup.
 */
const AV_NO_VIRUS = 0;  #scan ok, no virus found
const AV_VIRUS_FOUND = 1;  #virus found!
const AV_SCAN_ABORTED = -1;  #scan aborted, the file is probably imune
const AV_SCAN_FAILED = false;  #scan failed (scanner not found or error in scanner)
/**@}*/

/**@{
 * Anti-lock flags
 * See DefaultSettings.php for a description
 */
const ALF_PRELOAD_LINKS = 1;
const ALF_PRELOAD_EXISTENCE = 2;
const ALF_NO_LINK_LOCK = 4;
const ALF_NO_BLOCK_LOCK = 8;
/**@}*/

/**@{
 * Date format selectors; used in user preference storage and by
 * Language::date() and co.
 */
/*define( 'MW_DATE_DEFAULT', '0' );
define( 'MW_DATE_MDY', '1' );
define( 'MW_DATE_DMY', '2' );
define( 'MW_DATE_YMD', '3' );
define( 'MW_DATE_ISO', 'ISO 8601' );*/
const MW_DATE_DEFAULT = 'default';
const MW_DATE_MDY = 'mdy';
const MW_DATE_DMY = 'dmy';
const MW_DATE_YMD = 'ymd';
const MW_DATE_ISO = 'ISO 8601';
/**@}*/

/**@{
 * RecentChange type identifiers
 */
const RC_EDIT = 0;
const RC_NEW = 1;
const RC_MOVE = 2; // obsolete
const RC_LOG = 3;
const RC_MOVE_OVER_REDIRECT = 4; // obsolete
/**@}*/

/**@{
 * Article edit flags
 */
const EDIT_NEW = 1;
const EDIT_UPDATE = 2;
const EDIT_MINOR = 4;
const EDIT_SUPPRESS_RC = 8;
const EDIT_FORCE_BOT = 16;
const EDIT_DEFER_UPDATES = 32;
const EDIT_AUTOSUMMARY = 64;
/**@}*/

/**@{
 * Flags for Database::makeList()
 * These are also available as Database class constants
 */
const LIST_COMMA = 0;
const LIST_AND = 1;
const LIST_SET = 2;
const LIST_NAMES = 3;
const LIST_OR = 4;
const LIST_SET_PREPARED = 8;  // List of (?, ?, ?) for DatabaseIbm_db2
/**@}*/

/**
 * Unicode and normalisation related
 */
require_once dirname(__FILE__).'/normal/UtfNormalDefines.php';

/**@{
 * Hook support constants
 */
const MW_SUPPORTS_EDITFILTERMERGED = 1;
const MW_SUPPORTS_PARSERFIRSTCALLINIT = 1;
const MW_SUPPORTS_LOCALISATIONCACHE = 1;
/**@}*/

/** Support for $wgResourceModules */
const MW_SUPPORTS_RESOURCE_MODULES = 1;

/**@{
 * Allowed values for Parser::$mOutputType
 * Parameter to Parser::startExternalParse().
 */
const OT_HTML = 1;
const OT_WIKI = 2;
const OT_PREPROCESS = 3;
const OT_MSG = 3;  // b/c alias for OT_PREPROCESS
const OT_PLAIN = 4;
/**@}*/

/**@{
 * Flags for Parser::setFunctionHook
 */
const SFH_NO_HASH = 1;
const SFH_OBJECT_ARGS = 2;
/**@}*/

/**
 * Flags for Parser::setLinkHook
 */
const SLH_PATTERN = 1;

/**
 * Flags for Parser::replaceLinkHolders
 */
const RLH_FOR_UPDATE = 1;

/**@{
 * Autopromote conditions (must be here and not in Autopromote.php, so that
 * they're loaded for DefaultSettings.php before AutoLoader.php)
 */
const APCOND_EDITCOUNT = 1;
const APCOND_AGE = 2;
const APCOND_EMAILCONFIRMED = 3;
const APCOND_INGROUPS = 4;
const APCOND_ISIP = 5;
const APCOND_IPINRANGE = 6;
const APCOND_AGE_FROM_EDIT = 7;
const APCOND_BLOCKED = 8;
const APCOND_ISBOT = 9;
/**@}*/

/**
 * Protocol constants for wfExpandUrl()
 */
const PROTO_HTTP = 'http://';
const PROTO_HTTPS = 'https://';
const PROTO_RELATIVE = '//';
const PROTO_CURRENT = null;
const PROTO_CANONICAL = 1;
const PROTO_INTERNAL = 2;
