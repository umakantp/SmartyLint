<?php
/**
 * A class to represent param tags within a function comment.
 *
 * @package   SmartyLint
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      https://github.com/umakantp/SmartyLint
 */
class SmartyLint_CommentParser_ParameterElement extends SmartyLint_CommentParser_AbstractDocElement {

    /**
     * The variable name of this parameter name, including the $ sign.
     *
     * @var string
     */
    private $_varName = '';

    /**
     * The comment of this parameter tag.
     *
     * @var string
     */
    private $_comment = '';

    /**
     * The variable type of this parameter tag.
     *
     * @var string
     */
    private $_type = '';

    /**
     * The whitespace that exists before the variable name.
     *
     * @var string
     */
    private $_varNameWhitespace = '';

    /**
     * The whitespace that exists before the comment.
     *
     * @var string
     */
    private $_commentWhitespace = null;

    /**
     * The whitespace that exists before the variable type.
     *
     * @var string
     */
    private $_typeWhitespace = '';


    /**
     * Constructs a SmartyLint_CommentParser_ParameterElement.
     *
     * @param SmartyLint_CommentParser_DocElement $previousElement The element
     *                                                             previous to
     *                                                             this one.
     * @param array                               $tokens          The tokens
     *                                                             that make up
     *                                                             this element.
     * @param SmartyLint_File                     $smartylFile     The file that
     *                                                             this element
     *                                                             is in.
     */
    public function __construct(
        $previousElement,
        $tokens,
        SmartyLint_File $smartylFile
    ) {
        parent::__construct($previousElement, $tokens, 'param', $smartylFile);

        // Handle special variable type: array(x => y).
        $type = strtolower($this->_type);
        if ($this->_varName === '=>' && str_contains($type, 'array(')) {
            $rawContent = $this->getRawContent();
            $matches    = array();
            $pattern    = '/^(\s+)(array\(.*\))(\s+)(\$\S*)(\s+)(.*)/i';
            if (preg_match($pattern, $rawContent, $matches) !== 0) {
                // Process the sub elements correctly for this special case.
                if (count($matches) === 7) {
                    $this->processSubElement('type', $matches[2], $matches[1]);
                    $this->processSubElement('varName', $matches[4], $matches[3]);
                    $this->processSubElement('comment', $matches[6], $matches[5]);
                }
            }
        }
    }

    /**
     * Returns the element names that this tag is comprised of, in the order
     * that they appear in the tag.
     *
     * @return array(string)
     * @see processSubElement()
     */
    protected function getSubElements() {
        return array(
                'type',
                'varName',
                'comment',
            );
    }

    /**
     * Processes the sub element with the specified name.
     *
     * @param string $name             The name of the sub element to process.
     * @param string $content          The content of this sub element.
     * @param string $beforeWhitespace The whitespace that exists before the
     *                                 sub element.
     *
     * @return void
     * @see getSubElements()
     */
    protected function processSubElement($name, $content, $beforeWhitespace) {
        $element           = '_'.$name;
        $whitespace        = $element.'Whitespace';
        $this->$element    = $content;
        $this->$whitespace = $beforeWhitespace;
    }

    /**
     * Returns the variable name that this parameter tag represents.
     *
     * @return string
     */
    public function getVarName() {
        return $this->_varName;
    }


    /**
     * Returns the variable type that this string represents.
     *
     * @return string
     */
    public function getType() {
        return $this->_type;
    }

    /**
     * Returns the comment of this comment for this parameter.
     *
     * @return string
     */
    public function getComment() {
        return $this->_comment;
    }

    /**
     * Returns the whitespace before the variable type.
     *
     * @return stirng
     * @see getWhiteSpaceBeforeVarName()
     * @see getWhiteSpaceBeforeComment()
     */
    public function getWhiteSpaceBeforeType() {
        return $this->_typeWhitespace;
    }

    /**
     * Returns the whitespace before the variable name.
     *
     * @return string
     * @see getWhiteSpaceBeforeComment()
     * @see getWhiteSpaceBeforeType()
     */
    public function getWhiteSpaceBeforeVarName() {
        return $this->_varNameWhitespace;
    }


    /**
     * Returns the whitespace before the comment.
     *
     * @return string
     * @see getWhiteSpaceBeforeVarName()
     * @see getWhiteSpaceBeforeType()
     */
    public function getWhiteSpaceBeforeComment() {
        return $this->_commentWhitespace;
    }

    /**
     * Returns the postition of this parameter are it appears in the comment.
     *
     * This method differs from getOrder as it is only relative to method
     * parameters.
     *
     * @return int
     */
    public function getPosition() {
        if (! ($this->getPreviousElement() instanceof SmartyLint_CommentParser_ParameterElement)) {
            return 1;
        } else {
            return ($this->getPreviousElement()->getPosition() + 1);
        }
    }

    /**
     * Returns true if this parameter's variable aligns with the other's.
     *
     * @param SmartyLint_CommentParser_ParameterElement $other The other param
     *                                                         to check
     *                                                         alignment with.
     *
     * @return boolean
     */
    public function alignsVariableWith(
        SmartyLint_CommentParser_ParameterElement $other
    ) {
        // Format is:
        // @param type $variable Comment.
        // @param <-a-><---b---->
        // Compares the index before param variable.
        $otherVar = (strlen($other->_type) + strlen($other->_varNameWhitespace));
        $thisVar  = (strlen($this->_type) + strlen($this->_varNameWhitespace));
        if ($otherVar !== $thisVar) {
            return false;
        }

        return true;
    }

    /**
     * Returns true if this parameter's comment aligns with the other's.
     *
     * @param SmartyLint_CommentParser_ParameterElement $other The other param
     *                                                         to check
     *                                                         alignment with.
     *
     * @return boolean
     */
    public function alignsCommentWith(
        SmartyLint_CommentParser_ParameterElement $other
    ) {
        // Compares the index before param comment.
        $otherComment
            = (strlen($other->_varName) + strlen($other->_commentWhitespace));
        $thisComment
            = (strlen($this->_varName) + strlen($this->_commentWhitespace));

        if ($otherComment !== $thisComment) {
            return false;
        }

        return true;
    }

    /**
     * Returns true if this parameter aligns with the other paramter.
     *
     * @param SmartyLint_CommentParser_ParameterElement $other The other param
     *                                                         to check
     *                                                         alignment with.
     *
     * @return boolean
     */
    public function alignsWith(SmartyLint_CommentParser_ParameterElement $other) {
        if (! $this->alignsVariableWith($other)) {
            return false;
        }
        if (! $this->alignsCommentWith($other)) {
            return false;
        }
        return true;
    }
}
