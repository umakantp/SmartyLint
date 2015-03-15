<?php
/**
 * A SmartyLint_File object represents a PHP source file and the tokens
 * associated with it.
 *
 * It provides a means for traversing the token stack, along with
 * other token related operations. If a SmartyLint_Rule finds and error or
 *  warning within a SmartyLint_File, you can raise an error using the
 *  addError() or addWarning() methods.
 *
 * @package   SmartyLint
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @author    Umakant Patil <me@umakantpatil.com>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      https://github.com/umakantp/SmartyLint
 */
class SmartyLint_File {

    /**
     * The absolute path to the file associated with this object.
     *
     * @var string
     */
    private $_file = '';

    /**
     * The EOL character this file uses.
     *
     * @var string
     */
    public $eolChar = '';

    /**
     * Left delimiter for Smarty.
     *
     * @var string
     */
    public $lDelimiter = '';

    /**
     * Right delimiter for Smarty.
     *
     * @var string
     */
    public $rDelimiter = '';

    /**
     * Auto literal settings for Smarty.
     *
     * @var string
     */
    public $autoLiteral = true;

    /**
     * The SmartyLint object controlling this run.
     *
     * @var SmartyLint
     */
    public $smartyl = null;

    /**
     * The number of tokens in this file.
     *
     * Stored here to save calling count() everywhere.
     *
     * @var int
     */
    public $numTokens = 0;

    /**
     * The tokens stack map.
     *
     * Note that the tokens in this array differ in format to the tokens
     * produced by Smarty tokenizer. Tokens are initially produced with
     * Smarty tokenizer, then augmented so that it's easier to process them.
     *
     * @var array()
     * @see Tokens.php
     */
    private $_tokens = array();

    /**
     * The errors raised from SmartyLint_Rules.
     *
     * @var array()
     * @see getErrors()
     */
    private $_errors = array();

    /**
     * The warnings raised form SmartyLint_Rules.
     *
     * @var array()
     * @see getWarnings()
     */
    private $_warnings = array();

    /**
     * Record the errors raised.
     *
     * @var bool
     */
    private $_recordErrors = true;

    /**
     * The total number of errors raised.
     *
     * @var int
     */
    private $_errorCount = 0;

    /**
     * The total number of warnings raised.
     *
     * @var int
     */
    private $_warningCount = 0;

    /**
     * An array of rules listening to this file's processing.
     *
     * @var array(SmartyLint_Rule)
     */
    private $_listeners = array();

    /**
     * The class name of the rule currently processing the file.
     *
     * @var string
     */
    private $_activeListener = '';

    /**
     * Constructs a SmartyLint_File.
     *
     * @param string        $file      The absolute path to the file to process.
     * @param array(string) $listeners The initial listeners listening
     *                                 to processing of this file.
     * @param SmartyLint    $smartyl   The SmartyLint object controlling
     *                                 this run.
     *
     * @throws SmartyLint_Exception If the register() method does
     *                              not return an array.
     */
    public function __construct(
        $file,
        array $listeners,
        SmartyLint $smartyl
    ) {
        $this->_file = trim($file);
        $this->_listeners = $listeners;
        $this->smartyl = $smartyl;
        $this->lDelimiter = $smartyl->leftDelimiter;
        $this->rDelimiter = $smartyl->rightDelimiter;
        $this->autoLiteral = $smartyl->autoLiteral;
    }

    /**
     * Sets the name of the currently active rule.
     *
     * @param string $activeListener The class name of the current rule.
     *
     * @return void
     */
    public function setActiveListener($activeListener) {
        $this->_activeListener = $activeListener;

    }

    /**
     * Adds a listener to the token stack that listens to the specific tokens.
     *
     * When SmartyLint encounters on the the tokens specified in $tokens,
     * it invokes the process method of the rule.
     *
     * @param SmartyLint_Rule $listener The listener to add to the
     *                                   listener stack.
     * @param array(int)       $tokens   The token types the listener wishes to
     *                                   listen to.
     *
     * @return void
     */
    public function addTokenListener(SmartyLint_Rule $listener, array $tokens) {
        foreach ($tokens as $token) {
            if (isset($this->_listeners[$token]) === false) {
                $this->_listeners[$token] = array();
            }

            if (in_array($listener, $this->_listeners[$token], true) === false) {
                $this->_listeners[$token][] = $listener;
            }
        }
    }

