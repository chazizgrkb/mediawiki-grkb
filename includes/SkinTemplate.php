<?php
/**
 * Base class for template-based skins
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 1 );
}

/**
 * Wrapper object for MediaWiki's localization functions,
 * to be passed to the template engine.
 *
 * @private
 * @ingroup Skins
 */
class MediaWiki_I18N {
	var $_context = array();

	function set( $varName, $value ) {
		$this->_context[$varName] = $value;
	}

	function translate( $value ) {
		wfProfileIn( __METHOD__ );

		// Hack for i18n:attributes in PHPTAL 1.0.0 dev version as of 2004-10-23
		$value = preg_replace( '/^string:/', '', $value );

		$value = wfMsg( $value );
		// interpolate variables
		$m = array();
		while( preg_match( '/\$([0-9]*?)/sm', $value, $m ) ) {
			list( $src, $var ) = $m;
			wfSuppressWarnings();
			$varValue = $this->_context[$var];
			wfRestoreWarnings();
			$value = str_replace( $src, $varValue, $value );
		}
		wfProfileOut( __METHOD__ );
		return $value;
	}
}

/**
 * Template-filler skin base class
 * Formerly generic PHPTal (http://phptal.sourceforge.net/) skin
 * Based on Brion's smarty skin
 * @copyright Copyright © Gabriel Wicke -- http://www.aulinx.de/
 *
 * @todo Needs some serious refactoring into functions that correspond
 * to the computations individual esi snippets need. Most importantly no body
 * parsing for most of those of course.
 *
 * @ingroup Skins
 */
class SkinTemplate extends Skin {
	/**#@+
	 * @private
	 */

	/**
	 * Name of our skin, it probably needs to be all lower case.  Child classes
	 * should override the default.
	 */
	var $skinname = 'monobook';

	/**
	 * Stylesheets set to use.  Subdirectory in skins/ where various stylesheets
	 * are located.  Child classes should override the default.
	 */
	var $stylename = 'monobook';

	/**
	 * For QuickTemplate, the name of the subclass which will actually fill the
	 * template.  Child classes should override the default.
	 */
	var $template = 'QuickTemplate';

	/**
	 * Whether this skin use OutputPage::headElement() to generate the <head>
	 * tag
	 */
	var $useHeadElement = false;

	/**#@-*/

	/**
	 * Add specific styles for this skin
	 *
	 * @param $out OutputPage
	 */
	function setupSkinUserCss( OutputPage $out ) {
		$out->addModuleStyles( array( 'mediawiki.legacy.shared', 'mediawiki.legacy.commonPrint' ) );
	}

	/**
	 * Create the template engine object; we feed it a bunch of data
	 * and eventually it spits out some HTML. Should have interface
	 * roughly equivalent to PHPTAL 0.7.
	 *
	 * @param $classname String
	 * @param $repository string: subdirectory where we keep template files
	 * @param $cache_dir string
	 * @return QuickTemplate
	 * @private
	 */
	function setupTemplate( $classname, $repository = false, $cache_dir = false ) {
		return new $classname();
	}

