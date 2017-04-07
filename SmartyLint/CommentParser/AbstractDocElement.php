<?php
/**
 * A class to handle most of the parsing operations of a doc comment element.
 *
 * Extending classes should implement the getSubElements method to return
 * a list of elements that the doc comment element contains, in the order that
 * they appear in the element. For example a function parameter element has a
 * type, a variable name and a comment. It should therefore implement the method
 * as follows:
 *
 * <code>
 *    protected function getSubElements()
 *    {
 *        return array(
 *                'type',
 *                'variable',
 *                'comment',
 *               );
 *    }
 * </code>
 *
 * The processSubElement will be called for each of the sub elements to allow
 * the extending class to process them. So for the parameter element we would
 * have:
 *
 * <code>
 *    protected function processSubElement($name, $content, $whitespaceBefore)
 *    {
 *        if ($name === 'type') {
 *            echo 'The name of the variable was '.$content;
 *        }
 *        // Process other tags.
 *    }
 * </code>
 *
 * @package   SmartyLint
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      https://github.com/umakantp/SmartyLint
 */

abstract class SmartyLint_CommentParser_AbstractDocElement implements SmartyLint_CommentParser_DocElement
{
    /**
     * The element previous to this element.
     *
     * @var SmartyLint_CommentParser_DocElement
     */
    protected $previousElement = null;

    /**
     * The element proceeding this element.
     *
     * @var SmartyLint_CommentParser_DocElement
     */
    protected $nextElement = null;

    /**
     * The whitespace the occurs after this element and its sub elements.
     *
     * @var string
     */
    protected $afterWhitespace = '';

    /**
     * The tokens that comprise this element.
     *
     * @var array(string)
     */
    protected $tokens = array();

    /**
     * The file this element is in.
     *
     * @var array(string)
     */
    protected $smartylFile = null;

    /**
     * The tag that this element represents (omiting the @ symbol).
     *
     * @var string
     */
    protected $tag = '';


    /**
     * Constructs a Doc Element.
     *
     * @param SmartyLint_CommentParser_DocElement $previousElement The element
     *                                                             that ocurred
     *                                                             before this.
     * @param array                               $tokens          The tokens of
     *                                                             this element.
     * @param string                              $tag             The doc
     *                                                             element tag
     *                                                             this element
     *                                                             represents.
     * @param SmartyLint_File                     $smartylFile     The file that
     *                                                             this element
     *                                                             is in.
     *
     * @throws Exception If $previousElement in not a DocElement or if
     *                   getSubElements() does not return an array.
     */
    public function __construct(
        $previousElement,
        array $tokens,
        $tag,
        SmartyLint_File $smartylFile
    ) {
        if ($previousElement !== null
            && ($previousElement instanceof SmartyLint_CommentParser_DocElement) === false
        ) {
            $error = '$previousElement must be an instance of DocElement';
            throw new Exception($error);
        }

        $this->smartylFile = $smartylFile;

        $this->previousElement = $previousElement;
        if ($previousElement !== null) {
            $this->previousElement->nextElement = $this;
        }

        $this->tag = $tag;
        $this->tokens = $tokens;

        $subElements = $this->getSubElements();

        if (is_array($subElements) === false) {
            throw new Exception('getSubElements() must return an array');
        }

        $whitespace = '';
        $currElem = 0;
        $lastElement = '';
        $lastElementWhitespace = null;
        $numSubElements = count($subElements);

        foreach ($this->tokens as $token) {
            if (trim($token) === '') {
                $whitespace .= $token;
            } else {
                if ($currElem < ($numSubElements - 1)) {
                    $element = $subElements[$currElem];
                    $this->processSubElement($element, $token, $whitespace);
                    $whitespace = '';
                    $currElem++;
                } else {
                    if ($lastElementWhitespace === null) {
                        $lastElementWhitespace = $whitespace;
                    }

                    $lastElement .= $whitespace.$token;
                    $whitespace   = '';
                }
            }
        }//end foreach

        $lastElement     = ltrim($lastElement);
        $lastElementName = $subElements[($numSubElements - 1)];

        // Process the last element in this tag.
        $this->processSubElement(
            $lastElementName,
            $lastElement,
            $lastElementWhitespace
        );

        $this->afterWhitespace = $whitespace;
    }

    /**
     * Returns the element that exists before this.
     *
     * @return SmartyLint_CommentParser_DocElement
     */
    public function getPreviousElement()
    {
        return $this->previousElement;
    }


    /**
     * Returns the element that exists after this.
     *
     * @return SmartyLint_CommentParser_DocElement
     */
    public function getNextElement()
    {
        return $this->nextElement;
    }

    /**
     * Returns the whitespace that exists before this element.
     *
     * @return string
     */
    public function getWhitespaceBefore()
    {
        if ($this->previousElement !== null) {
            return $this->previousElement->getWhitespaceAfter();
        }

        return '';
    }

    /**
     * Returns the whitespace that exists after this element.
     *
     * @return string
     */
    public function getWhitespaceAfter()
    {
        return $this->afterWhitespace;
    }

    /**
     * Returns the order that this element appears in the comment.
     *
     * @return int
     */
    public function getOrder()
    {
        if ($this->previousElement === null) {
            return 1;
        } else {
            return ($this->previousElement->getOrder() + 1);
        }
    }

    /**
     * Returns the tag that this element represents, ommiting the @ symbol.
     *
     * @return string
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * Returns the raw content of this element, ommiting the tag.
     *
     * @return string
     */
    public function getRawContent()
    {
        return implode('', $this->tokens);
    }

    /**
     * Returns the comment tokens.
     *
     * @return array
     */
    public function getTokens()
    {
        return $this->tokens;
    }

    /**
     * Returns the line in which this element first occured.
     *
     * @return int
     */
    public function getLine()
    {
        if ($this->previousElement === null) {
            // First element is on line one.
            return 1;
        } else {
            $previousContent = $this->previousElement->getRawContent();
            $previousLine = $this->previousElement->getLine();

            return ($previousLine + substr_count($previousContent, $this->smartylFile->eolChar));
        }
    }


    /**
     * Returns the sub element names that make up this element in the order they
     * appear in the element.
     *
     * @return array(string)
     * @see processSubElement()
     */
    abstract protected function getSubElements();

    /**
     * Called to process each sub element as sepcified in the return value
     * of getSubElements().
     *
     * @param string $name             The name of the element to process.
     * @param string $content          The content of the the element.
     * @param string $whitespaceBefore The whitespace found before this element.
     *
     * @return void
     * @see getSubElements()
     */
    abstract protected function processSubElement(
        $name,
        $content,
        $whitespaceBefore
    );
}