    /**
     * Removes a listener from listening from the specified tokens.
     *
     * @param SmartyLint_Rule $listener The listener to remove from the
     *                                   listener stack.
     * @param array(int)       $tokens   The token types the listener wishes to
     *                                   stop listen to.
     *
     * @return void
     */
    public function removeTokenListener(
        SmartyLint_Rule $listener,
        array $tokens
    ) {
        foreach ($tokens as $token) {
            if (isset($this->_listeners[$token]) === false) {
                continue;
            }

            if (in_array($listener, $this->_listeners[$token]) === true) {
                foreach ($this->_listeners[$token] as $pos => $value) {
                    if ($value === $listener) {
                        unset($this->_listeners[$token][$pos]);
                    }
                }
            }
        }
    }

    /**
     * Returns the token stack for this file.
     *
     * @return array()
     */
    public function getTokens() {
        return $this->_tokens;
    }

    /**
     * Starts the stack traversal and tells listeners when tokens are found.
     *
     * @param string $contents The contents to parse. If NULL, the content
     *                         is taken from the file system.
     *
     * @return void
     */
    public function start($contents=null) {
        $this->_parse($contents);

        // Foreach of the listeners that have registered to listen for this
        // token, get them to process it.
        foreach ($this->_tokens as $stackPtr => $token) {
            // Check for ignored lines.
            if ($token['type'] === 'COMMENT' || $token['type'] === 'DOC_COMMENT') {
                if (strpos($token['content'], '@noSmartyLint') !== false) {
                    // Ignoring the whole file, just a little late.
                    $this->_errors = array();
                    $this->_warnings = array();
                    $this->_errorCount = 0;
                    $this->_warningCount = 0;
                    return;
                }
            }

            $tokenType = $token['type'];

            if (isset($this->_listeners[$tokenType]) === false) {
                continue;
            }

            foreach ($this->_listeners[$tokenType] as $listenerData) {
                $listener = $listenerData['listener'];
                $class = $listenerData['class'];

                // If the file path matches one of our ignore patterns, skip it.
                $parts = explode('_', $class);
                if (isset($parts[2]) === true) {
                    $source = $parts[1].'.'.substr($parts[2], 0, -4);
                    $patterns = $this->smartyl->getIgnorePatterns($source);
                    foreach ($patterns as $pattern) {
                        // While there is support for a type of each pattern
                        // (absolute or relative) we don't actually support it here.
                        $replacements = array(
                                '\\,' => ',',
                                '*'   => '.*',
                            );

                        $pattern = strtr($pattern, $replacements);
                        if (preg_match("|{$pattern}|i", $this->_file) === 1) {
                            continue(2);
                        }
                    }
                }



                $this->setActiveListener($class);

                $listener->process($this, $stackPtr);

                $this->_activeListener = '';
            }
        }
    }

    /**
     * Remove vars stored in this rule that are no longer required.
     *
     * @return void
     */
    public function cleanUp() {
        $this->_tokens = null;
        $this->_listeners = null;
    }

    /**
     * Tokenizes the file and prepares it for the test run.
     *
     * @param string $contents The contents to parse. If NULL, the content
     *                         is taken from the file system.
     *
     * @return void
     */
    private function _parse($contents = null) {
        try {
            $this->eolChar = self::detectLineEndings($this->_file, $contents);
        } catch (SmartyLint_Exception $e) {
            $this->addError($e->getMessage(), null, 'Internal.DetectLineEndings');
            return;
        }

        $tokenizer = new SmartyLint_Tokenizer_Smarty();

        if ($contents === null) {
            $contents = file_get_contents($this->_file);
        }

        $this->_tokens = self::tokenizeString(
                $contents,
                $tokenizer,
                $this->eolChar,
                $this->lDelimiter,
                $this->rDelimiter,
                $this->autoLiteral
            );
        $this->numTokens = count($this->_tokens);

        // Check for mixed line endings as these can cause tokenizer errors and we
        // should let the user know that the results they get may be incorrect.
        // This is done by removing all backslashes, removing the newline char we
        // detected, then converting newlines chars into text. If any backslashes
        // are left at the end, we have additional newline chars in use.
        $contents = str_replace('\\', '', $contents);
        $contents = str_replace($this->eolChar, '', $contents);
        $contents = str_replace("\n", '\n', $contents);
        $contents = str_replace("\r", '\r', $contents);
        if (strpos($contents, '\\') !== false) {
            $error = 'File has mixed line endings; this may cause incorrect results';
            $this->addError($error, 0, 'Internal.LineEndings.Mixed');
        }
    }

