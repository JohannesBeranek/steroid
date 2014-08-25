<?
  /**
   * Squiloople Framework
   *
   * LICENSE: Feel free to use and redistribute this code.
   *
   * @author Michael Rushton <michael@squiloople.com>
   * @link <!-- m --><a class="postlink" href="http://squiloople.com/">http://squiloople.com/</a><!-- m -->
   * @category Squiloople
   * @package Models
   * @subpackage Validators
   * @version 1.0
   * @copyright Copyright © 2009-2010 Michael Rushton
   */
   
  /**
   * Email Address Validator
   *
   * Validate email addresses according to RFC 5321 or RFC 5322
   */
  final class EmailAddressValidator
  {

    /**
     * The email address to validate
     *
     * @access private
     * @var string $_emailAddress
     */
    private $_emailAddress;

    /**
     * A quoted string local part is either allowed (true) or not (false)
     *
     * @access private
     * @var bool $_quotedString
     */
    private $_quotedString = false;

    /**
     * An obsolete local part is either allowed (true) or not (false)
     *
     * @access private
     * @var bool $_obsolete
     */
    private $_obsolete = false;

    /**
     * A domain literal domain is either allowed (true) or not (false)
     *
     * @access private
     * @var bool $_domainLiteral
     */
    private $_domainLiteral = false;

   /**
     * Comments and folding white spaces are either allowed (true) or not (false)
     *
     * @access private
     * @var bool $_cfws
     */
    private $_cfws = false;

    /**
     * Set the email address and turn on the relevant standard if required
     *
     * @access public
     * @param string $emailAddress
     * @param integer $standard
     */
    public function __construct($emailAddress, $standard = null)
    {

      // Set the email address
      $this->_emailAddress = $emailAddress;

      // Turn on the RFC 5321 standard if requested
      if ($standard == 5321)
      {
        $this->setStandard5321();
      }

      // Otherwise turn on the RFC 5322 standard if requested
      if ($standard == 5322)
      {
        $this->setStandard5322();
      }

    }

    /**
     * Call the constructor fluently
     *
     * @access public
     * @static
     * @param string $emailAddress
     * @param integer $standard
     * @return EmailAddressValidator
     */
    public static function setEmailAddress($emailAddress, $standard = null)
    {
      return new self($emailAddress, $standard);
    }

    /**
     * Validate the email address according to RFC 5321 and return itself
     *
     * @access public
     * @param bool $allow
     * @return EmailAddressValidator
     */
    public function setStandard5321($allow = true)
    {

      // A quoted string local part is either allowed (true) or not (false)
      $this->_quotedString = $allow;

      // A domain literal domain is either allowed (true) or not (false)
      $this->_domainLiteral = $allow;

      // Return itself
      return $this;

    }

    /**
     * Validate the email address according to RFC 5322 and return itself
     *
     * @access public
     * @param bool $allow
     * @return EmailAddressValidator
     */
    public function setStandard5322($allow = true)
    {

      // An obsolete local part is either allowed (true) or not (false)
      $this->_obsolete = $allow;

      // A domain literal domain is either allowed (true) or not (false)
      $this->_domainLiteral = $allow;

      // Comments and folding white spaces are either allowed (true) or not (false)
      $this->_cfws = $allow;

      // Return itself
      return $this;

    }

    /**
     * Either allow (true) or disallow (false) a quoted string local part and return itself
     *
     * @access public
     * @param bool $allow
     * @return EmailAddressValidator
     */
    public function setQuotedString($allow = true)
    {

      // Either allow (true) or disallow (false) a quoted string local part
      $this->_quotedString = $allow;

      // Return itself
      return $this;

    }

    /**
     * Either allow (true) or disallow (false) an obsolete local part and return itself
     *
     * @access public
     * @param bool $allow
     * @return EmailAddressValidator
     */
    public function setObsolete($allow = true)
    {

      // Either allow (true) or disallow (false) an obsolete local part
      $this->_obsolete = $allow;

      // Return itself
      return $this;

    }

    /**
     * Either allow (true) or disallow (false) a domain literal domain and return itself
     *
     * @access public
     * @param bool $allow
     * @return EmailAddressValidator
     */
    public function setDomainLiteral($allow = true)
    {

      // Either allow (true) or disallow (false) a domain literal domain
      $this->_domainLiteral = $allow;

      // Return itself
      return $this;

    }

    /**
     * Either allow (true) or disallow (false) comments and folding white spaces and return itself
     *
     * @access public
     * @param bool $allow
     * @return EmailAddressValidator
     */
    public function setCFWS($allow = true)
    {

      // Either allow (true) or disallow (false) comments and folding white spaces
      $this->_cfws = $allow;

      // Return itself
      return $this;

    }

    /**
     * Return the regular expression for a dot atom local part
     *
     * @access private
     * @return string
     */
    private function _getDotAtom()
    {
      return "([!#-'*+\/-9=?^-~-]+)(?>\.(?1))*";
    }

    /**
     * Return the regular expression for a quoted string local part
     *
     * @access private
     * @return string
     */
    private function _getQuotedString()
    {
      return '"(?>[ !#-\[\]-~]|\\\[ -~])*"';
    }

    /**
     * Return the regular expression for an obsolete local part
     *
     * @access private
     * @return string
     */
    private function _getObsolete()
    {

      return '([!#-\'*+\/-9=?^-~-]+|"(?>'
        . $this->_getFWS()
        . '(?>[\x01-\x08\x0B\x0C\x0E-!#-\[\]-\x7F]|\\\[\x00-\x7F]))*'
        . $this->_getFWS()
        . '")(?>'
        . $this->_getCFWS()
        . '\.'
        . $this->_getCFWS()
        . '(?1))*';

    }

    /**
     * Return the regular expression for a domain name domain
     *
     * @access private
     * @return string
     */
    private function _getDomainName()
    {

      return '([a-z0-9](?>[a-z0-9-]*[a-z0-9])?)(?>'
        . $this->_getCFWS()
        . '\.'
        . $this->_getCFWS()
        . '(?2)){0,126}';

    }

    /**
     * Return the regular expression for an IPv6 address
     *
     * @access private
     * @return string
     */
    private function _getIPv6()
    {
      return '([a-f0-9]{1,4})(?>:(?3)){7}|(?!(?:.*[a-f0-9][:\]]){8,})((?3)(?>:(?3)){0,6})?::(?4)?';
    }

    /**
     * Return the regular expression for an IPv4-mapped IPv6 address
     *
     * @access private
     * @return string
     */
    private function _getIPv6v4()
    {
      return '(?3)(?>:(?3)){5}:|(?!(?:.*[a-f0-9]:){6,})(?5)?::(?>((?3)(?>:(?3)){0,4}):)?';
    }

    /**
     * Return the regular expression for an IPv4 address
     *
     * @access private
     * @return string
     */
    private function _getIPv4()
    {
      return '(25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])(?>\.(?6)){3}';
    }

    /**
     * Return the regular expression for a domain literal domain
     *
     * @access private
     * @return string
     */
    private function _getDomainLiteral()
    {

      return '\[(?:(?>IPv6:(?>'
        . $this->_getIPv6()
        . '))|(?>(?>IPv6:(?>'
        . $this->_getIPv6v4()
        . '))?'
        . $this->_getIPv4()
        . '))\]';

    }

    /**
     * Return either the regular expression for folding white spaces or its backreference if allowed
     *
     * @access private
     * @var bool $define
     * @return string
     */
    private function _getFWS($define = false)
    {

      // Return the backreference if $define is set to false otherwise return the regular expression
      if ($this->_cfws)
      {
        return !$define ? '(?P>fws)' : '(?<fws>(?>[	 ]+(?>\x0D\x0A[	 ]+)*)?)';
      }

    }

    /**
     * Return the regular expression for comments
     *
     * @access private
     * @return string
     */
    private function _getComments()
    {

      return '(?<comment>\((?>'
        . $this->_getFWS()
        . '(?>[\x01-\x08\x0B\x0C\x0E-\'*-\[\]-\x7F]|\\\[\x00-\x7F]|(?P>comment)))*'
        . $this->_getFWS()
        . '\))';

    }

    /**
     * Return either the regular expression for comments and folding white spaces or its backreference if allowed
     *
     * @access private
     * @var bool $define
     * @return string
     */
    private function _getCFWS($define = false)
    {

      // Return the backreference if $define is set to false
      if ($this->_cfws && !$define)
      {
        return '(?P>cfws)';
      }

      // Otherwise return the regular expression
      if ($this->_cfws)
      {

        return '(?<cfws>(?>(?>(?>'
          . $this->_getFWS(true)
          . $this->_getComments()
          . ')+'
          . $this->_getFWS()
          . ')|'
          . $this->_getFWS()
          . ')?)';

      }

    }

    /**
     * Establish, and return, the valid format for the local part
     *
     * @access private
     * @return string
     */
    private function _getLocalPart()
    {

      // The local part may be obsolete if allowed
      if ($this->_obsolete)
      {
        return $this->_getObsolete();
      }

      // Or the local part may be either a dot atom or a quoted string if the latter is allowed
      if ($this->_quotedString)
      {
        return '(?>' . $this->_getDotAtom() . '|' . $this->_getQuotedString() . ')';
      }

      // Otherwise the local part may only be a dot atom
      return $this->_getDotAtom();

    }

    /**
     * Establish, and return, the valid format for the domain
     *
     * @access private
     * @return string
     */
    private function _getDomain()
    {

      // The domain may be either a domain name or a domain literal if the latter is allowed
      if ($this->_domainLiteral)
      {
        return '(?>' . $this->_getDomainName() . '|' . $this->_getDomainLiteral() . ')';
      }

      // Otherwise the domain must be a domain name
      return $this->_getDomainName();

    }

    /**
     * Check to see if the domain can be resolved to MX RRs
     *
     * @access private
     * @param array $domain
     * @return bool
     */
    private function _verifyDomain($domain)
    {

      // Return false if the domain cannot be resolved to MX RRs
      if (!checkdnsrr(end($domain), 'MX'))
      {
        return false;
      }

      // Otherwise return true
      return true;

    }

    /**
     * Perform the validation check on the email address's syntax and, if required, call _verifyDomain()
     *
     * @access public
     * @param bool $verify
     * @return bool|integer
     */

    public function validate($verify = false)
    {

      // Return false if the email address has an incorrect syntax
      if (!preg_match(

          '/^'
        . $this->_getCFWS()
        . $this->_getLocalPart()
        . $this->_getCFWS()
        . '@'
        . $this->_getCFWS()
        . $this->_getDomain()
        . $this->_getCFWS(true)
        . '$/isD'
        , $this->_emailAddress

      ))
      {
        return false;
      }

      // Otherwise check to see if the domain can be resolved to MX RRs if required
      if ($verify)
      {

        // Return 0 if the domain cannot be resolved to MX RRs
        if (!$this->_verifyDomain(explode('@', $this->_emailAddress)))
        {
          return 0;
        }

        // Otherwise return true
        return true;

      }

      // Otherwise return 1
      return 1;

    }

  }