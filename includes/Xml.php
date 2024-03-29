<?php

/**
 * Module of static functions for generating XML
 */

class Xml {
	/**
	 * Format an XML element with given attributes and, optionally, text content.
	 * Element and attribute names are assumed to be ready for literal inclusion.
	 * Strings are assumed to not contain XML-illegal characters; special
	 * characters (<, >, &) are escaped but illegals are not touched.
	 *
	 * @param $element String: element name
	 * @param $attribs Array: Name=>value pairs. Values will be escaped.
	 * @param $contents String: NULL to make an open tag only; '' for a contentless closed tag (default)
	 * @param $allowShortTag Bool: whether '' in $contents will result in a contentless closed tag
	 * @return string
	 */
	public static function element( $element, $attribs = null, $contents = '', $allowShortTag = true ) {
		$out = '<' . $element;
		if( !is_null( $attribs ) ) {
			$out .=  self::expandAttributes( $attribs );
		}
		if( is_null( $contents ) ) {
			$out .= '>';
		} else {
			if( $allowShortTag && $contents === '' ) {
				$out .= ' />';
			} else {
				$out .= '>' . htmlspecialchars( $contents ) . "</$element>";
			}
		}
		return $out;
	}

	/**
	 * Given an array of ('attributename' => 'value'), it generates the code
	 * to set the XML attributes : attributename="value".
	 * The values are passed to Sanitizer::encodeAttribute.
	 * Return null if no attributes given.
	 * @param $attribs Array of attributes for an XML element
	 */
	public static function expandAttributes( $attribs ) {
		$out = '';
		if( is_null( $attribs ) ) {
			return null;
		} elseif( is_array( $attribs ) ) {
			foreach( $attribs as $name => $val ) {
				$out .= " {$name}=\"" . Sanitizer::encodeAttribute( $val ) . '"';
			}
			return $out;
		} else {
			throw new MWException( 'Expected attribute array, got something else in ' . __METHOD__ );
		}
	}

	/**
	 * Format an XML element as with self::element(), but run text through the
	 * $wgContLang->normalize() validator first to ensure that no invalid UTF-8
	 * is passed.
	 *
	 * @param $element String:
	 * @param $attribs Array: Name=>value pairs. Values will be escaped.
	 * @param $contents String: NULL to make an open tag only; '' for a contentless closed tag (default)
	 * @return string
	 */
	public static function elementClean( $element, $attribs = array(), $contents = '') {
		global $wgContLang;
		if( $attribs ) {
			$attribs = array_map( array( 'UtfNormal', 'cleanUp' ), $attribs );
		}
		if( $contents ) {
			wfProfileIn( __METHOD__ . '-norm' );
			$contents = $wgContLang->normalize( $contents );
			wfProfileOut( __METHOD__ . '-norm' );
		}
		return self::element( $element, $attribs, $contents );
	}

	/**
	 * This opens an XML element
	 *
	 * @param $element String name of the element
	 * @param $attribs array of attributes, see Xml::expandAttributes()
	 * @return string
	 */
	public static function openElement( $element, $attribs = null ) {
		return '<' . $element . self::expandAttributes( $attribs ) . '>';
	}

	/**
	 * Shortcut to close an XML element
	 * @param $element String element name
	 * @return string
	 */
	public static function closeElement( $element ) { return "</$element>"; }

	/**
	 * Same as Xml::element(), but does not escape contents. Handy when the
	 * content you have is already valid xml.
	 *
	 * @param $element String element name
	 * @param $contents String content of the element
     * @param $attribs array of attributes
	 * @return string
	 */
	public static function tags( $element, $contents, $attribs = null ) {
		return self::openElement( $element, $attribs ) . $contents . "</$element>";
	}

	/**
	 * Build a drop-down box for selecting a namespace
	 *
	 * @param $selected Mixed: Namespace which should be pre-selected
	 * @param $all Mixed: Value of an item denoting all namespaces, or null to omit
	 * @param $element_name String: value of the "name" attribute of the select tag
	 * @param $label String: optional label to add to the field
	 * @return string
	 * @deprecated since 1.19
	 */
	public static function namespaceSelector( $selected = '', $all = null, $element_name = 'namespace', $label = null ) {
		wfDeprecated( __METHOD__, '1.19' );
		return Html::namespaceSelector( array(
			'selected' => $selected,
			'all'      => $all,
			'label'    => $label,
		), array(
			'name'  => $element_name,
			'id'    => 'namespace',
			'class' => 'namespaceselector',
		) );
	}

