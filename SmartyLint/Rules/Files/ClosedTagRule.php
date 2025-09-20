<?php
/**
 * Rules_Files_ClosedTagRule.
 * 
 * Ensures that files do not contain unclosed Smarty tags.
 * 
 * @package   SmartyLint
 * @author    Umakant Patil <me@umakantpatil.com>
 * @copyright 2013-15 Umakant Patil
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      https://github.com/umakantp/SmartyLint
 */

class Rules_Files_ClosedTagRule implements SmartyLint_Rule {

    protected const REQUIRED_CLOSING_TAG = [ 'if', 'foreach', 'section', 'while', 'capture', 'block', 'function', 'strip', 'php', 'nocache', 'literal', 'for', 'setfilter' ];
    protected $computedTags = [];

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
    public function process(SmartyLint_File $smartylFile, $stackPtr)
    {
        $tokens = $smartylFile->getTokens();
        $content = $tokens[$stackPtr]['content'];
        if (preg_match('/\{([^\}\s]*)/', $content, $matches)) {
            $this->computedTags[$stackPtr] = $matches[1];
        }
    }

    /**
     * Callback when all tokens have been processed.
     *
     * @param SmartyLint_File $smartylFile The file being scanned.
     *
     * @return void
     */
    public function finish(SmartyLint_File $smartylFile): void {
        $tags = array_filter($this->computedTags, function($tag) {
            return in_array($tag, self::REQUIRED_CLOSING_TAG);
        });
        foreach ($tags as $stackPtr => $tag) {
            $closingTag = array_search('/'.$tag, $this->computedTags);
            if ($closingTag !== false) {
                unset($this->computedTags[$closingTag]);
            } else {
                $smartylFile->addError("Tag '{$tag}' is not closed.", $stackPtr, 'TagNotClosed');
            }
        }
    }
}
