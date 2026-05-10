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

        $leftDelimiter = preg_quote($smartylFile->lDelimiter, '/');
        $rightDelimiter = preg_quote($smartylFile->rDelimiter, '/');
        $pattern = '/' . $leftDelimiter . '([^\s]+?)(?=\s|' . $rightDelimiter . ')/';

        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[1] as $tag) {
                $this->computedTags[] = [
                    'tag' => $tag,
                    'stackPtr' => $stackPtr,
                ];
            }
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
        $requiredClosingTags = array_fill_keys(self::REQUIRED_CLOSING_TAG, true);

        $openTagStack = [];
        foreach ($this->computedTags as $computedTag) {
            $isClosingTag = str_starts_with($computedTag['tag'], '/');
            $tag = $isClosingTag ? substr($computedTag['tag'], 1) : $computedTag['tag'];

            if (! isset($requiredClosingTags[$tag])) {
                continue;
            }

            if (! $isClosingTag) {
                $openTagStack[] = [
                    'tag' => $tag,
                    'stackPtr' => $computedTag['stackPtr'],
                ];
                continue;
            }

            if (empty($openTagStack)) {
                continue;
            }

            $lastOpenTag = end($openTagStack);
            if ($lastOpenTag['tag'] === $tag) {
                array_pop($openTagStack);
                continue;
            }

            $smartylFile->addError("Tag {$lastOpenTag['tag']} is not closed.", $lastOpenTag['stackPtr'], 'TagNotClosed');
            array_pop($openTagStack);
        }

        foreach ($openTagStack as $openTag) {
            $smartylFile->addError("Tag {$openTag['tag']} is not closed.", $openTag['stackPtr'], 'TagNotClosed');
        }

        $this->computedTags = [];
    }
}
