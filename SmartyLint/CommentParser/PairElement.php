<?php
/**
 * A class to represent elements that have a value => comment format.
 *
 * An example of a pair element tag is the \@throws as it has an exception type
 * and a comment on the circumstance of when the exception is thrown.
 *
 * @package   SmartyLint
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      https://github.com/umakantp/SmartyLint
 */
class SmartyLint_CommentParser_PairElement extends SmartyLint_CommentParser_AbstractDocElement
{

    /**
     * The value of the tag.
     *
     * @var string
     */
    private $_value = '';

    /**
     * The comment of the tag.
     *
     * @var string
     */
    private $_comment = '';

    /**
     * The whitespace that exists before the value elem.
     *
     * @var string
     */
    private $_valueWhitespace = '';

    /**
     * The whitespace that exists before the comment elem.
     *
     * @var string
     */
    private $_commentWhitespace = '';


    /**
     * Constructs a SmartyLint_CommentParser_PairElement doc tag.
     *
     * @param SmartyLint_CommentParser_DocElement $previousElement The element
     *                                                             before this
     *                                                             one.
     * @param array                               $tokens          The tokens
     *                                                             that comprise
     *                                                             this element.
     * @param string                              $tag             The tag that
     *                                                             this element
     *                                                             represents.
     * @param SmartyLint_File                     $smartylFile     The file that
     *                                                             this element
     *                                                             is in.
     */
    public function __construct(
        $previousElement,
        $tokens,
        $tag,
        SmartyLint_File $smartylFile
    ) {
        parent::__construct($previousElement, $tokens, $tag, $smartylFile);
    }

    /**
     * Returns the element names that this tag is comprised of, in the order
     * that they appear in the tag.
     *
     * @return array(string)
     * @see processSubElement()
     */
    protected function getSubElements()
    {
        return array(
                'value',
                'comment',
            );
    }

    /**
     * Processes the sub element with the specified name.
     *
     * @param string $name             The name of the sub element to process.
     * @param string $content          The content of this sub element.
     * @param string $whitespaceBefore The whitespace that exists before the
     *                                 sub element.
     *
     * @return void
     * @see getSubElements()
     */
    protected function processSubElement($name, $content, $whitespaceBefore)
    {
        $element = '_'.$name;
        $whitespace = $element.'Whitespace';
        $this->$element = $content;
        $this->$whitespace = $whitespaceBefore;
    }

    /**
     * Returns the value of the tag.
     *
     * @return string
     */
    public function getValue()
    {
        return $this->_value;
    }

    /**
     * Returns the comment associated with the value of this tag.
     *
     * @return string
     */
    public function getComment()
    {
        return $this->_comment;
    }

    /**
     * Returns the witespace before the content of this tag.
     *
     * @return string
     */
    public function getWhitespaceBeforeValue()
    {
        return $this->_valueWhitespace;
    }
}
