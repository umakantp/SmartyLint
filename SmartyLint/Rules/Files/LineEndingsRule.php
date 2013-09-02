<?php
/**
 * Rules_Files_LineEndingsRule.
 *
 * Checks that end of line characters are correct.
 *
 * @package   SmartyLint
 * @author    Umakant Patil <me@umakantpatil.com>
 * @copyright 2013 Umakant Patil
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      http://smartylint.com
 */
class Rules_Files_LineEndingsRule implements SmartyLint_Rule {

    /**
     * The valid EOL character.
     *
     * @var string
     */
    public $eolChar = '\n';

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register() {
        return array('NEW_LINE');
    }


    /**
     * Processes this rule, when one of its tokens is encountered.
     *
     * @param SmartyLint_File $smartylFile The file being scanned.
     * @param int             $stackPtr    The position of the current token in
     *                                     the stack passed in $tokens.
     *
     * @return void
     */
    public function process(SmartyLint_File $smartylFile, $stackPtr) {
        // We are only interested if this is the first new line in file.
        if ($stackPtr !== 0) {
            if ($smartylFile->findPrevious('NEW_LINE', ($stackPtr - 1)) !== false) {
                return;
            }
        }

        $found = $smartylFile->eolChar;
        $found = str_replace("\n", '\n', $found);
        $found = str_replace("\r", '\r', $found);

        if ($found !== $this->eolChar) {
            // Check for single line files without an EOL. This is a very special
            // case and the EOL char is set to \n when this happens.
            if ($found === '\n') {
                $tokens    = $smartylFile->getTokens();
                $lastToken = ($smartylFile->numTokens - 1);
                if ($tokens[$lastToken]['line'] === 1
                    && $tokens[$lastToken]['content'] !== "\n"
                ) {
                    return;
                }
            }

            $error    = 'End of line character is invalid; expected "%s" but found "%s"';
            $expected = $this->eolChar;
            $expected = str_replace("\n", '\n', $expected);
            $expected = str_replace("\r", '\r', $expected);
            $data     = array(
                         $expected,
                         $found,
                        );
            $smartylFile->addError($error, $stackPtr, 'InvalidEOLChar', $data);
        }
    }
}
