<?php
/**
 * Rules_Whitespace_SuperfluousWhitespaceRule.
 *
 * Checks that no whitespace at the end of each line and no two empty lines in the content.
 *
 * @package   SmartyLint
 * @author    Umakant Patil <me@umakantpatil.com>
 * @copyright 2013-15 Umakant Patil
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      https://github.com/umakantp/SmartyLint
 */
class Rules_Whitespace_SuperfluousWhitespaceRule implements SmartyLint_Rule {

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
        $tokens = $smartylFile->getTokens();

        $beforeNewLine = false;
        if (isset($tokens[($stackPtr - 1)])) {
            $beforeNewLine = $tokens[($stackPtr - 1)]['type'];
            if ($beforeNewLine == 'TAB' || $beforeNewLine == 'SPACE') {
                $smartylFile->addError('Whitespace found at end of line', $stackPtr, 'EndFile');
            }
        }

        $newLinesFound = 1;
        for ($i = ($stackPtr-1); $i >= 0; $i--) {
            if (isset($tokens[$i]) && $tokens[$i]['type'] == 'NEW_LINE') {
                $newLinesFound++;
            } else {
                break;
            }
        }

        if ($newLinesFound > 3) {
            $error = 'Found %s empty lines in a row.';
            $data  = array($newLinesFound);
            $smartylFile->addError($error, ($stackPtr - $newLinesFound), 'EmptyLines', $data);
        }
    }
}