	/**
	 * Create a date selector
	 *
	 * @param $selected Mixed: the month which should be selected, default ''
	 * @param $allmonths String: value of a special item denoting all month. Null to not include (default)
	 * @param $id String: Element identifier
	 * @return String: Html string containing the month selector
	 */
	public static function monthSelector( $selected = '', $allmonths = null, $id = 'month' ) {
		global $wgLang;
		$options = array();
		if( is_null( $selected ) )
			$selected = '';
		if( !is_null( $allmonths ) )
			$options[] = self::option( wfMsg( 'monthsall' ), $allmonths, $selected === $allmonths );
		for( $i = 1; $i < 13; $i++ )
			$options[] = self::option( $wgLang->getMonthName( $i ), $i, $selected === $i );
		return self::openElement( 'select', array( 'id' => $id, 'name' => 'month', 'class' => 'mw-month-selector' ) )
			. implode( "\n", $options )
			. self::closeElement( 'select' );
	}

	/**
	 * @param $year Integer
	 * @param $month Integer
	 * @return string Formatted HTML
	 */
	public static function dateMenu( $year, $month ) {
		# Offset overrides year/month selection
		if( $month && $month !== -1 ) {
			$encMonth = intval( $month );
		} else {
			$encMonth = '';
		}
		if( $year ) {
			$encYear = intval( $year );
		} elseif( $encMonth ) {
			$thisMonth = intval( gmdate( 'n' ) );
			$thisYear = intval( gmdate( 'Y' ) );
			if( intval($encMonth) > $thisMonth ) {
				$thisYear--;
			}
			$encYear = $thisYear;
		} else {
			$encYear = '';
		}
		return Xml::label( wfMsg( 'year' ), 'year' ) . ' '.
			Xml::input( 'year', 4, $encYear, array('id' => 'year', 'maxlength' => 4) ) . ' '.
			Xml::label( wfMsg( 'month' ), 'month' ) . ' '.
			Xml::monthSelector( $encMonth, -1 );
	}

	/**
	 * Construct a language selector appropriate for use in a form or preferences
	 * 
	 * @param string $selected The language code of the selected language
	 * @param boolean $customisedOnly If true only languages which have some content are listed
	 * @param string $language The ISO code of the language to display the select list in (optional)
	 * @return array containing 2 items: label HTML and select list HTML
	 */
	public static function languageSelector( $selected, $customisedOnly = true, $language = null ) {
		global $wgLanguageCode;

		// If a specific language was requested and CLDR is installed, use it
		if ( $language && is_callable( array( 'LanguageNames', 'getNames' ) ) ) {
			if ( $customisedOnly ) {
				$listType = LanguageNames::LIST_MW_SUPPORTED; // Only pull names that have localisation in MediaWiki
			} else {
				$listType = LanguageNames::LIST_MW; // Pull all languages that are in Names.php
			}
			// Retrieve the list of languages in the requested language (via CLDR)
			$languages = LanguageNames::getNames(
				$language, // Code of the requested language
				LanguageNames::FALLBACK_NORMAL, // Use fallback chain
				$listType
			);
		} else {
			$languages = Language::getLanguageNames( $customisedOnly );
		}
		
		// Make sure the site language is in the list; a custom language code might not have a
		// defined name...
		if( !array_key_exists( $wgLanguageCode, $languages ) ) {
			$languages[$wgLanguageCode] = $wgLanguageCode;
		}
		
		ksort( $languages );

		/**
		 * If a bogus value is set, default to the content language.
		 * Otherwise, no default is selected and the user ends up
		 * with an Afrikaans interface since it's first in the list.
		 */
		$selected = isset( $languages[$selected] ) ? $selected : $wgLanguageCode;
		$options = "\n";
		foreach( $languages as $code => $name ) {
			$options .= Xml::option( "$code - $name", $code, ($code == $selected) ) . "\n";
		}

		return array(
			Xml::label( wfMsg('yourlanguage'), 'wpUserLanguage' ),
			Xml::tags( 'select',
                $options,
				array( 'id' => 'wpUserLanguage', 'name' => 'wpUserLanguage' ),
			)
		);

	}