	/**
	 * initialize various variables and generate the template
	 *
	 * @param $out OutputPage
	 */
	function outputPage( OutputPage $out=null ) {
		global $wgContLang;
		global $wgScript, $wgStylePath;
		global $wgMimeType, $wgJsMimeType;
		global $wgXhtmlDefaultNamespace, $wgXhtmlNamespaces, $wgHtml5Version;
		global $wgDisableCounters, $wgSitename, $wgLogo, $wgHideInterlanguageLinks;
		global $wgMaxCredits, $wgShowCreditsIfMax;
		global $wgPageShowWatchingUsers;
		global $wgDebugComments;
		global $wgArticlePath, $wgScriptPath, $wgServer;

		wfProfileIn( __METHOD__ );
		Profiler::instance()->setTemplated( true );

		$oldContext = null;
		if ( $out !== null ) {
			// @todo Add wfDeprecated in 1.20
			$oldContext = $this->getContext();
			$this->setContext( $out->getContext() );
		}

		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();
		$title = $this->getTitle();

		wfProfileIn( __METHOD__ . '-init' );
		$this->initPage( $out );

		$tpl = $this->setupTemplate( $this->template, 'skins' );
		wfProfileOut( __METHOD__ . '-init' );

		wfProfileIn( __METHOD__ . '-stuff' );
		$this->thispage = $title->getPrefixedDBkey();
		$this->titletxt = $title->getPrefixedText();
		$this->userpage = $user->getUserPage()->getPrefixedText();
		$query = array();
		if ( !$request->wasPosted() ) {
			$query = $request->getValues();
			unset( $query['title'] );
			unset( $query['returnto'] );
			unset( $query['returntoquery'] );
		}
		$this->thisquery = wfArrayToCGI( $query );
		$this->loggedin = $user->isLoggedIn();
		$this->username = $user->getName();

		if ( $this->loggedin || $this->showIPinHeader() ) {
			$this->userpageUrlDetails = self::makeUrlDetails( $this->userpage );
		} else {
			# This won't be used in the standard skins, but we define it to preserve the interface
			# To save time, we check for existence
			$this->userpageUrlDetails = self::makeKnownUrlDetails( $this->userpage );
		}

		wfProfileOut( __METHOD__ . '-stuff' );

		wfProfileIn( __METHOD__ . '-stuff-head' );
		if ( !$this->useHeadElement ) {
			$tpl->set( 'pagecss', false );
			$tpl->set( 'usercss', false );

			$tpl->set( 'userjs', false );
			$tpl->set( 'userjsprev', false );

			$tpl->set( 'jsvarurl', false );

			$tpl->setRef( 'xhtmldefaultnamespace', $wgXhtmlDefaultNamespace );
			$tpl->set( 'xhtmlnamespaces', $wgXhtmlNamespaces );
			$tpl->set( 'html5version', $wgHtml5Version );
			$tpl->set( 'headlinks', $out->getHeadLinks() );
			$tpl->set( 'csslinks', $out->buildCssLinks() );
			$tpl->set( 'pageclass', $this->getPageClasses( $title ) );
			$tpl->set( 'skinnameclass', ( 'skin-' . Sanitizer::escapeClass( $this->getSkinName() ) ) );
		}
		wfProfileOut( __METHOD__ . '-stuff-head' );

		wfProfileIn( __METHOD__ . '-stuff2' );
		$tpl->set( 'title', $out->getPageTitle() );
		$tpl->set( 'pagetitle', $out->getHTMLTitle() );
		$tpl->set( 'displaytitle', $out->mPageLinkTitle );

		$tpl->setRef( 'thispage', $this->thispage );
		$tpl->setRef( 'titleprefixeddbkey', $this->thispage );
		$tpl->set( 'titletext', $title->getText() );
		$tpl->set( 'articleid', $title->getArticleId() );

		$tpl->set( 'isarticle', $out->isArticle() );

		$subpagestr = $this->subPageSubtitle();
		if ( $subpagestr !== '' ) {
			$subpagestr = '<span class="subpages">' . $subpagestr . '</span>';
		}
		$tpl->set( 'subtitle',  $subpagestr . $out->getSubtitle() );

		$undelete = $this->getUndeleteLink();
		if ( $undelete === '' ) {
			$tpl->set( 'undelete', '' );
		} else {
			$tpl->set( 'undelete', '<span class="subpages">' . $undelete . '</span>' );
		}

		$tpl->set( 'catlinks', $this->getCategories() );
		if( $out->isSyndicated() ) {
			$feeds = array();
			foreach( $out->getSyndicationLinks() as $format => $link ) {
				$feeds[$format] = array(
					'text' => $this->msg( "feed-$format" )->text(),
					'href' => $link
				);
			}
			$tpl->setRef( 'feeds', $feeds );
		} else {
			$tpl->set( 'feeds', false );
		}

		$tpl->setRef( 'mimetype', $wgMimeType );
		$tpl->setRef( 'jsmimetype', $wgJsMimeType );
		$tpl->set( 'charset', 'UTF-8' );
		$tpl->setRef( 'wgScript', $wgScript );
		$tpl->setRef( 'skinname', $this->skinname );
		$tpl->set( 'skinclass', get_class( $this ) );
		$tpl->setRef( 'skin', $this );
		$tpl->setRef( 'stylename', $this->stylename );
		$tpl->set( 'printable', $out->isPrintable() );
		$tpl->set( 'handheld', $request->getBool( 'handheld' ) );
		$tpl->setRef( 'loggedin', $this->loggedin );
		$tpl->set( 'notspecialpage', !$title->isSpecialPage() );
		/* XXX currently unused, might get useful later
		$tpl->set( 'editable', ( !$title->isSpecialPage() ) );
		$tpl->set( 'exists', $title->getArticleID() != 0 );
		$tpl->set( 'watch', $title->userIsWatching() ? 'unwatch' : 'watch' );
		$tpl->set( 'protect', count( $title->isProtected() ) ? 'unprotect' : 'protect' );
		$tpl->set( 'helppage', $this->msg( 'helppage' )->text() );
		*/
		$tpl->set( 'searchaction', $this->escapeSearchLink() );
		$tpl->set( 'searchtitle', SpecialPage::getTitleFor( 'Search' )->getPrefixedDBKey() );
		$tpl->set( 'search', trim( $request->getVal( 'search' ) ?? '' )  );
		$tpl->setRef( 'stylepath', $wgStylePath );
		$tpl->setRef( 'articlepath', $wgArticlePath );
		$tpl->setRef( 'scriptpath', $wgScriptPath );
		$tpl->setRef( 'serverurl', $wgServer );
		$tpl->setRef( 'logopath', $wgLogo );
		$tpl->setRef( 'sitename', $wgSitename );

		$lang = $this->getLanguage();
		$userlang = $lang->getHtmlCode();
		$userdir  = $lang->getDir();

		$tpl->set( 'lang', $userlang );
		$tpl->set( 'dir', $userdir );
		$tpl->set( 'rtl', $lang->isRTL() );

		$tpl->set( 'capitalizeallnouns', $lang->capitalizeAllNouns() ? ' capitalize-all-nouns' : '' );
		$tpl->set( 'showjumplinks', $user->getOption( 'showjumplinks' ) );
		$tpl->set( 'username', $this->loggedin ? $this->username : null );
		$tpl->setRef( 'userpage', $this->userpage );
		$tpl->setRef( 'userpageurl', $this->userpageUrlDetails['href'] );
		$tpl->set( 'userlang', $userlang );

		// Users can have their language set differently than the
		// content of the wiki. For these users, tell the web browser
		// that interface elements are in a different language.
		$tpl->set( 'userlangattributes', '' );
		$tpl->set( 'specialpageattributes', '' ); # obsolete

		if ( $userlang !== $wgContLang->getHtmlCode() || $userdir !== $wgContLang->getDir() ) {
			$escUserlang = htmlspecialchars( $userlang );
			$escUserdir = htmlspecialchars( $userdir );
			// Attributes must be in double quotes because htmlspecialchars() doesn't
			// escape single quotes
			$attrs = " lang=\"$escUserlang\" dir=\"$escUserdir\"";
			$tpl->set( 'userlangattributes', $attrs );
		}

		wfProfileOut( __METHOD__ . '-stuff2' );

		wfProfileIn( __METHOD__ . '-stuff3' );
		$tpl->set( 'newtalk', $this->getNewtalks() );
		$tpl->set( 'logo', $this->logoText() );

		$tpl->set( 'copyright', false );
		$tpl->set( 'viewcount', false );
		$tpl->set( 'lastmod', false );
		$tpl->set( 'credits', false );
		$tpl->set( 'numberofwatchingusers', false );
		if ( $out->isArticle() && $title->exists() ) {
			if ( $this->isRevisionCurrent() ) {
				if ( !$wgDisableCounters ) {
					$viewcount = $this->getWikiPage()->getCount();
					if ( $viewcount ) {
						$tpl->set( 'viewcount', $this->msg( 'viewcount' )->numParams( $viewcount )->parse() );
					}
				}

				if( $wgPageShowWatchingUsers ) {
					$dbr = wfGetDB( DB_SLAVE );
					$num = $dbr->selectField( 'watchlist', 'COUNT(*)',
						array( 'wl_title' => $title->getDBkey(), 'wl_namespace' => $title->getNamespace() ),
						__METHOD__
					);
					if( $num > 0 ) {
						$tpl->set( 'numberofwatchingusers',
							$this->msg( 'number_of_watching_users_pageview' )->numParams( $num )->parse()
						);
					}
				}

				if ( $wgMaxCredits != 0 ) {
					$tpl->set( 'credits', Action::factory( 'credits', $this->getWikiPage(),
						$this->getContext() )->getCredits( $wgMaxCredits, $wgShowCreditsIfMax ) );
				} else {
					$tpl->set( 'lastmod', $this->lastModified() );
				}
			}
			$tpl->set( 'copyright', $this->getCopyright() );
		}
		wfProfileOut( __METHOD__ . '-stuff3' );

		wfProfileIn( __METHOD__ . '-stuff4' );
		$tpl->set( 'copyrightico', $this->getCopyrightIcon() );
		$tpl->set( 'poweredbyico', $this->getPoweredBy() );
		$tpl->set( 'disclaimer', $this->disclaimerLink() );
		$tpl->set( 'privacy', $this->privacyLink() );
		$tpl->set( 'about', $this->aboutLink() );

		$tpl->set( 'footerlinks', array(
			'info' => array(
				'lastmod',
				'viewcount',
				'numberofwatchingusers',
				'credits',
				'copyright',
			),
			'places' => array(
				'privacy',
				'about',
				'disclaimer',
			),
		) );

		global $wgFooterIcons;
		$tpl->set( 'footericons', $wgFooterIcons );
		foreach ( $tpl->data['footericons'] as $footerIconsKey => &$footerIconsBlock ) {
			if ( count( $footerIconsBlock ) > 0 ) {
				foreach ( $footerIconsBlock as &$footerIcon ) {
					if ( isset( $footerIcon['src'] ) ) {
						if ( !isset( $footerIcon['width'] ) ) {
							$footerIcon['width'] = 88;
						}
						if ( !isset( $footerIcon['height'] ) ) {
							$footerIcon['height'] = 31;
						}
					}
				}
			} else {
				unset( $tpl->data['footericons'][$footerIconsKey] );
			}
		}

		if ( $wgDebugComments ) {
			$tpl->setRef( 'debug', $out->mDebugtext );
		} else {
			$tpl->set( 'debug', '' );
		}

		$tpl->set( 'sitenotice', $this->getSiteNotice() );
		$tpl->set( 'bottomscripts', $this->bottomScripts() );
		$tpl->set( 'printfooter', $this->printSource() );

		# An ID that includes the actual body text; without categories, contentSub, ...
		$realBodyAttribs = array( 'id' => 'mw-content-text' );

		# Add a mw-content-ltr/rtl class to be able to style based on text direction
		# when the content is different from the UI language, i.e.:
		# not for special pages or file pages AND only when viewing AND if the page exists
		# (or is in MW namespace, because that has default content)
		if( !in_array( $title->getNamespace(), array( NS_SPECIAL, NS_FILE ) ) &&
			in_array( $request->getVal( 'action', 'view' ), array( 'view', 'historysubmit' ) ) &&
			( $title->exists() || $title->getNamespace() == NS_MEDIAWIKI ) ) {
			$pageLang = $title->getPageLanguage();
			$realBodyAttribs['lang'] = $pageLang->getHtmlCode();
			$realBodyAttribs['dir'] = $pageLang->getDir();
			$realBodyAttribs['class'] = 'mw-content-'.$pageLang->getDir();
		}

		$out->mBodytext = Html::rawElement( 'div', $realBodyAttribs, $out->mBodytext );
		$tpl->setRef( 'bodytext', $out->mBodytext );

		# Language links
		$language_urls = array();

		if ( !$wgHideInterlanguageLinks ) {
			foreach( $out->getLanguageLinks() as $l ) {
				$tmp = explode( ':', $l, 2 );
				$class = 'interwiki-' . $tmp[0];
				unset( $tmp );
				$nt = Title::newFromText( $l );
				if ( $nt ) {
					$language_urls[] = array(
						'href' => $nt->getFullURL(),
						'text' => ( $wgContLang->getLanguageName( $nt->getInterwiki() ) != '' ?
									$wgContLang->getLanguageName( $nt->getInterwiki() ) : $l ),
						'title' => $nt->getText(),
						'class' => $class,
						'lang' => $nt->getInterwiki(),
						'hreflang' => $nt->getInterwiki(),
					);
				}
			}
		}
		if( count( $language_urls ) ) {
			$tpl->setRef( 'language_urls', $language_urls );
		} else {
			$tpl->set( 'language_urls', false );
		}
		wfProfileOut( __METHOD__ . '-stuff4' );

		wfProfileIn( __METHOD__ . '-stuff5' );
		# Personal toolbar
		$tpl->set( 'personal_urls', $this->buildPersonalUrls() );
		$content_navigation = $this->buildContentNavigationUrls();
		$content_actions = $this->buildContentActionUrls( $content_navigation );
		$tpl->setRef( 'content_navigation', $content_navigation );
		$tpl->setRef( 'content_actions', $content_actions );

		$tpl->set( 'sidebar', $this->buildSidebar() );
		$tpl->set( 'nav_urls', $this->buildNavUrls() );

		// Set the head scripts near the end, in case the above actions resulted in added scripts
		if ( $this->useHeadElement ) {
			$tpl->set( 'headelement', $out->headElement( $this ) );
		} else {
			$tpl->set( 'headscripts', $out->getHeadScripts() . $out->getHeadItems() );
		}

		$tpl->set( 'debughtml', $this->generateDebugHTML() );
		$tpl->set( 'reporttime', wfReportTime() );

		// original version by hansm
		if( !wfRunHooks( 'SkinTemplateOutputPageBeforeExec', array( &$this, &$tpl ) ) ) {
			wfDebug( __METHOD__ . ": Hook SkinTemplateOutputPageBeforeExec broke outputPage execution!\n" );
		}

		// Set the bodytext to another key so that skins can just output it on it's own
		// and output printfooter and debughtml separately
		$tpl->set( 'bodycontent', $tpl->data['bodytext'] );

		// Append printfooter and debughtml onto bodytext so that skins that were already
		// using bodytext before they were split out don't suddenly start not outputting information
		$tpl->data['bodytext'] .= Html::rawElement( 'div', array( 'class' => 'printfooter' ), "\n{$tpl->data['printfooter']}" ) . "\n";
		$tpl->data['bodytext'] .= $tpl->data['debughtml'];

		// allow extensions adding stuff after the page content.
		// See Skin::afterContentHook() for further documentation.
		$tpl->set( 'dataAfterContent', $this->afterContentHook() );
		wfProfileOut( __METHOD__ . '-stuff5' );

		// execute template
		wfProfileIn( __METHOD__ . '-execute' );
		$res = $tpl->execute();
		wfProfileOut( __METHOD__ . '-execute' );

		// result may be an error
		$this->printOrError( $res );

		if ( $oldContext ) {
			$this->setContext( $oldContext );
		}
		wfProfileOut( __METHOD__ );
	}

