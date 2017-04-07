<?php
/**
 * An exception to be thrown when a FileCommentParser finds an anomilty in a
 * doc comment.
 *
 * @package   SmartyLint
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      https://github.com/umakantp/SmartyLint
 */
class SmartyLint_CommentParser_ParserException extends Exception
{

    /**
     * The line where the exception occured, in relation to the doc comment.
     *
     * @var int
     */
    private $_line = 0;

    /**
     * Constructs a ParserException.
     *
     * @param string $message The message of the exception.
     * @param int    $line    The position in comment where the error occured.
     *                        A position of 0 indicates that the error occured
     *                        at the opening line of the doc comment.
     */
    public function __construct($message, $line)
    {
        parent::__construct($message);
        $this->_line = $line;
    }

    /**
     * Returns the line number within the comment where the exception occured.
     *
     * @return int
     */
    public function getLineWithinComment()
    {
        return $this->_line;
    }
}
