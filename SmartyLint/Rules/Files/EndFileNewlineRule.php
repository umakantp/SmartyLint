<?php
/**
 * Rules_Files_EndFileNewlineRule.
 *
 * Ensures the file ends with a newline character.
 *
 * @package   SmartyLint
 * @author    Umakant Patil <me@umakantpatil.com>
 * @copyright 2013-14 Umakant Patil
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      https://github.com/umakantp/SmartyLint
 */
class Rules_Files_EndFileNewlineRule implements SmartyLint_Rule {

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

        // Skip to the end of the file.
        $tokens   = $smartylFile->getTokens();
        $stackPtr = ($smartylFile->numTokens - 1);

        if ($tokens[$stackPtr]['type'] !== 'NEW_LINE') {
            $error = 'Expected 1 newline at end of file; 0 found';
            $smartylFile->addError($error, $stackPtr, 'NoneFound');
            return;
        }

        // Go looking for the last non-empty line.
        $lastLine = $tokens[$stackPtr]['line'];
        while ($tokens[$stackPtr]['type'] === 'NEW_LINE' || $tokens[$stackPtr]['type'] === 'SPACE' || $tokens[$stackPtr]['type'] === 'TAB') {
            $stackPtr--;
        }

        $lastCodeLine = $tokens[$stackPtr]['line'];
        $blankLines   = ($lastLine - $lastCodeLine);
        if ($blankLines > 0) {
            $error = 'Expected 1 blank line at end of file; %s found';
            $data  = array($blankLines + 1);
            $smartylFile->addError($error, $stackPtr, 'TooMany', $data);
        }
    }
}