	/**
	 * Output the string, or print error message if it's
	 * an error object of the appropriate type.
	 * For the base class, assume strings all around.
	 *
	 * @param $str Mixed
	 * @private
	 */
	function printOrError( $str ) {
		echo $str;
	}

	/**
	 * Output a boolean indiciating if buildPersonalUrls should output separate
	 * login and create account links or output a combined link
	 * By default we simply return a global config setting that affects most skins
	 * This is setup as a method so that like with $wgLogo and getLogo() a skin
	 * can override this setting and always output one or the other if it has
	 * a reason it can't output one of the two modes.
	 */
	function useCombinedLoginLink() {
		global $wgUseCombinedLoginLink;
		return $wgUseCombinedLoginLink;
	}

	/**
	 * build array of urls for personal toolbar
	 * @return array
	 */
	protected function buildPersonalUrls() {
		$title = $this->getTitle();
		$request = $this->getRequest();
		$pageurl = $title->getLocalURL();
		wfProfileIn( __METHOD__ );

		/* set up the default links for the personal toolbar */
		$personal_urls = array();

		# Due to bug 32276, if a user does not have read permissions,
		# $this->getTitle() will just give Special:Badtitle, which is
		# not especially useful as a returnto parameter. Use the title
		# from the request instead, if there was one.
		$page = Title::newFromURL( $request->getVal( 'title', '' ) );
		$page = $request->getVal( 'returnto', $page );
		$a = array();
		if ( strval( $page ) !== '' ) {
			$a['returnto'] = $page;
			$query = $request->getVal( 'returntoquery', $this->thisquery );
			if( $query != '' ) {
				$a['returntoquery'] = $query;
			}
		}
		$returnto = wfArrayToCGI( $a );
		if( $this->loggedin ) {
			$personal_urls['userpage'] = array(
				'text' => $this->username,
				'href' => &$this->userpageUrlDetails['href'],
				'class' => $this->userpageUrlDetails['exists'] ? false : 'new',
				'active' => ( $this->userpageUrlDetails['href'] == $pageurl )
			);
			$usertalkUrlDetails = $this->makeTalkUrlDetails( $this->userpage );
			$personal_urls['mytalk'] = array(
				'text' => $this->msg( 'mytalk' )->text(),
				'href' => &$usertalkUrlDetails['href'],
				'class' => $usertalkUrlDetails['exists'] ? false : 'new',
				'active' => ( $usertalkUrlDetails['href'] == $pageurl )
			);
			$href = self::makeSpecialUrl( 'Preferences' );
			$personal_urls['preferences'] = array(
				'text' => $this->msg( 'mypreferences' )->text(),
				'href' => $href,
				'active' => ( $href == $pageurl )
			);
			$href = self::makeSpecialUrl( 'Watchlist' );
			$personal_urls['watchlist'] = array(
				'text' => $this->msg( 'mywatchlist' )->text(),
				'href' => $href,
				'active' => ( $href == $pageurl )
			);

			# We need to do an explicit check for Special:Contributions, as we
			# have to match both the title, and the target (which could come
			# from request values or be specified in "sub page" form. The plot
			# thickens, because the Title object is altered for special pages,
			# so doesn't contain the original alias-with-subpage.
			$origTitle = Title::newFromText( $request->getText( 'title' ) );
			if( $origTitle instanceof Title && $origTitle->isSpecialPage() ) {
				list( $spName, $spPar ) = SpecialPageFactory::resolveAlias( $origTitle->getText() );
				$active = $spName == 'Contributions'
					&& ( ( $spPar && $spPar == $this->username )
						|| $request->getText( 'target' ) == $this->username );
			} else {
				$active = false;
			}

			$href = self::makeSpecialUrlSubpage( 'Contributions', $this->username );
			$personal_urls['mycontris'] = array(
				'text' => $this->msg( 'mycontris' )->text(),
				'href' => $href,
				'active' => $active
			);
			$personal_urls['logout'] = array(
				'text' => $this->msg( 'userlogout' )->text(),
				'href' => self::makeSpecialUrl( 'Userlogout',
					// userlogout link must always contain an & character, otherwise we might not be able
					// to detect a buggy precaching proxy (bug 17790)
					$title->isSpecial( 'Preferences' ) ? 'noreturnto' : $returnto
				),
				'active' => false
			);
		} else {
			$useCombinedLoginLink = $this->useCombinedLoginLink();
			$loginlink = $this->getUser()->isAllowed( 'createaccount' ) && $useCombinedLoginLink
				? 'nav-login-createaccount'
				: 'login';
			$is_signup = $request->getText('type') == "signup";

			# anonlogin & login are the same
			$login_url = array(
				'text' => $this->msg( $loginlink )->text(),
				'href' => self::makeSpecialUrl( 'Userlogin', $returnto ),
				'active' => $title->isSpecial( 'Userlogin' ) && ( $loginlink == "nav-login-createaccount" || !$is_signup )
			);
			if ( $this->getUser()->isAllowed( 'createaccount' ) && !$useCombinedLoginLink ) {
				$createaccount_url = array(
					'text' => $this->msg( 'createaccount' )->text(),
					'href' => self::makeSpecialUrl( 'Userlogin', "$returnto&type=signup" ),
					'active' => $title->isSpecial( 'Userlogin' ) && $is_signup
				);
			}
			global $wgServer, $wgSecureLogin;
			if( substr( $wgServer, 0, 5 ) === 'http:' && $wgSecureLogin ) {
				$title = SpecialPage::getTitleFor( 'Userlogin' );
				$https_url = preg_replace( '/^http:/', 'https:', $title->getFullURL() );
				$login_url['href']  = $https_url;
				# @todo FIXME: Class depends on skin
				$login_url['class'] = 'link-https';
				if ( isset($createaccount_url) ) {
					$https_url = preg_replace( '/^http:/', 'https:',
						$title->getFullURL("type=signup") );
					$createaccount_url['href']  = $https_url;
					# @todo FIXME: Class depends on skin
					$createaccount_url['class'] = 'link-https';
				}
			}


			if( $this->showIPinHeader() ) {
				$href = &$this->userpageUrlDetails['href'];
				$personal_urls['anonuserpage'] = array(
					'text' => $this->username,
					'href' => $href,
					'class' => $this->userpageUrlDetails['exists'] ? false : 'new',
					'active' => ( $pageurl == $href )
				);
				$usertalkUrlDetails = $this->makeTalkUrlDetails( $this->userpage );
				$href = &$usertalkUrlDetails['href'];
				$personal_urls['anontalk'] = array(
					'text' => $this->msg( 'anontalk' )->text(),
					'href' => $href,
					'class' => $usertalkUrlDetails['exists'] ? false : 'new',
					'active' => ( $pageurl == $href )
				);
				$personal_urls['anonlogin'] = $login_url;
			} else {
				$personal_urls['login'] = $login_url;
			}
			if ( isset($createaccount_url) ) {
				$personal_urls['createaccount'] = $createaccount_url;
			}
		}

		wfRunHooks( 'PersonalUrls', array( &$personal_urls, &$title ) );
		wfProfileOut( __METHOD__ );
		return $personal_urls;
	}

