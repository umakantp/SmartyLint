<?php
/**
 * Rules_WhiteSpace_DisallowTabIndentRule.
 *
 * Throws errors if tabs are used for indentation.
 *
 * @package   SmartyLint
 * @author    Umakant Patil <me@umakantpatil.com>
 * @copyright 2013-15 Umakant Patil
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      https://github.com/umakantp/SmartyLint
 */
class Rules_Indent_DisallowTabIndentRule implements SmartyLint_Rule {

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register() {
        return array('TAB');
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

        if (strpos($tokens[$stackPtr]['content'], "\t") !== false) {
            $error = 'Spaces must be used to indent lines; tabs are not allowed';
            $smartylFile->addError($error, $stackPtr, 'TabsUsed');
        }
    }
}