    /**
     * Opens a file and detects the EOL character being used.
     *
     * @param string $file     The full path to the file.
     * @param string $contents The contents to parse. If NULL, the content
     *                         is taken from the file system.
     *
     * @return string
     * @throws SmartyLint_Exception If $file could not be opened.
     */
    public static function detectLineEndings($file, $contents=null) {
        if ($contents === null) {
            // Determine the newline character being used in this file.
            // Will be either \r, \r\n or \n.
            if (is_readable($file) === false) {
                $error = 'Error opening file; file no longer exists or you do not have access to read the file';
                throw new SmartyLint_Exception($error);
            } else {
                $handle = fopen($file, 'r');
                if ($handle === false) {
                    $error = 'Error opening file; could not auto-detect line endings';
                    throw new SmartyLint_Exception($error);
                }
            }
            $firstLine = fgets($handle);
            fclose($handle);

            $eolChar = substr($firstLine, -1);
            if ($eolChar === "\n") {
                $secondLastChar = substr($firstLine, -2, 1);
                if ($secondLastChar === "\r") {
                    $eolChar = "\r\n";
                }
            } else if ($eolChar !== "\r") {
                // Must not be an EOL char at the end of the line.
                // Probably a one-line file, so assume \n as it really
                // doesn't matter considering there are no newlines.
                $eolChar = "\n";
            }
        } else {
            if (preg_match("/\r\n?|\n/", $contents, $matches) !== 1) {
                // Assuming there are no newlines.
                $eolChar = "\n";
            } else {
                $eolChar = $matches[0];
            }
        }

        return $eolChar;
    }

    /**
     * Adds an error to the error stack.
     *
     * @param string         $error    The error message.
     * @param int|array(int) $stackPtr The stack position where the error occured.
     *                                 Array if token was multiline.
     * @param string         $code     A violation code unique to the rule message.
     * @param array          $data     Replacements for the error message.
     *
     * @return void
     */
    public function addError($error, $stackPtr, $code='', $data=array()) {
        // Work out which rule generated the error.
        if (substr($code, 0, 9) === 'Internal.') {
            // Any internal message.
            $rule = $code;
        } else {
            $parts = explode('_', $this->_activeListener);


            if (isset($parts[2]) === true) {
                $rule = $parts[1].'.'.$parts[2];

                // Remove "Rule" from the end.
                $rule = substr($rule, 0, -4);
            } else {
                $rule = 'unknownRule';
            }

            if ($code !== '') {
                $rule .= '.'.$code;
            }
        }

        // Make sure we are not ignoring this file.
        $patterns = $this->smartyl->getIgnorePatterns($rule);
        foreach ($patterns as $pattern) {
            // While there is support for a type of each pattern
            // (absolute or relative) we don't actually support it here.
            $replacements = array(
                    '\\,' => ',',
                    '*'   => '.*',
                );
            $pattern = strtr($pattern, $replacements);
            if (preg_match("|{$pattern}|i", $this->_file) === 1) {
                return;
            }
        }

        // Make sure this rule is globally disabled.
        if ($this->smartyl->getIgnoreRules($rule)) {
            return;
        }

        $this->_errorCount++;
        if ($this->_recordErrors === false) {
            return;
        }

        if (empty($data) === true) {
            $message = $error;
        } else {
            $message = vsprintf($error, $data);
        }

        if ($stackPtr === null) {
            $lineNum = 1;
            $column  = 1;
        } else {
            if (is_array($stackPtr)) {
                // This is happens when token has multiple lines.
                $lineNum = $stackPtr[0] + $stackPtr[1];
            } else {
                $lineNum = $this->_tokens[$stackPtr]['line'];
            }
            $column = 1;
            //$column  = $this->_tokens[$stackPtr]['column'];
        }

        if (isset($this->_errors[$lineNum]) === false) {
            $this->_errors[$lineNum] = array();
        }

        if (isset($this->_errors[$lineNum][$column]) === false) {
            $this->_errors[$lineNum][$column] = array();
        }

        $this->_errors[$lineNum][$column][] = array(
                'message'  => $message,
                'source'   => $rule
            );
    }