	/**
	 * TODO document
	 * @param  $title Title
	 * @param  $message String message key
	 * @param  $selected Bool
	 * @param  $query String
	 * @param  $checkEdit Bool
	 * @return array
	 */
	function tabAction( $title, $message, $selected, $query = '', $checkEdit = false ) {
		$classes = array();
		if( $selected ) {
			$classes[] = 'selected';
		}
		if( $checkEdit && !$title->isKnown() ) {
			$classes[] = 'new';
			$query = 'action=edit&redlink=1';
		}

		// wfMessageFallback will nicely accept $message as an array of fallbacks
		// or just a single key
		$msg = wfMessageFallback( $message )->setContext( $this->getContext() );
		if ( is_array($message) ) {
			// for hook compatibility just keep the last message name
			$message = end($message);
		}
		if ( $msg->exists() ) {
			$text = $msg->text();
		} else {
			global $wgContLang;
			$text = $wgContLang->getFormattedNsText(
				MWNamespace::getSubject( $title->getNamespace() ) );
		}

		$result = array();
		if( !wfRunHooks( 'SkinTemplateTabAction', array( &$this,
				$title, $message, $selected, $checkEdit,
				&$classes, &$query, &$text, &$result ) ) ) {
			return $result;
		}

		return array(
			'class' => implode( ' ', $classes ),
			'text' => $text,
			'href' => $title->getLocalUrl( $query ),
			'primary' => true );
	}

	function makeTalkUrlDetails( $name, $urlaction = '' ) {
		$title = Title::newFromText( $name );
		if( !is_object( $title ) ) {
			throw new MWException( __METHOD__ . " given invalid pagename $name" );
		}
		$title = $title->getTalkPage();
		self::checkTitle( $title, $name );
		return array(
			'href' => $title->getLocalURL( $urlaction ),
			'exists' => $title->getArticleID() != 0,
		);
	}

	function makeArticleUrlDetails( $name, $urlaction = '' ) {
		$title = Title::newFromText( $name );
		$title= $title->getSubjectPage();
		self::checkTitle( $title, $name );
		return array(
			'href' => $title->getLocalURL( $urlaction ),
			'exists' => $title->getArticleID() != 0,
		);
	}