	/**
	 * Shortcut to make a span element
	 * @param $text String content of the element, will be escaped
	 * @param $class String class name of the span element
	 * @param $attribs array other attributes
	 * @return string
	 */
	public static function span( $text, $class, $attribs = array() ) {
		return self::element( 'span', array( 'class' => $class ) + $attribs, $text );
	}

	/**
	 * Shortcut to make a specific element with a class attribute
	 * @param $text content of the element, will be escaped
	 * @param $class class name of the span element
	 * @param $tag string element name
	 * @param $attribs array other attributes
	 * @return string
	 */
	public static function wrapClass( $text, $class, $tag = 'span', $attribs = array() ) {
		return self::tags( $tag, $text, array( 'class' => $class ) + $attribs );
	}

	/**
	 * Convenience function to build an HTML text input field
	 * @param $name String value of the name attribute
	 * @param $size int value of the size attribute
	 * @param $value mixed value of the value attribute
	 * @param $attribs array other attributes
	 * @return string HTML
	 */
	public static function input( $name, $size = false, $value = false, $attribs = array() ) {
		$attributes = array( 'name' => $name );

		if( $size ) {
			$attributes['size'] = $size;
		}

		if( $value !== false ) { // maybe 0
			$attributes['value'] = $value;
		}

		return self::element( 'input', $attributes + $attribs );
	}

	/**
	 * Convenience function to build an HTML password input field
	 * @param $name string value of the name attribute
	 * @param $size int value of the size attribute
	 * @param $value mixed value of the value attribute
	 * @param $attribs array other attributes
	 * @return string HTML
	 */
	public static function password( $name, $size = false, $value = false, $attribs = array() ) {
		return self::input( $name, $size, $value, array_merge( $attribs, array( 'type' => 'password' ) ) );
	}

	/**
	 * Internal function for use in checkboxes and radio buttons and such.
	 *
	 * @param $name string
	 * @param $present bool
	 *
	 * @return array
	 */
	public static function attrib( $name, $present = true ) {
		return $present ? array( $name => $name ) : array();
	}

	/**
	 * Convenience function to build an HTML checkbox
	 * @param $name String value of the name attribute
	 * @param $checked Bool Whether the checkbox is checked or not
	 * @param $attribs Array other attributes
	 * @return string HTML
	 */
	public static function check( $name, $checked = false, $attribs=array() ) {
		return self::element( 'input', array_merge(
			array(
				'name' => $name,
				'type' => 'checkbox',
				'value' => 1 ),
			self::attrib( 'checked', $checked ),
			$attribs ) );
	}

	/**
	 * Convenience function to build an HTML radio button
	 * @param $name String value of the name attribute
	 * @param $value String value of the value attribute
	 * @param $checked Bool Whether the checkbox is checked or not
	 * @param $attribs Array other attributes
	 * @return string HTML
	 */
	public static function radio( $name, $value, $checked = false, $attribs = array() ) {
		return self::element( 'input', array(
			'name' => $name,
			'type' => 'radio',
			'value' => $value ) + self::attrib( 'checked', $checked ) + $attribs );
	}

	/**
	 * Convenience function to build an HTML form label
	 * @param $label String text of the label
	 * @param $id
	 * @param $attribs Array an attribute array.  This will usuall be
	 *     the same array as is passed to the corresponding input element,
	 *     so this function will cherry-pick appropriate attributes to
	 *     apply to the label as well; only class and title are applied.
	 * @return string HTML
	 */
	public static function label( $label, $id, $attribs = array() ) {
		$a = array( 'for' => $id );

		# FIXME avoid copy pasting below:
		if( isset( $attribs['class'] ) ){
				$a['class'] = $attribs['class'];
		}
		if( isset( $attribs['title'] ) ){
				$a['title'] = $attribs['title'];
		}

		return self::element( 'label', $a, $label );
	}

	/**
	 * Convenience function to build an HTML text input field with a label
	 * @param $label String text of the label
	 * @param $name String value of the name attribute
	 * @param $id String id of the input
	 * @param $size Int|Bool value of the size attribute
	 * @param $value String|Bool value of the value attribute
	 * @param $attribs array other attributes
	 * @return string HTML
	 */
	public static function inputLabel( $label, $name, $id, $size=false, $value=false, $attribs = array() ) {
		list( $label, $input ) = self::inputLabelSep( $label, $name, $id, $size, $value, $attribs );
		return $label . '&#160;' . $input;
	}