    /**
     * Adds an warning to the warning stack.
     *
     * @param string         $warning  The error message.
     * @param int|array(int) $stackPtr The stack position where the error occured.
     *                                 Array if token was multiline.
     * @param string         $code     A violation code unique to the rule message.
     * @param array          $data     Replacements for the warning message.
     *
     * @return void
     */
    public function addWarning($warning, $stackPtr, $code='', $data=array()) {
        // Work out which rule generated the warning.
        if (substr($code, 0, 9) === 'Internal.') {
            // Any internal message.
            $rule = $code;
        } else {
            $parts = explode('_', $this->_activeListener);
            if (isset($parts[2]) === true) {
                $rule = $parts[1].'.'.$parts[2];

                // Remove "Rule" from the end.
                $rule = substr($rule, 0, -4);
            } else {
                $rule = 'unknownRule';
            }

            if ($code !== '') {
                $rule .= '.'.$code;
            }
        }

        // Make sure we are not ignoring this file.
        $patterns = $this->smartyl->getIgnorePatterns($rule);
        foreach ($patterns as $pattern) {
            // While there is support for a type of each pattern
            // (absolute or relative) we don't actually support it here.
            $replacements = array(
                             '\\,' => ',',
                             '*'   => '.*',
                            );

            $pattern = strtr($pattern, $replacements);
            if (preg_match("|{$pattern}|i", $this->_file) === 1) {
                return;
            }
        }

        // Make sure this rule is globally disabled.
        if ($this->smartyl->getIgnoreRules($rule)) {
            return;
        }

        $this->_warningCount++;
        if ($this->_recordErrors === false) {
            return;
        }

        if (empty($data) === true) {
            $message = $warning;
        } else {
            $message = vsprintf($warning, $data);
        }

        if ($stackPtr === null) {
            $lineNum = 1;
            $column  = 1;
        } else {
            if (is_array($stackPtr)) {
                // This is happens when token has multiple lines.
                $lineNum = $stackPtr[0] + $stackPtr[1];
            } else {
                $lineNum = $this->_tokens[$stackPtr]['line'];
            }
            $column = 1;
            //$column  = $this->_tokens[$stackPtr]['column'];
        }

        if (isset($this->_warnings[$lineNum]) === false) {
            $this->_warnings[$lineNum] = array();
        }

        if (isset($this->_warnings[$lineNum][$column]) === false) {
            $this->_warnings[$lineNum][$column] = array();
        }

        $this->_warnings[$lineNum][$column][] = array(
                'message'  => $message,
                'source'   => $rule
            );
    }

    /**
     * Returns the number of errors raised.
     *
     * @return int
     */
    public function getErrorCount() {
        return $this->_errorCount;
    }

    /**
     * Returns the number of warnings raised.
     *
     * @return int
     */
    public function getWarningCount() {
        return $this->_warningCount;
    }

    /**
     * Returns the errors raised from processing this file.
     *
     * @return array
     */
    public function getErrors() {
        return $this->_errors;
    }

    /**
     * Returns the warnings raised from processing this file.
     *
     * @return array
     */
    public function getWarnings() {
        return $this->_warnings;
    }

    /**
     * Returns the absolute filename of this file.
     *
     * @return string
     */
    public function getFilename() {
        return $this->_file;
    }

    /**
     * Creates an array of tokens when given some SMARTY code.
     *
     * @param string $string    The string to tokenize.
     * @param object $tokenizer A tokenizer class to use to tokenize the string.
     * @param string $eolChar   The EOL character to use for splitting strings.
     * @param string $sD        Start delimiter used to tokenize Smarty strings.
     * @param string $eD        End delimiter used to tokenize Smarty strings.
     *
     * @return array
     */
    public static function tokenizeString($string, $tokenizer, $eolChar='\n', $sD, $eD) {
        return $tokenizer->tokenizeString($string, $eolChar, $sD, $eD);
    }

    /**
     * Returns the position of the next specified token(s).
     *
     * If a value is specified, the next token of the specified type(s)
     * containing the specified value will be returned.
     *
     * Returns false if no token can be found.
     *
     * @param int|array $types   The type(s) of tokens to search for.
     * @param int       $start   The position to start searching from in the
     *                           token stack.
     * @param int       $end     The end position to fail if no token is found.
     *                           if not specified or null, end will default to
     *                           the start of the token stack.
     * @param bool      $exclude If true, find the next token that are NOT of
     *                           the types specified in $types.
     * @param string    $value   The value that the token(s) must be equal to.
     *                           If value is ommited, tokens with any value will
     *                           be returned.
     *
     * @return int | bool
     * @see findNext()
     */
    public function findPrevious(
        $types,
        $start,
        $end=null,
        $exclude=false,
        $value=null
    ) {
        $types = (array) $types;

        if ($end === null) {
            $end = 0;
        }

        for ($i = $start; $i >= $end; $i--) {
            $found = (bool) $exclude;
            foreach ($types as $type) {
                if ($this->_tokens[$i]['type'] === $type) {
                    $found = !$exclude;
                    break;
                }
            }

            if ($found === true) {
                if ($value === null) {
                    return $i;
                } else if ($this->_tokens[$i]['content'] === $value) {
                    return $i;
                }
            }

        }

        return false;
    }
}
