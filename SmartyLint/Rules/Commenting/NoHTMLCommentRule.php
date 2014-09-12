<?php
/**
 * Rules_Commenting_NoHTMLCommentRule.
 *
 * Warns about HTML comments.
 *
 * @package   SmartyLint
 * @author    Umakant Patil <me@umakantpatil.com>
 * @copyright 2013-14 Umakant Patil
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      https://github.com/umakantp/SmartyLint
 */
class Rules_Commenting_NoHTMLCommentRule implements SmartyLint_Rule {

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register() {
        return array('HTML_COMMENT');
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
        if (isset($tokens[$stackPtr]['multi'])) {
            $line = $tokens[$stackPtr]['start'];
        } else {
            $line = $tokens[$stackPtr]['line'];
        }

        $error = 'Don\'t use HTML Comments. Use smarty comments '.$smartylFile->sDelimiter.'* *'.$smartylFile->eDelimiter;
        $smartylFile->addWarning($error, array(0, $line), 'HTMLCommentFound');
    }
}
