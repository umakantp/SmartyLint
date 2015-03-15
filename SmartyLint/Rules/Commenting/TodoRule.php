<?php
/**
 * Rules_Commenting_TodoRule.
 *
 * Warns about TODO comments.
 *
 * @package   SmartyLint
 * @author    Umakant Patil <me@umakantpatil.com>
 * @copyright 2013-14 Umakant Patil
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      https://github.com/umakantp/SmartyLint
 */
class Rules_Commenting_TodoRule implements SmartyLint_Rule {

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
            if (preg_match('|[^a-z]+todo[^a-z]+(.*)|i', $c, $matches) !== 0) {
                $type = 'CommentFound';
                $todoMessage = trim($matches[1]);
                $todoMessage = trim($todoMessage, '[]().*'.$smartylFile->rDelimiter);
                $error       = 'Comment refers to a TODO task';
                $data        = array($todoMessage);
                if ($todoMessage !== '') {
                    $type   = 'TaskFound';
                    $error .= ' "%s"';
                }

                if (isset($tokens[$stackPtr]['start'])) {
                    $l = $tokens[$stackPtr]['start'];
                } else {
                    $l = $tokens[$stackPtr]['line'];
                }
                $smartylFile->addWarning($error, array($l, $cLine), $type, $data);
            }
            $cLine++;
        }
    }
}