	/**
	 * a structured array of links usually used for the tabs in a skin
	 *
	 * There are 4 standard sections
	 * namespaces: Used for namespace tabs like special, page, and talk namespaces
	 * views: Used for primary page views like read, edit, history
	 * actions: Used for most extra page actions like deletion, protection, etc...
	 * variants: Used to list the language variants for the page
	 *
	 * Each section's value is a key/value array of links for that section.
	 * The links themseves have these common keys:
	 * - class: The css classes to apply to the tab
	 * - text: The text to display on the tab
	 * - href: The href for the tab to point to
	 * - rel: An optional rel= for the tab's link
	 * - redundant: If true the tab will be dropped in skins using content_actions
	 *   this is useful for tabs like "Read" which only have meaning in skins that
	 *   take special meaning from the grouped structure of content_navigation
	 *
	 * Views also have an extra key which can be used:
	 * - primary: If this is not true skins like vector may try to hide the tab
	 *            when the user has limited space in their browser window
	 *
	 * content_navigation using code also expects these ids to be present on the
	 * links, however these are usually automatically generated by SkinTemplate
	 * itself and are not necessary when using a hook. The only things these may
	 * matter to are people modifying content_navigation after it's initial creation:
	 * - id: A "preferred" id, most skins are best off outputting this preferred id for best compatibility
	 * - tooltiponly: This is set to true for some tabs in cases where the system
	 *                believes that the accesskey should not be added to the tab.
	 *
	 * @return array
	 */
	protected function buildContentNavigationUrls() {
		global $wgDisableLangConversion;

		wfProfileIn( __METHOD__ );

		$title = $this->getRelevantTitle(); // Display tabs for the relevant title rather than always the title itself
		$onPage = $title->equals($this->getTitle());

		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		$content_navigation = array(
			'namespaces' => array(),
			'views' => array(),
			'actions' => array(),
			'variants' => array()
		);

		// parameters
		$action = $request->getVal( 'action', 'view' );

		$userCanRead = $title->quickUserCan( 'read', $user );

		$preventActiveTabs = false;
		wfRunHooks( 'SkinTemplatePreventOtherActiveTabs', array( &$this, &$preventActiveTabs ) );

		// Checks if page is some kind of content
		if( $title->canExist() ) {
			// Gets page objects for the related namespaces
			$subjectPage = $title->getSubjectPage();
			$talkPage = $title->getTalkPage();

			// Determines if this is a talk page
			$isTalk = $title->isTalkPage();

			// Generates XML IDs from namespace names
			$subjectId = $title->getNamespaceKey( '' );

			if ( $subjectId == 'main' ) {
				$talkId = 'talk';
			} else {
				$talkId = "{$subjectId}_talk";
			}

			$skname = $this->skinname;

			// Adds namespace links
			$subjectMsg = array( "nstab-$subjectId" );
			if ( $subjectPage->isMainPage() ) {
				array_unshift($subjectMsg, 'mainpage-nstab');
			}
			$content_navigation['namespaces'][$subjectId] = $this->tabAction(
				$subjectPage, $subjectMsg, !$isTalk && !$preventActiveTabs, '', $userCanRead
			);
			$content_navigation['namespaces'][$subjectId]['context'] = 'subject';
			$content_navigation['namespaces'][$talkId] = $this->tabAction(
				$talkPage, array( "nstab-$talkId", 'talk' ), $isTalk && !$preventActiveTabs, '', $userCanRead
			);
			$content_navigation['namespaces'][$talkId]['context'] = 'talk';

			if ( $userCanRead ) {
				// Adds view view link
				if ( $title->exists() ) {
					$content_navigation['views']['view'] = $this->tabAction(
						$isTalk ? $talkPage : $subjectPage,
						array( "$skname-view-view", 'view' ),
						( $onPage && ($action == 'view' || $action == 'purge' ) ), '', true
					);
					$content_navigation['views']['view']['redundant'] = true; // signal to hide this from simple content_actions
				}

				wfProfileIn( __METHOD__ . '-edit' );

				// Checks if user can edit the current page if it exists or create it otherwise
				if ( $title->quickUserCan( 'edit', $user ) && ( $title->exists() || $title->quickUserCan( 'create', $user ) ) ) {
					// Builds CSS class for talk page links
					$isTalkClass = $isTalk ? ' istalk' : '';
					// Whether the user is editing the page
					$isEditing = $onPage && ( $action == 'edit' || $action == 'submit' );
					// Whether to show the "Add a new section" tab
					// Checks if this is a current rev of talk page and is not forced to be hidden
					$showNewSection = !$out->forceHideNewSectionLink()
						&& ( ( $isTalk && $this->isRevisionCurrent() ) || $out->showNewSectionLink() );
					$section = $request->getVal( 'section' );

					$msgKey = $title->exists() || ( $title->getNamespace() == NS_MEDIAWIKI && $title->getDefaultMessageText() !== false ) ?
						"edit" : "create";
					$content_navigation['views']['edit'] = array(
						'class' => ( $isEditing && ( $section !== 'new' || !$showNewSection ) ? 'selected' : '' ) . $isTalkClass,
						'text' => wfMessageFallback( "$skname-view-$msgKey", $msgKey )->setContext( $this->getContext() )->text(),
						'href' => $title->getLocalURL( $this->editUrlOptions() ),
						'primary' => true, // don't collapse this in vector
					);
					
					// section link
					if ( $showNewSection ) {
						// Adds new section link
						//$content_navigation['actions']['addsection']
						$content_navigation['views']['addsection'] = array(
							'class' => ( $isEditing && $section == 'new' ) ? 'selected' : false,
							'text' => wfMessageFallback( "$skname-action-addsection", 'addsection' )->setContext( $this->getContext() )->text(),
							'href' => $title->getLocalURL( 'action=edit&section=new' )
						);
					}
				// Checks if the page has some kind of viewable content
				} elseif ( $title->hasSourceText() ) {
					// Adds view source view link
					$content_navigation['views']['viewsource'] = array(
						'class' => ( $onPage && $action == 'edit' ) ? 'selected' : false,
						'text' => wfMessageFallback( "$skname-action-viewsource", 'viewsource' )->setContext( $this->getContext() )->text(),
						'href' => $title->getLocalURL( $this->editUrlOptions() ),
						'primary' => true, // don't collapse this in vector
					);
				}
				wfProfileOut( __METHOD__ . '-edit' );

				wfProfileIn( __METHOD__ . '-live' );
				// Checks if the page exists
				if ( $title->exists() ) {
					// Adds history view link
					$content_navigation['views']['history'] = array(
						'class' => ( $onPage && $action == 'history' ) ? 'selected' : false,
						'text' => wfMessageFallback( "$skname-view-history", 'history_short' )->setContext( $this->getContext() )->text(),
						'href' => $title->getLocalURL( 'action=history' ),
						'rel' => 'archives',
					);

					if ( $title->quickUserCan( 'delete', $user ) ) {
						$content_navigation['actions']['delete'] = array(
							'class' => ( $onPage && $action == 'delete' ) ? 'selected' : false,
							'text' => wfMessageFallback( "$skname-action-delete", 'delete' )->setContext( $this->getContext() )->text(),
							'href' => $title->getLocalURL( 'action=delete' )
						);
					}

					if ( $title->quickUserCan( 'move', $user ) ) {
						$moveTitle = SpecialPage::getTitleFor( 'Movepage', $title->getPrefixedDBkey() );
						$content_navigation['actions']['move'] = array(
							'class' => $this->getTitle()->isSpecial( 'Movepage' ) ? 'selected' : false,
							'text' => wfMessageFallback( "$skname-action-move", 'move' )->setContext( $this->getContext() )->text(),
							'href' => $moveTitle->getLocalURL()
						);
					}
				} else {
					// article doesn't exist or is deleted
					if ( $user->isAllowed( 'deletedhistory' ) ) {
						$n = $title->isDeleted();
						if( $n ) {
							$undelTitle = SpecialPage::getTitleFor( 'Undelete' );
							// If the user can't undelete but can view deleted history show them a "View .. deleted" tab instead
							$msgKey = $user->isAllowed( 'undelete' ) ? 'undelete' : 'viewdeleted';
							$content_navigation['actions']['undelete'] = array(
								'class' => $this->getTitle()->isSpecial( 'Undelete' ) ? 'selected' : false,
								'text' => wfMessageFallback( "$skname-action-$msgKey", "{$msgKey}_short" )
									->setContext( $this->getContext() )->numParams( $n )->text(),
								'href' => $undelTitle->getLocalURL( array( 'target' => $title->getPrefixedDBkey() ) )
							);
						}
					}
				}

				if ( $title->getNamespace() !== NS_MEDIAWIKI && $title->quickUserCan( 'protect', $user ) ) {
					$mode = $title->isProtected() ? 'unprotect' : 'protect';
					$content_navigation['actions'][$mode] = array(
						'class' => ( $onPage && $action == $mode ) ? 'selected' : false,
						'text' => wfMessageFallback( "$skname-action-$mode", $mode )->setContext( $this->getContext() )->text(),
						'href' => $title->getLocalURL( "action=$mode" )
					);
				}

				wfProfileOut( __METHOD__ . '-live' );

				// Checks if the user is logged in
				if ( $this->loggedin ) {
					/**
					 * The following actions use messages which, if made particular to
					 * the any specific skins, would break the Ajax code which makes this
					 * action happen entirely inline. Skin::makeGlobalVariablesScript
					 * defines a set of messages in a javascript object - and these
					 * messages are assumed to be global for all skins. Without making
					 * a change to that procedure these messages will have to remain as
					 * the global versions.
					 */
					$mode = $title->userIsWatching() ? 'unwatch' : 'watch';
					$token = WatchAction::getWatchToken( $title, $user, $mode );
					$content_navigation['actions'][$mode] = array(
						'class' => $onPage && ( $action == 'watch' || $action == 'unwatch' ) ? 'selected' : false,
						'text' => $this->msg( $mode )->text(), // uses 'watch' or 'unwatch' message
						'href' => $title->getLocalURL( array( 'action' => $mode, 'token' => $token ) )
					);
				}
			}

			wfRunHooks( 'SkinTemplateNavigation', array( &$this, &$content_navigation ) );

			if ( $userCanRead && !$wgDisableLangConversion ) {
				$pageLang = $title->getPageLanguage();
				// Gets list of language variants
				$variants = $pageLang->getVariants();
				// Checks that language conversion is enabled and variants exist
				// And if it is not in the special namespace
				if( count( $variants ) > 1 ) {
					// Gets preferred variant (note that user preference is 
					// only possible for wiki content language variant)
					$preferred = $pageLang->getPreferredVariant();
					// Loops over each variant
					foreach( $variants as $code ) {
						// Gets variant name from language code
						$varname = $pageLang->getVariantname( $code );
						// Checks if the variant is marked as disabled
						if( $varname == 'disable' ) {
							// Skips this variant
							continue;
						}
						// Appends variant link
						$content_navigation['variants'][] = array(
							'class' => ( $code == $preferred ) ? 'selected' : false,
							'text' => $varname,
							'href' => $title->getLocalURL( array( 'variant' => $code ) )
						);
					}
				}
			}
		} else {
			// If it's not content, it's got to be a special page
			$content_navigation['namespaces']['special'] = array(
				'class' => 'selected',
				'text' => $this->msg( 'nstab-special' )->text(),
				'href' => $request->getRequestURL(), // @bug 2457, 2510
				'context' => 'subject'
			);

			wfRunHooks( 'SkinTemplateNavigation::SpecialPage',
				array( &$this, &$content_navigation ) );
		}

		// Equiv to SkinTemplateContentActions
		wfRunHooks( 'SkinTemplateNavigation::Universal', array( &$this,  &$content_navigation ) );

		// Setup xml ids and tooltip info
		foreach ( $content_navigation as $section => &$links ) {
			foreach ( $links as $key => &$link ) {
				$xmlID = $key;
				if ( isset( $link['context'] ) && $link['context'] == 'subject' ) {
					$xmlID = 'ca-nstab-' . $xmlID;
				} elseif ( isset( $link['context'] ) && $link['context'] == 'talk' ) {
					$xmlID = 'ca-talk';
				} elseif ( $section == "variants" ) {
					$xmlID = 'ca-varlang-' . $xmlID;
				} else {
					$xmlID = 'ca-' . $xmlID;
				}
				$link['id'] = $xmlID;
			}
		}

		# We don't want to give the watch tab an accesskey if the
		# page is being edited, because that conflicts with the
		# accesskey on the watch checkbox.  We also don't want to
		# give the edit tab an accesskey, because that's fairly su-
		# perfluous and conflicts with an accesskey (Ctrl-E) often
		# used for editing in Safari.
		if( in_array( $action, array( 'edit', 'submit' ) ) ) {
			if ( isset($content_navigation['views']['edit']) ) {
				$content_navigation['views']['edit']['tooltiponly'] = true;
			}
			if ( isset($content_navigation['actions']['watch']) ) {
				$content_navigation['actions']['watch']['tooltiponly'] = true;
			}
			if ( isset($content_navigation['actions']['unwatch']) ) {
				$content_navigation['actions']['unwatch']['tooltiponly'] = true;
			}
		}

		wfProfileOut( __METHOD__ );

		return $content_navigation;
	}

	/**
	 * an array of edit links by default used for the tabs
	 * @return array
	 * @private
	 */
	function buildContentActionUrls( $content_navigation ) {

		wfProfileIn( __METHOD__ );

		// content_actions has been replaced with content_navigation for backwards
		// compatibility and also for skins that just want simple tabs content_actions
		// is now built by flattening the content_navigation arrays into one

		$content_actions = array();

		foreach ( $content_navigation as $links ) {

			foreach ( $links as $key => $value ) {

				if ( isset($value["redundant"]) && $value["redundant"] ) {
					// Redundant tabs are dropped from content_actions
					continue;
				}

				// content_actions used to have ids built using the "ca-$key" pattern
				// so the xmlID based id is much closer to the actual $key that we want
				// for that reason we'll just strip out the ca- if present and use
				// the latter potion of the "id" as the $key
				if ( isset($value["id"]) && substr($value["id"], 0, 3) == "ca-" ) {
					$key = substr($value["id"], 3);
				}

				if ( isset($content_actions[$key]) ) {
					wfDebug( __METHOD__ . ": Found a duplicate key for $key while flattening content_navigation into content_actions." );
					continue;
				}

				$content_actions[$key] = $value;

			}

		}

		wfProfileOut( __METHOD__ );

		return $content_actions;
	}