	/**
	 * Same as Xml::inputLabel() but return input and label in an array
	 *
	 * @param $label String
	 * @param $name String
	 * @param $id String
	 * @param $size Int|Bool
	 * @param $value String|Bool
	 * @param $attribs array
	 *
	 * @return array
	 */
	public static function inputLabelSep( $label, $name, $id, $size = false, $value = false, $attribs = array() ) {
		return array(
			Xml::label( $label, $id, $attribs ),
			self::input( $name, $size, $value, array( 'id' => $id ) + $attribs )
		);
	}

	/**
	 * Convenience function to build an HTML checkbox with a label
	 *
	 * @param $label
	 * @param $name
	 * @param $id
	 * @param $checked bool
	 * @param $attribs array
	 *
	 * @return string HTML
	 */
	public static function checkLabel( $label, $name, $id, $checked = false, $attribs = array() ) {
		return self::check( $name, $checked, array( 'id' => $id ) + $attribs ) .
			'&#160;' .
			self::label( $label, $id, $attribs );
	}

	/**
	 * Convenience function to build an HTML radio button with a label
	 *
	 * @param $label
	 * @param $name
	 * @param $value
	 * @param $id
	 * @param $checked bool
	 * @param $attribs array
	 *
	 * @return string HTML
	 */
	public static function radioLabel( $label, $name, $value, $id, $checked = false, $attribs = array() ) {
		return self::radio( $name, $value, $checked, array( 'id' => $id ) + $attribs ) .
			'&#160;' .
			self::label( $label, $id, $attribs );
	}

	/**
	 * Convenience function to build an HTML submit button
	 * @param $value String: label text for the button
	 * @param $attribs Array: optional custom attributes
	 * @return string HTML
	 */
	public static function submitButton( $value, $attribs = array() ) {
		return Html::element( 'input', array( 'type' => 'submit', 'value' => $value ) + $attribs );
	}

	/**
	 * Convenience function to build an HTML drop-down list item.
	 * @param $text String: text for this item
	 * @param $value String: form submission value; if empty, use text
	 * @param $selected boolean: if true, will be the default selected item
	 * @param $attribs array: optional additional HTML attributes
	 * @return string HTML
	 */
	public static function option( $text, $value=null, $selected = false,
			$attribs = array() ) {
		if( !is_null( $value ) ) {
			$attribs['value'] = $value;
		}
		if( $selected ) {
			$attribs['selected'] = 'selected';
		}
		return Html::element( 'option', $attribs, $text );
	}

	/**
	 * Build a drop-down box from a textual list.
	 *
	 * @param $name Mixed: Name and id for the drop-down
	 * @param $list Mixed: Correctly formatted text (newline delimited) to be used to generate the options
	 * @param $other Mixed: Text for the "Other reasons" option
	 * @param $selected Mixed: Option which should be pre-selected
	 * @param $class Mixed: CSS classes for the drop-down
	 * @param $tabindex Mixed: Value of the tabindex attribute
	 * @return string
	 */
	public static function listDropDown( $name= '', $list = '', $other = '', $selected = '', $class = '', $tabindex = null ) {
		$optgroup = false;

		$options = self::option( $other, 'other', $selected === 'other' );

		foreach ( explode( "\n", $list ) as $option) {
				$value = trim( $option );
				if ( $value == '' ) {
					continue;
				} elseif ( substr( $value, 0, 1) == '*' && substr( $value, 1, 1) != '*' ) {
					// A new group is starting ...
					$value = trim( substr( $value, 1 ) );
					if( $optgroup ) $options .= self::closeElement('optgroup');
					$options .= self::openElement( 'optgroup', array( 'label' => $value ) );
					$optgroup = true;
				} elseif ( substr( $value, 0, 2) == '**' ) {
					// groupmember
					$value = trim( substr( $value, 2 ) );
					$options .= self::option( $value, $value, $selected === $value );
				} else {
					// groupless reason list
					if( $optgroup ) $options .= self::closeElement('optgroup');
					$options .= self::option( $value, $value, $selected === $value );
					$optgroup = false;
				}
			}

			if( $optgroup ) $options .= self::closeElement('optgroup');

		$attribs = array();

		if( $name ) {
			$attribs['id'] = $name;
			$attribs['name'] = $name;
		}

		if( $class ) {
			$attribs['class'] = $class;
		}

		if( $tabindex ) {
			$attribs['tabindex'] = $tabindex;
		}

		return Xml::openElement( 'select', $attribs )
			. "\n"
			. $options
			. "\n"
			. Xml::closeElement( 'select' );
	}

