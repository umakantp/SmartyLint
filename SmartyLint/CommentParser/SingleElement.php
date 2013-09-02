<?php
/**
 * A class to represent single element doc tags.
 *
 * A single element doc tag contains only one value after the tag itself. An
 * example would be the \@package tag.
 *
 * @package   SmartyLint
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      http://smartylint.com
 */
class SmartyLint_CommentParser_SingleElement extends SmartyLint_CommentParser_AbstractDocElement {

    /**
     * The content that exists after the tag.
     *
     * @var string
     * @see getContent()
     */
    protected $content = '';

    /**
     * The whitespace that exists before the content.
     *
     * @var string
     * @see getWhitespaceBeforeContent()
     */
    protected $contentWhitespace = '';


    /**
     * Constructs a SingleElement doc tag.
     *
     * @param SmartyLint_CommentParser_DocElement $previousElement The element before
     *                                                             this one.
     * @param array                                $tokens         The tokens that
     *                                                             comprise this element.
     * @param string                               $tag            The tag that this
     *                                                             element represents.
     * @param SmartyLint_File                      $smartylFile    The file that this
     *                                                             element is in.
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
    protected function getSubElements() {
        return array('content');
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
    protected function processSubElement($name, $content, $whitespaceBefore) {
        $this->content = $content;
        $this->contentWhitespace = $whitespaceBefore;
    }

    /**
     * Returns the content of this tag.
     *
     * @return string
     */
    public function getContent() {
        return $this->content;
    }

    /**
     * Returns the witespace before the content of this tag.
     *
     * @return string
     */
    public function getWhitespaceBeforeContent() {
        return $this->contentWhitespace;
    }

    /**
     * Processes a content check for single doc element.
     *
     * @param SmartyLint_File $smartylFile  The file being scanned.
     * @param int             $commentStart The line number where the error
     *                                      occurs.
     * @param string          $docBlock     Whether this is a file or class
     *                                      comment doc.
     *
     * @return void
     */
    public function process(
        SmartyLint_File $smartylFile,
        $commentStart,
        $docBlock
    ) {
        if ($this->content === '') {
            $errorPos = ($commentStart + $this->getLine());
            $error = 'Content missing for %s tag in %s comment';
            $data = array(
                    $this->tag,
                    $docBlock,
                );
            $smartylFile->addError($error, $errorPos, 'EmptyTagContent', $data);
        }
    }
}
