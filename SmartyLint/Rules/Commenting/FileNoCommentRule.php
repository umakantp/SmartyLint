<?php
/**
 * Verifies if the doc comments are present for smarty file.
 *
 * Every smarty file should have doc comments.
 *
 * @package   SmartyLint
 * @author    Umakant Patil <me@umakantpatil.com>
 * @copyright 2013-14 Umakant Patil
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      https://github.com/umakantp/SmartyLint
 */
class Rules_Commenting_FileNoCommentRule implements SmartyLint_Rule {

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
        $tokens = $smartylFile->getTokens();
        $hasDocComments = false;
        for ($tokenCount = 0; $tokenCount < $smartylFile->numTokens; $tokenCount++) {
            if ($tokens[$tokenCount]['type'] == 'DOC_COMMENT') {
                $hasDocComments = true;
            }
        }

        if (!$hasDocComments) {
            $error = 'Missing file doc comment';
            $smartylFile->addError($error, $stackPtr, 'Missing');
        }
    }
}
