<?php
/**
 * Parses function doc comments.
 *
 * @package   SmartyLint
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      https://github.com/umakantp/SmartyLint
 */
class SmartyLint_CommentParser_FileCommentParser {

    /**
     * The parameter elements within this function comment.
     *
     * @var array(SmartyLint_CommentParser_ParameterElement)
     */
    private $_params = array();

    /**
     * The word tokens that appear in the comment.
     *
     * Whitespace tokens also appear in this stack, but are separate tokens
     * from words.
     *
     * @var array(string)
     */
    protected $words = array();

    /**
     * An array of all tags found in the comment.
     *
     * @var array(string)
     */
    protected $foundTags = array();

    /**
     * The previous doc element that was processed.
     *
     * null if the current element being processed is the first element in the
     * doc comment.
     *
     * @var SmartyLint_CommentParser_DocElement
     */
    protected $previousElement = null;

    /**
     * A list of see elements that appear in this doc comment.
     *
     * @var array(SmartyLint_CommentParser_SingleElement)
     */
    protected $sees = array();

    /**
     * A list of see elements that appear in this doc comment.
     *
     * @var array(SmartyLint_CommentParser_SingleElement)
     */
    protected $links = array();

    /**
     * A element to represent \@since tags.
     *
     * @var SmartyLint_CommentParser_SingleElement
     */
    protected $since = null;

    /**
     * The string content of the comment.
     *
     * @var string
     */
    protected $commentString = '';

    /**
     * The file that the comment exists in.
     *
     * @var SmartyLint_File
     */
    protected $smartylFile = null;

    /**
     * True if the comment has been parsed.
     *
     * @var boolean
     */
    private $_hasParsed = false;

    /**
     * The tags that this class can process.
     *
     * @var array(string)
     */
    private static $_tags = array(
            'see'        => false,
            'link'       => false,
            'since'      => true
        );

    /**
     * An array of unknown tags.
     *
     * @var array(string)
     */
    public $unknown = array();

    /**
     * The order of tags.
     *
     * @var array(string)
     */
    public $orders = array();

    /**
     * The element that represents this comment element.
     *
     * @var DocElement
     */
    protected $comment = null;

    /**
     * Constructs a SmartyLint_CommentParser_FileCommentParser.
     *
     * @param string          $comment     The comment to parse.
     * @param SmartyLint_File $smartylFile The file that this comment is in.
     */
    public function __construct($comment, SmartyLint_File $smartylFile) {
        $this->commentString = $comment;
        $this->smartylFile = $smartylFile;
    }

    /**
     * Initiates the parsing of the doc comment.
     *
     * @return void
     * @throws SmartyLint_CommentParser_ParserException If the parser finds a
     *                                                       problem with the
     *                                                       comment.
     */
    public function parse() {
        if (! $this->_hasParsed) {
            $this->_parse($this->commentString);
        }
    }

