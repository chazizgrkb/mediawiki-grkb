<?php
/**
 * Note: this entire file is a byte-for-byte copy of UserArray.php with
 * s/User/Title/.  If anyone can figure out how to do this nicely with inheri-
 * tance or something, please do so.
 */

/**
 * The TitleArray class only exists to provide the newFromResult method at pre-
 * sent.
 */
abstract class TitleArray implements Iterator {
	/**
	 * @param $res ResultWrapper A SQL result including at least page_namespace and
	 *   page_title -- also can have page_id, page_len, page_is_redirect,
	 *   page_latest (if those will be used).  See Title::newFromRow.
	 * @return TitleArrayFromResult
	 */
	static function newFromResult( $res ) {
		$array = null;
		if ( !wfRunHooks( 'TitleArrayFromResult', array( &$array, $res ) ) ) {
			return null;
		}
		if ( $array === null ) {
			$array = self::newFromResult_internal( $res );
		}
		return $array;
	}

	/**
	 * @param $res ResultWrapper
	 * @return TitleArrayFromResult
	 */
	protected static function newFromResult_internal( $res ) {
		$array = new TitleArrayFromResult( $res );
		return $array;
	}
}

class TitleArrayFromResult extends TitleArray {

	/**
	 * @var ResultWrapper
	 */
	var $res;
	var $key, $current;

	function __construct( $res ) {
		$this->res = $res;
		$this->key = 0;
		$this->setCurrent( $this->res->current() );
	}

	/**
	 * @param $row ResultWrapper
	 * @return void
	 */
	protected function setCurrent( $row ) {
		if ( $row === false ) {
			$this->current = false;
		} else {
			$this->current = Title::newFromRow( $row );
		}
	}

	/**
	 * @return int
	 */
	public function count() {
		return $this->res->numRows();
	}

	#[ReturnTypeWillChange] function current() {
		return $this->current;
	}

	#[ReturnTypeWillChange] function key() {
		return $this->key;
	}

	#[ReturnTypeWillChange] function next() {
		$row = $this->res->next();
		$this->setCurrent( $row );
		$this->key++;
	}

	#[ReturnTypeWillChange] function rewind() {
		$this->res->rewind();
		$this->key = 0;
		$this->setCurrent( $this->res->current() );
	}

	/**
	 * @return bool
	 */
	function valid() {
		return $this->current !== false;
	}
}