	/**
	 * build array of common navigation links
	 * @return array
	 * @private
	 */
	protected function buildNavUrls() {
		global $wgUploadNavigationUrl;

		wfProfileIn( __METHOD__ );

		$out = $this->getOutput();
		$request = $this->getRequest();

		$nav_urls = array();
		$nav_urls['mainpage'] = array( 'href' => self::makeMainPageUrl() );
		if( $wgUploadNavigationUrl ) {
			$nav_urls['upload'] = array( 'href' => $wgUploadNavigationUrl );
		} elseif( UploadBase::isEnabled() && UploadBase::isAllowed( $this->getUser() ) === true ) {
			$nav_urls['upload'] = array( 'href' => self::makeSpecialUrl( 'Upload' ) );
		} else {
			$nav_urls['upload'] = false;
		}
		$nav_urls['specialpages'] = array( 'href' => self::makeSpecialUrl( 'Specialpages' ) );

		$nav_urls['print'] = false;
		$nav_urls['permalink'] = false;
		$nav_urls['whatlinkshere'] = false;
		$nav_urls['recentchangeslinked'] = false;
		$nav_urls['contributions'] = false;
		$nav_urls['log'] = false;
		$nav_urls['blockip'] = false;
		$nav_urls['emailuser'] = false;

		// A print stylesheet is attached to all pages, but nobody ever
		// figures that out. :)  Add a link...
		if( $out->isArticle() ) {
			if ( !$out->isPrintable() ) {
				$nav_urls['print'] = array(
					'text' => $this->msg( 'printableversion' )->text(),
					'href' => $this->getTitle()->getLocalURL(
						$request->appendQueryValue( 'printable', 'yes', true ) )
				);
			}

			// Also add a "permalink" while we're at it
			$revid = $this->getRevisionId();
			if ( $revid ) {
				$nav_urls['permalink'] = array(
					'text' => $this->msg( 'permalink' )->text(),
					'href' => $out->getTitle()->getLocalURL( "oldid=$revid" )
				);
			}

			// Use the copy of revision ID in case this undocumented, shady hook tries to mess with internals
			wfRunHooks( 'SkinTemplateBuildNavUrlsNav_urlsAfterPermalink',
				array( &$this, &$nav_urls, &$revid, &$revid ) );
		}

		if ( $out->isArticleRelated() ) {
			$nav_urls['whatlinkshere'] = array(
				'href' => SpecialPage::getTitleFor( 'Whatlinkshere', $this->thispage )->getLocalUrl()
			);
			if ( $this->getTitle()->getArticleId() ) {
				$nav_urls['recentchangeslinked'] = array(
					'href' => SpecialPage::getTitleFor( 'Recentchangeslinked', $this->thispage )->getLocalUrl()
				);
			}
		}

		$user = $this->getRelevantUser();
		if ( $user ) {
			$rootUser = $user->getName();

			$nav_urls['contributions'] = array(
				'href' => self::makeSpecialUrlSubpage( 'Contributions', $rootUser )
			);

			if ( $user->isLoggedIn() ) {
				$logPage = SpecialPage::getTitleFor( 'Log' );
				$nav_urls['log'] = array(
					'href' => $logPage->getLocalUrl( array( 'user' => $rootUser ) )
				);
			}

			if ( $this->getUser()->isAllowed( 'block' ) ) {
				$nav_urls['blockip'] = array(
					'href' => self::makeSpecialUrlSubpage( 'Block', $rootUser )
				);
			}

			if ( $this->showEmailUser( $user ) ) {
				$nav_urls['emailuser'] = array(
					'href' => self::makeSpecialUrlSubpage( 'Emailuser', $rootUser )
				);
			}
		}

		wfProfileOut( __METHOD__ );
		return $nav_urls;
	}

	/**
	 * Generate strings used for xml 'id' names
	 * @return string
	 * @private
	 */
	function getNameSpaceKey() {
		return $this->getTitle()->getNamespaceKey();
	}

	public function commonPrintStylesheet() {
		return false;
	}
}

/**
 * Generic wrapper for template functions, with interface
 * compatible with what we use of PHPTAL 0.7.
 * @ingroup Skins
 */
abstract class QuickTemplate {
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->data = array();
		$this->translator = new MediaWiki_I18N();
	}

	/**
	 * Sets the value $value to $name
	 * @param $name
	 * @param $value
	 */
	public function set( $name, $value ) {
		$this->data[$name] = $value;
	}

	/**
	 * @param $name
	 * @param $value
	 */
	public function setRef( $name, &$value ) {
		$this->data[$name] =& $value;
	}

	/**
	 * @param $t
	 */
	public function setTranslator( &$t ) {
		$this->translator = &$t;
	}

	/**
	 * Main function, used by classes that subclass QuickTemplate
	 * to show the actual HTML output
	 */
	abstract public function execute();

	/**
	 * @private
	 */
	function text( $str ) {
		echo htmlspecialchars( $this->data[$str] );
	}

	/**
	 * @private
	 */
	function jstext( $str ) {
		echo Xml::escapeJsString( $this->data[$str] );
	}

	/**
	 * @private
	 */
	function html( $str ) {
		echo $this->data[$str];
	}

	/**
	 * @private
	 */
	function msg( $str ) {
		echo htmlspecialchars( $this->translator->translate( $str ) );
	}

	/**
	 * @private
	 */
	function msgHtml( $str ) {
		echo $this->translator->translate( $str );
	}

	/**
	 * An ugly, ugly hack.
	 * @private
	 */
	function msgWiki( $str ) {
		global $wgOut;

		$text = $this->translator->translate( $str );
		echo $wgOut->parse( $text );
	}

	/**
	 * @private
	 */
	function haveData( $str ) {
		return isset( $this->data[$str] );
	}

	/**
	 * @private
	 *
	 * @return bool
	 */
	function haveMsg( $str ) {
		$msg = $this->translator->translate( $str );
		return ( $msg != '-' ) && ( $msg != '' ); # ????
	}

	/**
	 * Get the Skin object related to this object
	 *
	 * @return Skin object
	 */
	public function getSkin() {
		return $this->data['skin'];
	}
}

/**
 * New base template for a skin's template extended from QuickTemplate
 * this class features helper methods that provide common ways of interacting
 * with the data stored in the QuickTemplate
 */
abstract class BaseTemplate extends QuickTemplate {

	/**
	 * Get a Message object with its context set
	 *
	 * @param $name Str message name
	 * @return Message
	 */
	public function getMsg( $name ) {
		return $this->getSkin()->msg( $name );
	}

	function msg( $str ) {
		echo $this->getMsg( $str )->escaped();
	}

	function msgHtml( $str ) {
		echo $this->getMsg( $str )->text();
	}

	function msgWiki( $str ) {
		echo $this->getMsg( $str )->parseAsBlock();
	}

	/**
	 * Create an array of common toolbox items from the data in the quicktemplate
	 * stored by SkinTemplate.
	 * The resulting array is built acording to a format intended to be passed
	 * through makeListItem to generate the html.
	 */
	function getToolbox() {
		wfProfileIn( __METHOD__ );

		$toolbox = array();
		if ( isset( $this->data['nav_urls']['whatlinkshere'] ) && $this->data['nav_urls']['whatlinkshere'] ) {
			$toolbox['whatlinkshere'] = $this->data['nav_urls']['whatlinkshere'];
			$toolbox['whatlinkshere']['id'] = 't-whatlinkshere';
		}
		if ( isset( $this->data['nav_urls']['recentchangeslinked'] ) && $this->data['nav_urls']['recentchangeslinked'] ) {
			$toolbox['recentchangeslinked'] = $this->data['nav_urls']['recentchangeslinked'];
			$toolbox['recentchangeslinked']['msg'] = 'recentchangeslinked-toolbox';
			$toolbox['recentchangeslinked']['id'] = 't-recentchangeslinked';
		}
		if ( isset( $this->data['feeds'] ) && $this->data['feeds'] ) {
			$toolbox['feeds']['id'] = 'feedlinks';
			$toolbox['feeds']['links'] = array();
			foreach ( $this->data['feeds'] as $key => $feed ) {
				$toolbox['feeds']['links'][$key] = $feed;
				$toolbox['feeds']['links'][$key]['id'] = "feed-$key";
				$toolbox['feeds']['links'][$key]['rel'] = 'alternate';
				$toolbox['feeds']['links'][$key]['type'] = "application/{$key}+xml";
				$toolbox['feeds']['links'][$key]['class'] = 'feedlink';
			}
		}
		foreach ( array( 'contributions', 'log', 'blockip', 'emailuser', 'upload', 'specialpages' ) as $special ) {
			if ( isset( $this->data['nav_urls'][$special] ) && $this->data['nav_urls'][$special] ) {
				$toolbox[$special] = $this->data['nav_urls'][$special];
				$toolbox[$special]['id'] = "t-$special";
			}
		}
		if ( isset( $this->data['nav_urls']['print'] ) && $this->data['nav_urls']['print'] ) {
			$toolbox['print'] = $this->data['nav_urls']['print'];
			$toolbox['print']['rel'] = 'alternate';
			$toolbox['print']['msg'] = 'printableversion';
		}
		if ( isset( $this->data['nav_urls']['permalink'] ) && $this->data['nav_urls']['permalink'] ) {
			$toolbox['permalink'] = $this->data['nav_urls']['permalink'];
			if( $toolbox['permalink']['href'] === '' ) {
				unset( $toolbox['permalink']['href'] );
				$toolbox['ispermalink']['tooltiponly'] = true;
				$toolbox['ispermalink']['id'] = 't-ispermalink';
				$toolbox['ispermalink']['msg'] = 'permalink';
			} else {
				$toolbox['permalink']['id'] = 't-permalink';
			}
		}
		wfRunHooks( 'BaseTemplateToolbox', array( &$this, &$toolbox ) );
		wfProfileOut( __METHOD__ );
		return $toolbox;
	}

