<?php
/**
 * A class to represent Comments of a doc comment.
 *
 * Comments are in the following format.
 * <code>
 * /** <--this is the start of the comment.
 *  * This is a short comment description
 *  *
 *  * This is a long comment description
 *  * <-- this is the end of the comment
 *  * @return something
 *  {@/}
 *  </code>
 *
 * Note that the sentence before two newlines is assumed
 * the short comment description.
 *
 * @package   SmartyLint
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      https://github.com/umakantp/SmartyLint
 */
class SmartyLint_CommentParser_CommentElement extends SmartyLint_CommentParser_SingleElement{

    /**
     * Constructs a SmartyLint_CommentParser_CommentElement.
     *
     * @param SmartyLint_CommentParser_DocElemement $previousElement The element
     *                                                               that
     *                                                               appears
     *                                                               before this
     *                                                               element.
     * @param array                                 $tokens          The tokens
     *                                                               that make
     *                                                               up this
     *                                                               element.
     * @param SmartyLinr_File                       $smartylFile     The file
     *                                                               that this
     *                                                               element is
     *                                                               in.
     */
    public function __construct(
        $previousElement,
        $tokens,
        SmartyLint_File $smartylFile
    ) {
        parent::__construct($previousElement, $tokens, 'comment', $smartylFile);
    }

    /**
     * Returns the short comment description.
     *
     * @return string
     * @see getLongComment()
     */
    public function getShortComment() {
        $pos = $this->_getShortCommentEndPos();
        if ($pos === -1) {
            return '';
        }

        return implode('', array_slice($this->tokens, 0, ($pos + 1)));
    }

    /**
     * Returns the last token position of the short comment description.
     *
     * @return int The last token position of the short comment description
     * @see _getLongCommentStartPos()
     */
    private function _getShortCommentEndPos() {
        $found = false;
        $whiteSpace = array(
                ' ',
                "\t",
            );

        foreach ($this->tokens as $pos => $token) {
            $token = str_replace($whiteSpace, '', $token);
            if ($token === $this->smartylFile->eolChar) {
                if (! $found) {
                    // Include newlines before short description.
                    continue;
                } else {
                    if (isset($this->tokens[($pos + 1)])) {
                        if ($this->tokens[($pos + 1)] === $this->smartylFile->eolChar) {
                            return ($pos - 1);
                        }
                    } else {
                        return $pos;
                    }
                }
            } else {
                $found = true;
            }
        }

        return (count($this->tokens) - 1);
    }

    /**
     * Returns the long comment description.
     *
     * @return string
     * @see getShortComment
     */
    public function getLongComment() {
        $start = $this->_getLongCommentStartPos();
        if ($start === -1) {
            return '';
        }

        return implode('', array_slice($this->tokens, $start));
    }

    /**
     * Returns the start position of the long comment description.
     *
     * Returns -1 if there is no long comment.
     *
     * @return int The start position of the long comment description.
     * @see _getShortCommentEndPos()
     */
    private function _getLongCommentStartPos() {
        $pos = ($this->_getShortCommentEndPos() + 1);
        if ($pos === (count($this->tokens) - 1)) {
            return -1;
        }

        $count = count($this->tokens);
        for ($i = $pos; $i < $count; $i++) {
            $content = trim($this->tokens[$i]);
            if ($content !== '') {
                if ($content[0] === '@') {
                    return -1;
                }

                return $i;
            }
        }

        return -1;
    }

    /**
     * Returns the whitespace that exists between
     * the short and the long comment description.
     *
     * @return string
     */
    public function getWhiteSpaceBetween() {
        $endShort  = ($this->_getShortCommentEndPos() + 1);
        $startLong = ($this->_getLongCommentStartPos() - 1);
        if ($startLong === -1) {
            return '';
        }

        return implode(
                '',
                array_slice($this->tokens, $endShort, ($startLong - $endShort))
            );
    }

    /**
     * Returns the number of newlines that exist before the tags.
     *
     * @return int
     */
    public function getNewlineAfter() {
        $long = $this->getLongComment();
        if ($long !== '') {
            $long     = rtrim($long, ' ');
            $long     = strrev($long);
            $newlines = strspn($long, $this->smartylFile->eolChar);
        } else {
            $endShort = ($this->_getShortCommentEndPos() + 1);
            $after    = implode('', array_slice($this->tokens, $endShort));
            $after    = trim($after, ' ');
            $newlines = strspn($after, $this->smartylFile->eolChar);
        }

        return ($newlines / strlen($this->smartylFile->eolChar));
    }

    /**
     * Returns true if there is no comment.
     *
     * @return boolean
     */
    public function isEmpty() {
        return (trim($this->getContent()) === '');
    }
}