	/**
	 * Shortcut for creating fieldsets.
	 *
	 * @param $legend Legend of the fieldset. If evaluates to false, legend is not added.
	 * @param $content Pre-escaped content for the fieldset. If false, only open fieldset is returned.
	 * @param $attribs array Any attributes to fieldset-element.
	 *
	 * @return string
	 */
	public static function fieldset( $legend = false, $content = false, $attribs = array() ) {
		$s = Xml::openElement( 'fieldset', $attribs ) . "\n";

		if ( $legend ) {
			$s .= Xml::element( 'legend', null, $legend ) . "\n";
		}

		if ( $content !== false ) {
			$s .= $content . "\n";
			$s .= Xml::closeElement( 'fieldset' ) . "\n";
		}

		return $s;
	}

	/**
	 * Shortcut for creating textareas.
	 *
	 * @param $name string The 'name' for the textarea
	 * @param $content string Content for the textarea
	 * @param $cols int The number of columns for the textarea
	 * @param $rows int The number of rows for the textarea
	 * @param $attribs array Any other attributes for the textarea
	 *
	 * @return string
	 */
	public static function textarea( $name, $content, $cols = 40, $rows = 5, $attribs = array() ) {
		return self::element( 'textarea',
					array(	'name' => $name,
						'id' => $name,
						'cols' => $cols,
						'rows' => $rows
					) + $attribs,
					$content, false );
	}

	/**
	 * Returns an escaped string suitable for inclusion in a string literal
	 * for JavaScript source code.
	 * Illegal control characters are assumed not to be present.
	 *
	 * @param $string String to escape
	 * @return String
	 */
	public static function escapeJsString( $string ) {
		// See ECMA 262 section 7.8.4 for string literal format
		$pairs = array(
			"\\" => "\\\\",
			"\"" => "\\\"",
			'\'' => '\\\'',
			"\n" => "\\n",
			"\r" => "\\r",

			# To avoid closing the element or CDATA section
			"<" => "\\x3c",
			">" => "\\x3e",

			# To avoid any complaints about bad entity refs
			"&" => "\\x26",

			# Work around https://bugzilla.mozilla.org/show_bug.cgi?id=274152
			# Encode certain Unicode formatting chars so affected
			# versions of Gecko don't misinterpret our strings;
			# this is a common problem with Farsi text.
			"\xe2\x80\x8c" => "\\u200c", // ZERO WIDTH NON-JOINER
			"\xe2\x80\x8d" => "\\u200d", // ZERO WIDTH JOINER
		);

		return strtr( $string, $pairs );
	}

	/**
	 * Encode a variable of unknown type to JavaScript.
	 * Arrays are converted to JS arrays, objects are converted to JS associative
	 * arrays (objects). So cast your PHP associative arrays to objects before
	 * passing them to here.
	 *
	 * @param $value
	 *
	 * @return string
	 */
	public static function encodeJsVar( $value ) {
		if ( is_bool( $value ) ) {
			$s = $value ? 'true' : 'false';
		} elseif ( is_null( $value ) ) {
			$s = 'null';
		} elseif ( is_int( $value ) || is_float( $value ) ) {
			$s = strval($value);
		} elseif ( is_array( $value ) && // Make sure it's not associative.
				   ( array_keys($value) === range( 0, count($value) - 1 ) ||
					count($value) == 0 )
				) {
			$s = '[';
			foreach ( $value as $elt ) {
				if ( $s != '[' ) {
					$s .= ',';
				}
				$s .= self::encodeJsVar( $elt );
			}
			$s .= ']';
		} elseif ( $value instanceof XmlJsCode ) {
			$s = $value->value;
		} elseif ( is_object( $value ) || is_array( $value ) ) {
			// Objects and associative arrays
			$s = '{';
			foreach ( (array)$value as $name => $elt ) {
				if ( $s != '{' ) {
					$s .= ',';
				}

				$s .= '"' . self::escapeJsString( $name ) . '":' .
					self::encodeJsVar( $elt );
			}
			$s .= '}';
		} else {
			$s = '"' . self::escapeJsString( $value ) . '"';
		}
		return $s;
	}