	/**
	 * Create an array of personal tools items from the data in the quicktemplate
	 * stored by SkinTemplate.
	 * The resulting array is built acording to a format intended to be passed
	 * through makeListItem to generate the html.
	 * This is in reality the same list as already stored in personal_urls
	 * however it is reformatted so that you can just pass the individual items
	 * to makeListItem instead of hardcoding the element creation boilerplate.
	 */
	function getPersonalTools() {
		$personal_tools = array();
		foreach( $this->data['personal_urls'] as $key => $ptool ) {
			# The class on a personal_urls item is meant to go on the <a> instead
			# of the <li> so we have to use a single item "links" array instead
			# of using most of the personal_url's keys directly
			$personal_tools[$key] = array();
			$personal_tools[$key]["links"][] = array();
			$personal_tools[$key]["links"][0]["single-id"] = $personal_tools[$key]["id"] = "pt-$key";
			if ( isset($ptool["active"]) ) {
				$personal_tools[$key]["active"] = $ptool["active"];
			}
			foreach ( array("href", "class", "text") as $k ) {
				if ( isset($ptool[$k]) )
					$personal_tools[$key]["links"][0][$k] = $ptool[$k];
			}
		}
		return $personal_tools;
	}

	function getSidebar( $options = array() ) {
		// Force the rendering of the following portals
		$sidebar = $this->data['sidebar'];
		if ( !isset( $sidebar['SEARCH'] ) ) {
			$sidebar['SEARCH'] = true;
		}
		if ( !isset( $sidebar['TOOLBOX'] ) ) {
			$sidebar['TOOLBOX'] = true;
		}
		if ( !isset( $sidebar['LANGUAGES'] ) ) {
			$sidebar['LANGUAGES'] = true;
		}
		
		if ( !isset( $options['search'] ) || $options['search'] !== true ) {
			unset( $sidebar['SEARCH'] );
		}
		if ( isset( $options['toolbox'] ) && $options['toolbox'] === false ) {
			unset( $sidebar['TOOLBOX'] );
		}
		if ( isset( $options['languages'] ) && $options['languages'] === false ) {
			unset( $sidebar['LANGUAGES'] );
		}
		
		$boxes = array();
		foreach ( $sidebar as $boxName => $content ) {
			if ( $content === false ) {
				continue;
			}
			switch ( $boxName ) {
			case 'SEARCH':
				// Search is a special case, skins should custom implement this
				$boxes[$boxName] = array(
					'id'        => "p-search",
					'header'    => $this->getMsg( 'search' )->text(),
					'generated' => false,
					'content'   => true,
				);
				break;
			case 'TOOLBOX':
				$msgObj = $this->getMsg( 'toolbox' );
				$boxes[$boxName] = array(
					'id'        => "p-tb",
					'header'    => $msgObj->exists() ? $msgObj->text() : 'toolbox',
					'generated' => false,
					'content'   => $this->getToolbox(),
				);
				break;
			case 'LANGUAGES':
				if ( $this->data['language_urls'] ) {
					$msgObj = $this->getMsg( 'otherlanguages' );
					$boxes[$boxName] = array(
						'id'        => "p-lang",
						'header'    => $msgObj->exists() ? $msgObj->text() : 'otherlanguages',
						'generated' => false,
						'content'   => $this->data['language_urls'],
					);
				} 
				break;
			default:
				$msgObj = $this->getMsg( $boxName );
				$boxes[$boxName] = array(
					'id'        => "p-$boxName",
					'header'    => $msgObj->exists() ? $msgObj->text() : $boxName,
					'generated' => true,
					'content'   => $content,
				);
				break;
			}
		}
		
		// HACK: Compatibility with extensions still using SkinTemplateToolboxEnd
		$hookContents = null;
		if ( isset( $boxes['TOOLBOX'] ) ) {
			ob_start();
			// We pass an extra 'true' at the end so extensions using BaseTemplateToolbox
			// can abort and avoid outputting double toolbox links
			wfRunHooks( 'SkinTemplateToolboxEnd', array( &$this, true ) );
			$hookContents = ob_get_contents();
			ob_end_clean();
			if ( !trim( $hookContents ) ) {
				$hookContents = null;
			}
		}
		// END hack
		
		if ( isset( $options['htmlOnly'] ) && $options['htmlOnly'] === true ) {
			foreach ( $boxes as $boxName => $box ) {
				if ( is_array( $box['content'] ) ) {
					$content = "<ul>";
					foreach ( $box['content'] as $key => $val ) {
						$content .= "\n	" . $this->makeListItem( $key, $val );
					}
					// HACK, shove the toolbox end onto the toolbox if we're rendering itself
					if ( $hookContents ) {
						$content .= "\n	$hookContents"; 
					}
					// END hack
					$content .= "\n</ul>\n";
					$boxes[$boxName]['content'] = $content;
				}
			}
		} else {
			if ( $hookContents ) {
				$boxes['TOOLBOXEND'] = array(
					'id'        => "p-toolboxend",
					'header'    => $boxes['TOOLBOX']['header'],
					'generated' => false,
					'content'   => "<ul>{$hookContents}</ul>",
				);
				// HACK: Make sure that TOOLBOXEND is sorted next to TOOLBOX
				$boxes2 = array();
				foreach ( $boxes as $key => $box ) {
					if ( $key === 'TOOLBOXEND' ) {
						continue;
					}
					$boxes2[$key] = $box;
					if ( $key === 'TOOLBOX' ) {
						$boxes2['TOOLBOXEND'] = $boxes['TOOLBOXEND'];
					}
				}
				$boxes = $boxes2;
				// END hack
			}
		}
		
		return $boxes;
	}

	/**
	 * Makes a link, usually used by makeListItem to generate a link for an item
	 * in a list used in navigation lists, portlets, portals, sidebars, etc...
	 *
	 * $key is a string, usually a key from the list you are generating this link from
	 * $item is an array containing some of a specific set of keys.
	 * The text of the link will be generated either from the contents of the "text"
	 * key in the $item array, if a "msg" key is present a message by that name will
	 * be used, and if neither of those are set the $key will be used as a message name.
	 * If a "href" key is not present makeLink will just output htmlescaped text.
	 * The href, id, class, rel, and type keys are used as attributes for the link if present.
	 * If an "id" or "single-id" (if you don't want the actual id to be output on the link)
	 * is present it will be used to generate a tooltip and accesskey for the link.
	 * If you don't want an accesskey, set $item['tooltiponly'] = true;
	 * $options can be used to affect the output of a link:
	 *   You can use a text-wrapper key to specify a list of elements to wrap the
	 *     text of a link in. This should be an array of arrays containing a 'tag' and
	 *     optionally an 'attributes' key. If you only have one element you don't need
	 *     to wrap it in another array. eg: To use <a><span>...</span></a> in all links
	 *     use array( 'text-wrapper' => array( 'tag' => 'span' ) ) for your options.
	 *   A link-class key can be used to specify additional classes to apply to all links.
	 *   A link-fallback can be used to specify a tag to use instead of <a> if there is
	 *   no link. eg: If you specify 'link-fallback' => 'span' than any non-link will
	 *   output a <span> instead of just text.
	 */
	function makeLink( $key, $item, $options = array() ) {
		if ( isset( $item['text'] ) ) {
			$text = $item['text'];
		} else {
			$text = $this->translator->translate( isset( $item['msg'] ) ? $item['msg'] : $key );
		}

		$html = htmlspecialchars( $text );

		if ( isset( $options['text-wrapper'] ) ) {
			$wrapper = $options['text-wrapper'];
			if ( isset( $wrapper['tag'] ) ) {
				$wrapper = array( $wrapper );
			}
			while ( count( $wrapper ) > 0 ) {
				$element = array_pop( $wrapper );
				$html = Html::rawElement( $element['tag'], isset( $element['attributes'] ) ? $element['attributes'] : null, $html );
			}
		}

		if ( isset( $item['href'] ) || isset( $options['link-fallback'] ) ) {
			$attrs = $item;
			foreach ( array( 'single-id', 'text', 'msg', 'tooltiponly' ) as $k ) {
				unset( $attrs[$k] );
			}

			if ( isset( $item['id'] ) && !isset( $item['single-id'] ) ) {
				$item['single-id'] = $item['id'];
			}
			if ( isset( $item['single-id'] ) ) {
				if ( isset( $item['tooltiponly'] ) && $item['tooltiponly'] ) {
					$title = Linker::titleAttrib( $item['single-id'] );
					if ( $title !== false ) {
						$attrs['title'] = $title;
					}
				} else {
					$tip = Linker::tooltipAndAccesskeyAttribs( $item['single-id'] );
					if ( isset( $tip['title'] ) && $tip['title'] !== false ) {
						$attrs['title'] = $tip['title'];
					}
					if ( isset( $tip['accesskey'] ) && $tip['accesskey'] !== false ) {
						$attrs['accesskey'] = $tip['accesskey'];
					}
				}
			}
			if ( isset( $options['link-class'] ) ) {
				if ( isset( $attrs['class'] ) ) {
					$attrs['class'] .= " {$options['link-class']}";
				} else {
					$attrs['class'] = $options['link-class'];
				}
			}
			$html = Html::rawElement( isset( $attrs['href'] ) ? 'a' : $options['link-fallback'], $attrs, $html );
		}

		return $html;
	}

