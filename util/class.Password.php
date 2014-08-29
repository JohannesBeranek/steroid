<?
/**
 * Used to encapsulate passwords, so they don't end up getting printed in the log
 */
class Password implements JsonSerializable {
	private $password;
	
	/**
	 * @param string $password
	 */
	public function __construct( $password ) {
		$this->password = (string)$password;
	}
	
	public function jsonSerialize () {
		return $this->password;	
	}
	
	public function getValue() {
		return $this->password;
	}
	
	// DO NOT IMPLEMENT __toString here, as otherwise it will end up in exception messages!
}