	/**
	 * Create a call to a JavaScript function. The supplied arguments will be
	 * encoded using Xml::encodeJsVar().
	 *
	 * @param $name String The name of the function to call, or a JavaScript expression
	 *    which evaluates to a function object which is called.
	 * @param $args Array of arguments to pass to the function.
	 *
	 * @since 1.17
	 *
	 * @return string
	 */
	public static function encodeJsCall( $name, $args ) {
		$s = "$name(";
		$first = true;

		foreach ( $args as $arg ) {
			if ( $first ) {
				$first = false;
			} else {
				$s .= ', ';
			}

			$s .= Xml::encodeJsVar( $arg );
		}

		$s .= ");\n";

		return $s;
	}

	/**
	 * Check if a string is well-formed XML.
	 * Must include the surrounding tag.
	 *
	 * @param $text String: string to test.
	 * @return bool
	 *
	 * @todo Error position reporting return
	 */
	public static function isWellFormed( $text ) {
		$parser = xml_parser_create( "UTF-8" );

		# case folding violates XML standard, turn it off
		xml_parser_set_option( $parser, XML_OPTION_CASE_FOLDING, false );

		if( !xml_parse( $parser, $text, true ) ) {
			//$err = xml_error_string( xml_get_error_code( $parser ) );
			//$position = xml_get_current_byte_index( $parser );
			//$fragment = $this->extractFragment( $html, $position );
			//$this->mXmlError = "$err at byte $position:\n$fragment";
			xml_parser_free( $parser );
			return false;
		}

		xml_parser_free( $parser );

		return true;
	}

	/**
	 * Check if a string is a well-formed XML fragment.
	 * Wraps fragment in an \<html\> bit and doctype, so it can be a fragment
	 * and can use HTML named entities.
	 *
	 * @param $text String:
	 * @return bool
	 */
	public static function isWellFormedXmlFragment( $text ) {
		$html =
			Sanitizer::hackDocType() .
			'<html>' .
			$text .
			'</html>';

		return Xml::isWellFormed( $html );
	}

	/**
	 * Replace " > and < with their respective HTML entities ( &quot;,
	 * &gt;, &lt;)
	 *
	 * @param $in String: text that might contain HTML tags.
	 * @return string Escaped string
	 */
	public static function escapeTagsOnly( $in ) {
		return str_replace(
			array( '"', '>', '<' ),
			array( '&quot;', '&gt;', '&lt;' ),
			$in );
	}

	/**
	* Generate a form (without the opening form element).
	* Output optionally includes a submit button.
	* @param $fields Array Associative array, key is message corresponding to a description for the field (colon is in the message), value is appropriate input.
	* @param $submitLabel String A message containing a label for the submit button.
	* @return string HTML form.
	*/
	public static function buildForm( $fields, $submitLabel = null ) {
		$form = '';
		$form .= "<table><tbody>";

		foreach( $fields as $labelmsg => $input ) {
			$id = "mw-$labelmsg";
			$form .= Xml::openElement( 'tr', array( 'id' => $id ) );
			$form .= Xml::tags( 'td', wfMsgExt( $labelmsg, array('parseinline') ), array('class' => 'mw-label') );
			$form .= Xml::openElement( 'td', array( 'class' => 'mw-input' ) ) . $input . Xml::closeElement( 'td' );
			$form .= Xml::closeElement( 'tr' );
		}

		if( $submitLabel ) {
			$form .= Xml::openElement( 'tr' );
			$form .= Xml::tags( 'td', '', array() );
			$form .= Xml::openElement( 'td', array( 'class' => 'mw-submit' ) ) . Xml::submitButton( wfMsg( $submitLabel ) ) . Xml::closeElement( 'td' );
			$form .= Xml::closeElement( 'tr' );
		}

		$form .= "</tbody></table>";

		return $form;
	}

