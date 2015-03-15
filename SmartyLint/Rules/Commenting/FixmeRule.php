<?php
/**
 * Rules_Commenting_FixmeRule.
 *
 * Warns about FIXME comments.
 *
 * @package   SmartyLint
 * @author    Umakant Patil <me@umakantpatil.com>
 * @copyright 2013-15 Umakant Patil
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      https://github.com/umakantp/SmartyLint
 */
class Rules_Commenting_FixmeRule implements SmartyLint_Rule {

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register() {
        return array('SMARTY_COMMENT', 'SMARTY_DOC_COMMENT', 'HTML_COMMENT');
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

        $content = $tokens[$stackPtr]['content'];

        if (isset($tokens[$stackPtr]['multi']) && $tokens[$stackPtr]['multi'] == true) {
            $content = explode($smartylFile->eolChar, $content);
        } else {
            $content = array($content);
        }

        $cLine = 0;
        foreach ($content as $c) {
            $matches = array();
            if (preg_match('|[^a-z]+fixme[^a-z]+(.*)|i', $c, $matches) !== 0) {
                // Clear whitespace and some common characters not required at
                // the end of a fixme message to make the warning more informative.
                $type = 'CommentFound';
                $fixmeMessage = trim($matches[1]);
                $fixmeMessage = trim($fixmeMessage, '[]().*'.$smartylFile->rDelimiter);
                $error = 'Comment refers to a FIXME task';
                $data = array($fixmeMessage);
                if ($fixmeMessage !== '') {
                    $type   = 'TaskFound';
                    $error .= ' "%s"';
                }

                if (isset($tokens[$stackPtr]['start'])) {
                    $l = $tokens[$stackPtr]['start'];
                } else {
                    $l = $tokens[$stackPtr]['line'];
                }
                $smartylFile->addError($error, array($l, $cLine), $type, $data);
            }
            $cLine++;
        }
    }
}