	/**
	 * Generates a list item for a navigation, portlet, portal, sidebar... etc list
	 * $key is a string, usually a key from the list you are generating this link from
	 * $item is an array of list item data containing some of a specific set of keys.
	 * The "id" and "class" keys will be used as attributes for the list item,
	 * if "active" contains a value of true a "active" class will also be appended to class.
	 * If you want something other than a <li> you can pass a tag name such as
	 * "tag" => "span" in the $options array to change the tag used.
	 * link/content data for the list item may come in one of two forms
	 * A "links" key may be used, in which case it should contain an array with
	 * a list of links to include inside the list item, see makeLink for the format
	 * of individual links array items.
	 * Otherwise the relevant keys from the list item $item array will be passed
	 * to makeLink instead. Note however that "id" and "class" are used by the
	 * list item directly so they will not be passed to makeLink
	 * (however the link will still support a tooltip and accesskey from it)
	 * If you need an id or class on a single link you should include a "links"
	 * array with just one link item inside of it.
	 * $options is also passed on to makeLink calls
	 */
	function makeListItem( $key, $item, $options = array() ) {
		if ( isset( $item['links'] ) ) {
			$html = '';
			foreach ( $item['links'] as $linkKey => $link ) {
				$html .= $this->makeLink( $linkKey, $link, $options );
			}
		} else {
			$link = $item;
			// These keys are used by makeListItem and shouldn't be passed on to the link
			foreach ( array( 'id', 'class', 'active', 'tag' ) as $k ) {
				unset( $link[$k] );
			}
			if ( isset( $item['id'] ) ) {
				// The id goes on the <li> not on the <a> for single links
				// but makeSidebarLink still needs to know what id to use when
				// generating tooltips and accesskeys.
				$link['single-id'] = $item['id'];
			}
			$html = $this->makeLink( $key, $link, $options );
		}

		$attrs = array();
		foreach ( array( 'id', 'class' ) as $attr ) {
			if ( isset( $item[$attr] ) ) {
				$attrs[$attr] = $item[$attr];
			}
		}
		if ( isset( $item['active'] ) && $item['active'] ) {
			if ( !isset( $attrs['class'] ) ) {
				$attrs['class'] = '';
			}
			$attrs['class'] .= ' active';
			$attrs['class'] = trim( $attrs['class'] );
		}
		return Html::rawElement( isset( $options['tag'] ) ? $options['tag'] : 'li', $attrs, $html );
	}

	function makeSearchInput( $attrs = array() ) {
		$realAttrs = array(
			'type' => 'search',
			'name' => 'search',
			'value' => isset( $this->data['search'] ) ? $this->data['search'] : '',
		);
		$realAttrs = array_merge( $realAttrs, Linker::tooltipAndAccesskeyAttribs( 'search' ), $attrs );
		return Html::element( 'input', $realAttrs );
	}

	function makeSearchButton( $mode, $attrs = array() ) {
		switch( $mode ) {
			case 'go':
			case 'fulltext':
				$realAttrs = array(
					'type' => 'submit',
					'name' => $mode,
					'value' => $this->translator->translate(
						$mode == 'go' ? 'searcharticle' : 'searchbutton' ),
				);
				$realAttrs = array_merge(
					$realAttrs,
					Linker::tooltipAndAccesskeyAttribs( "search-$mode" ),
					$attrs
				);
				return Html::element( 'input', $realAttrs );
			case 'image':
				$buttonAttrs = array(
					'type' => 'submit',
					'name' => 'button',
				);
				$buttonAttrs = array_merge(
					$buttonAttrs,
					Linker::tooltipAndAccesskeyAttribs( 'search-fulltext' ),
					$attrs
				);
				unset( $buttonAttrs['src'] );
				unset( $buttonAttrs['alt'] );
				$imgAttrs = array(
					'src' => $attrs['src'],
					'alt' => isset( $attrs['alt'] )
						? $attrs['alt']
						: $this->translator->translate( 'searchbutton' ),
				);
				return Html::rawElement( 'button', $buttonAttrs, Html::element( 'img', $imgAttrs ) );
			default:
				throw new MWException( 'Unknown mode passed to BaseTemplate::makeSearchButton' );
		}
	}

	/**
	 * Returns an array of footerlinks trimmed down to only those footer links that
	 * are valid.
	 * If you pass "flat" as an option then the returned array will be a flat array
	 * of footer icons instead of a key/value array of footerlinks arrays broken
	 * up into categories.
	 */
	function getFooterLinks( $option = null ) {
		$footerlinks = $this->data['footerlinks'];

		// Reduce footer links down to only those which are being used
		$validFooterLinks = array();
		foreach( $footerlinks as $category => $links ) {
			$validFooterLinks[$category] = array();
			foreach( $links as $link ) {
				if( isset( $this->data[$link] ) && $this->data[$link] ) {
					$validFooterLinks[$category][] = $link;
				}
			}
			if ( count( $validFooterLinks[$category] ) <= 0 ) {
				unset( $validFooterLinks[$category] );
			}
		}

		if ( $option == 'flat' ) {
			// fold footerlinks into a single array using a bit of trickery
			$validFooterLinks = call_user_func_array(
				'array_merge',
				array_values( $validFooterLinks )
			);
		}

		return $validFooterLinks;
	}

	/**
	 * Returns an array of footer icons filtered down by options relevant to how
	 * the skin wishes to display them.
	 * If you pass "icononly" as the option all footer icons which do not have an
	 * image icon set will be filtered out.
	 * If you pass "nocopyright" then MediaWiki's copyright icon will not be included
	 * in the list of footer icons. This is mostly useful for skins which only
	 * display the text from footericons instead of the images and don't want a
	 * duplicate copyright statement because footerlinks already rendered one.
	 */
	function getFooterIcons( $option = null ) {
		// Generate additional footer icons
		$footericons = $this->data['footericons'];

		if ( $option == 'icononly' ) {
			// Unset any icons which don't have an image
			foreach ( $footericons as &$footerIconsBlock ) {
				foreach ( $footerIconsBlock as $footerIconKey => $footerIcon ) {
					if ( !is_string( $footerIcon ) && !isset( $footerIcon['src'] ) ) {
						unset( $footerIconsBlock[$footerIconKey] );
					}
				}
			}
			// Redo removal of any empty blocks
			foreach ( $footericons as $footerIconsKey => &$footerIconsBlock ) {
				if ( count( $footerIconsBlock ) <= 0 ) {
					unset( $footericons[$footerIconsKey] );
				}
			}
		} elseif ( $option == 'nocopyright' ) {
			unset( $footericons['copyright']['copyright'] );
			if ( count( $footericons['copyright'] ) <= 0 ) {
				unset( $footericons['copyright'] );
			}
		}

		return $footericons;
	}

	/**
	 * Output the basic end-page trail including bottomscripts, reporttime, and
	 * debug stuff. This should be called right before outputting the closing
	 * body and html tags.
	 */
	function printTrail() { ?>
<?php $this->html('bottomscripts'); /* JS call to runBodyOnloadHook */ ?>
<?php $this->html('reporttime') ?>
<?php if ( $this->data['debug'] ): ?>
<!-- Debug output:
<?php $this->text( 'debug' ); ?>

-->
<?php endif;
	}

}