	/**
	 * Build a table of data
	 * @param $rows array An array of arrays of strings, each to be a row in a table
	 * @param $attribs array An array of attributes to apply to the table tag [optional]
	 * @param $headers array An array of strings to use as table headers [optional]
	 * @return string
	 */
	public static function buildTable( $rows, $attribs = array(), $headers = null ) {
		$s = Xml::openElement( 'table', $attribs );

		if ( is_array( $headers ) ) {
			$s .= Xml::openElement( 'thead', $attribs );

			foreach( $headers as $id => $header ) {
				$attribs = array();

				if ( is_string( $id ) ) {
					$attribs['id'] = $id;
				}

				$s .= Xml::element( 'th', $attribs, $header );
			}
			$s .= Xml::closeElement( 'thead' );
		}

		foreach( $rows as $id => $row ) {
			$attribs = array();

			if ( is_string( $id ) ) {
				$attribs['id'] = $id;
			}

			$s .= Xml::buildTableRow( $attribs, $row );
		}

		$s .= Xml::closeElement( 'table' );

		return $s;
	}

	/**
	 * Build a row for a table
	 * @param $attribs array An array of attributes to apply to the tr tag
	 * @param $cells array An array of strings to put in <td>
	 * @return string
	 */
	public static function buildTableRow( $attribs, $cells ) {
		$s = Xml::openElement( 'tr', $attribs );

		foreach( $cells as $id => $cell ) {

			$attribs = array();

			if ( is_string( $id ) ) {
				$attribs['id'] = $id;
			}

			$s .= Xml::element( 'td', $attribs, $cell );
		}

		$s .= Xml::closeElement( 'tr' );

		return $s;
	}
}

class XmlSelect {
	protected $options = array();
	protected $default = false;
	protected $attributes = array();

	public function __construct( $name = false, $id = false, $default = false ) {
		if ( $name ) {
			$this->setAttribute( 'name', $name );
		}

		if ( $id ) {
			$this->setAttribute( 'id', $id );
		}

		if ( $default !== false ) {
			$this->default = $default;
		}
	}

	/**
	 * @param $default
	 */
	public function setDefault( $default ) {
		$this->default = $default;
	}

	/**
	 * @param $name string
	 * @param $value
	 */
	public function setAttribute( $name, $value ) {
		$this->attributes[$name] = $value;
	}

	/**
	 * @param $name
	 * @return array|null
	 */
	public function getAttribute( $name ) {
		if ( isset( $this->attributes[$name] ) ) {
			return $this->attributes[$name];
		} else {
			return null;
		}
	}

	/**
	 * @param $name
	 * @param $value bool
	 */
	public function addOption( $name, $value = false ) {
		// Stab stab stab
		$value = ($value !== false) ? $value : $name;

		$this->options[] = array( $name => $value );
	}

	/**
	 * This accepts an array of form
	 * label => value
	 * label => ( label => value, label => value )
	 *
	 * @param  $options
	 */
	public function addOptions( $options ) {
		$this->options[] = $options;
	}

	/**
	 * This accepts an array of form
	 * label => value
	 * label => ( label => value, label => value )
	 *
	 * @param  $options
	 * @param bool $default
	 * @return string
	 */
	static function formatOptions( $options, $default = false ) {
		$data = '';

		foreach( $options as $label => $value ) {
			if ( is_array( $value ) ) {
				$contents = self::formatOptions( $value, $default );
				$data .= Html::rawElement( 'optgroup', array( 'label' => $label ), $contents ) . "\n";
			} else {
				$data .= Xml::option( $label, $value, $value === $default ) . "\n";
			}
		}

		return $data;
	}

	/**
	 * @return string
	 */
	public function getHTML() {
		$contents = '';

		foreach ( $this->options as $options ) {
			$contents .= self::formatOptions( $options, $this->default );
		}

		return Html::rawElement( 'select', $this->attributes, rtrim( $contents ) );
	}
}

/**
 * A wrapper class which causes Xml::encodeJsVar() and Xml::encodeJsCall() to
 * interpret a given string as being a JavaScript expression, instead of string
 * data.
 *
 * Example:
 *
 *    Xml::encodeJsVar( new XmlJsCode( 'a + b' ) );
 *
 * Returns "a + b".
 * @since 1.17
 */
class XmlJsCode {
	public $value;

	function __construct( $value ) {
		$this->value = $value;
	}
}