    /**
     * Parse the comment.
     *
     * @param string $comment The doc comment to parse.
     *
     * @return void
     * @see _parseWords()
     */
    private function _parse($comment) {
        // Firstly, remove the comment tags and any stars from the left side.
        $lines = explode($this->smartylFile->eolChar, $comment);
        foreach ($lines as &$line) {
            $line = trim($line);

            if ($line !== '') {
                $lEnd = strlen($this->smartylFile->lDelimiter) + 2;
                $rEnd = strlen($this->smartylFile->rDelimiter) + 1;
                if (substr($line, 0, $lEnd) === ($this->smartylFile->lDelimiter.'**')) {
                    $line = substr($line, $lEnd);
                } else if (substr($line, -($rEnd), $rEnd) === ('*'.$this->smartylFile->rDelimiter)) {
                    $line = substr($line, 0, -($rEnd));
                } else if ($line[0] === '*') {
                    $line = substr($line, 1);
                }

                // Add the words to the stack, preserving newlines. Other parsers
                // might be interested in the spaces between words, so tokenize
                // spaces as well as separate tokens.
                $flags = (PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                $words = preg_split(
                    '|(\s+)|',
                    $line.$this->smartylFile->eolChar,
                    -1,
                    $flags
                );

                $this->words = array_merge($this->words, $words);
            }
        }
        $this->_parseWords();
    }

    /**
     * Parses each word within the doc comment.
     *
     * @return void
     * @see _parse()
     * @throws SmartyLint_CommentParser_ParserException If more than the allowed
     *                                                  number of occurances of
     *                                                  a tag is found.
     */
    private function _parseWords() {
        $allowedTags = (self::$_tags + $this->getAllowedTags());
        $allowedTagNames = array_keys($allowedTags);
        $prevTagPos = false;
        $wordWasEmpty = true;
        foreach ($this->words as $wordPos => $word) {
            if (trim($word) !== '') {
                $wordWasEmpty = false;
            }
            if ($word[0] === '@') {
                $tag = substr($word, 1);

                // Filter out @ tags in the comment description.
                // A real comment tag should have whitespace and a newline before it.
                if (!empty($this->words[$wordPos - 1])) {
                    continue;
                }

                if (! isset($this->words[($wordPos - 2)])
                    || $this->words[($wordPos - 2)] !== $this->smartylFile->eolChar
                ) {
                    continue;
                }

                $this->foundTags[] = array(
                        'tag'  => $tag,
                        'line' => $this->getLine($wordPos),
                        'pos'  => $wordPos,
                    );

                if ($prevTagPos !== false) {
                    // There was a tag before this so let's process it.
                    $prevTag = substr($this->words[$prevTagPos], 1);
                    $this->parseTag($prevTag, $prevTagPos, ($wordPos - 1));
                } else {
                    // There must have been a comment before this tag, so
                    // let's process that.
                    $this->parseTag('comment', 0, ($wordPos - 1));
                }

                $prevTagPos = $wordPos;

                if (! in_array($tag, $allowedTagNames)) {
                    // This is not a tag that we process, but let's check to
                    // see if it is a tag we know about. If we don't know about it,
                    // we add it to a list of unknown tags.
                    $knownTags = array(
                            'example',
                            'internal',
                            'name',
                            'todo',
                            'tutorial',
                            'package_version@',
                        );
                    if (! in_array($tag, $knownTags)) {
                        $this->unknown[] = array(
                                'tag'  => $tag,
                                'line' => $this->getLine($wordPos),
                                'pos'  => $wordPos,
                        );
                    }
                }
            }
        }

        // Only process this tag if there was something to process.
        if (! $wordWasEmpty) {
            if ($prevTagPos === false) {
                // There must only be a comment in this doc comment.
                $this->parseTag('comment', 0, count($this->words));
            } else {
                // Process the last tag element.
                $prevTag  = substr($this->words[$prevTagPos], 1);
                $numWords = count($this->words);
                $endPos   = $numWords;

                if ($prevTag === 'package' || $prevTag === 'subpackage') {
                    // These are single-word tags, so anything after a newline
                    // is really a comment.
                    for ($endPos = $prevTagPos; $endPos < $numWords; $endPos++) {
                        if (str_contains($this->words[$endPos], $this->smartyl->eolChar)) {
                            break;
                        }
                    }
                }

                $this->parseTag($prevTag, $prevTagPos, $endPos);

                if ($endPos !== $numWords) {
                    // Process the final comment, if it is not empty.
                    $tokens  = array_slice($this->words, ($endPos + 1), $numWords);
                    $content = implode('', $tokens);
                    if (trim($content) !== '') {
                        $this->parseTag('comment', ($endPos + 1), $numWords);
                    }
                }
            }
        }
    }

    /**
     * Returns the line that the token exists on in the doc comment.
     *
     * @param int $tokenPos The position in the words stack to find the line
     *                      number for.
     *
     * @return int
     */
    protected function getLine($tokenPos) {
        $newlines = 0;
        for ($i = 0; $i < $tokenPos; $i++) {
            $newlines += substr_count($this->smartylFile->eolChar, $this->words[$i]);
        }

        return $newlines;
    }

    /**
     * Parses see tag element within the doc comment.
     *
     * @param array(string) $tokens The word tokens that comprise this element.
     *
     * @return DocElement The element that represents this see comment.
     */
    protected function parseSee($tokens) {
        $see = new SmartyLint_CommentParser_SingleElement(
            $this->previousElement,
            $tokens,
            'see',
            $this->smartylFile
        );

        $this->sees[] = $see;
        return $see;
    }

    /**
     * Parses the comment element that appears at the top of the doc comment.
     *
     * @param array(string) $tokens The word tokens that comprise tihs element.
     *
     * @return DocElement The element that represents this comment element.
     */
    protected function parseComment($tokens) {
        $this->comment = new SmartyLint_CommentParser_CommentElement(
            $this->previousElement,
            $tokens,
            $this->smartylFile
        );

        return $this->comment;
    }

    /**
     * Parses \@since tags.
     *
     * @param array(string) $tokens The word tokens that comprise this element.
     *
     * @return SingleElement The element that represents this since tag.
     */
    protected function parseSince($tokens) {
        $this->since = new SmartyLint_CommentParser_SingleElement(
            $this->previousElement,
            $tokens,
            'since',
            $this->smartylFile
        );

        return $this->since;
    }

    /**
     * Parses \@link tags.
     *
     * @param array(string) $tokens The word tokens that comprise this element.
     *
     * @return SingleElement The element that represents this link tag.
     */
    protected function parseLink($tokens) {
        $link = new SmartyLint_CommentParser_SingleElement(
            $this->previousElement,
            $tokens,
            'link',
            $this->smartylFile
        );

        $this->links[] = $link;
        return $link;
    }

    /**
     * Parses parameter elements.
     *
     * @param array(string) $tokens The tokens that conmpise this sub element.
     *
     * @return SmartyLint_CommentParser_ParameterElement
     */
    protected function parseParam($tokens) {
        $param = new SmartyLint_CommentParser_ParameterElement(
            $this->previousElement,
            $tokens,
            $this->smartylFile
        );

        $this->_params[] = $param;
        return $param;
    }

    /**
     * Returns the see elements that appear in this doc comment.
     *
     * @return array(SingleElement)
     */
    public function getSees() {
        return $this->sees;
    }

    /**
     * Returns the comment element that appears at the top of this doc comment.
     *
     * @return CommentElement
     */
    public function getComment() {
        return $this->comment;
    }

    /**
     * Returns the word list.
     *
     * @return array
     */
    public function getWords() {
        return $this->words;
    }

    /**
     * Returns the list of found tags.
     *
     * @return array
     */
    public function getTags() {
        return $this->foundTags;
    }

    /**
     * Returns the link elements found in this comment.
     *
     * Returns an empty array if no links are found in the comment.
     *
     * @return array(SingleElement)
     */
    public function getLinks() {
        return $this->links;
    }

    /**
     * Returns the since element found in this comment.
     *
     * Returns null if no element exists in the comment.
     *
     * @return SingleElement
     */
    public function getSince() {
        return $this->since;
    }

    /**
     * Parses the specified tag.
     *
     * @param string $tag   The tag name to parse (omitting the @ sybmol from
     *                      the tag)
     * @param int    $start The position in the word tokens where this element
     *                      started.
     * @param int    $end   The position in the word tokens where this element
     *                      ended.
     *
     * @return void
     * @throws Exception If the process method for the tag cannot be found.
     */
    protected function parseTag($tag, $start, $end) {
        $tokens = array_slice($this->words, ($start + 1), ($end - $start));

        $allowedTags     = (self::$_tags + $this->getAllowedTags());
        $allowedTagNames = array_keys($allowedTags);
        if ($tag === 'comment' || in_array($tag, $allowedTagNames)) {
            $method = 'parse'.$tag;
            if (! method_exists($this, $method)) {
                $error = 'Method '.$method.' must be implemented to process '.$tag.' tags';
                throw new Exception($error);
            }

            $this->previousElement = $this->$method($tokens);
        } else {
            $this->previousElement = new SmartyLint_CommentParser_SingleElement(
                $this->previousElement,
                $tokens,
                $tag,
                $this->smartylFile
            );
        }

        $this->orders[] = $tag;

        if (! ($this->previousElement instanceof SmartyLint_CommentParser_DocElement)) {
            throw new Exception('Parse method must return a DocElement');
        }

    }//end parseTag()

    /**
     * Returns the allowed tags that can exist in a function comment.
     *
     * @return array(string => boolean)
     */
    protected function getAllowedTags() {
        return array('param'  => false);
    }

    /**
     * Returns the tag orders (index => tagName).
     *
     * @return array
     */
    public function getTagOrders() {
        return $this->orders;
    }

    /**
     * Returns the unknown tags.
     *
     * @return array
     */
    public function getUnknown() {
        return $this->unknown;
    }

    /**
     * Returns the parameter elements that this function comment contains.
     *
     * Returns an empty array if no parameter elements are contained within
     * this function comment.
     *
     * @return array(SmartyLint_CommentParser_ParameterElement)
     */
    public function getParams() {
        return $this->_params;
    }
}
