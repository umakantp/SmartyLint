<?php
/**
 * Rules_Whitespace_TagsWhitespaceRule.
 *
 * Checks that there is no white space to the content inside smarty tags.
 *
 * @package   SmartyLint
 * @author    Umakant Patil <me@umakantpatil.com>
 * @copyright 2013-15 Umakant Patil
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      https://github.com/umakantp/SmartyLint
 */
class Rules_Whitespace_TagsWhitespaceRule implements SmartyLint_Rule {

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register() {
        return array('SMARTY');
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

        $data = $tokens[$stackPtr];
        $content = $data['content'];

        if (isset($data['multi']) && $data['multi'] === true) {
            // Don't check anything for multiline smarty content as smarty
            // itself ignores it assuming it is CSS or JavaScript block.
        } else {
            $butOneChar = (strlen($content)-2);
            if (isset($content[$butOneChar]) && $content[$butOneChar] === ' ') {
                $smartylFile->addError('Whitespace found before end of one line smarty tag', $stackPtr, 'Smarty');
            }
        }
    }

}
