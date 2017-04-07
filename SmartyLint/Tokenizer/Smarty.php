<?php
/**
 * Tokenizes Smarty code.
 *
 * @package   SmartyLint
 * @author    Umakant Patil <me@umakantpatil.com>
 * @copyright 2013-15 Umakant Patil
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      https://github.com/umakantp/SmartyLint
 */
class SmartyLint_Tokenizer_Smarty
{

    /**
     * Opening (left) delimiter of smarty.
     *
     * @var string
     */
    private $_leftDelim = null;

    /**
     * Closing (right) delimiter of smarty.
     *
     * @var string
     */
    private $_rightDelim = null;

    /**
     * EOL character being used in the file.
     *
     * @var string
     */
    private $_eolChar = '\n';

    /**
     * If auto literal is turned on / off in smarty.
     *
     * @var boolean
     */
    private $_autoLiteral = true;

    /**
     * Creates an array of tokens when given some Smarty code.
     *
     * @param string $string      The string to tokenize.
     * @param string $eolChar     The EOL character to use for splitting strings.
     * @param string $leftD       Left delimiter used to tokenize Smarty strings.
     * @param string $rightD      Right delimiter used to tokenize Smarty strings.
     * @param string $autoLiteral If auto literal is turned on in settings.
     *
     * @return array
     */
    public function tokenizeString(
        $string,
        $eolChar = '\n',
        $leftD = '{',
        $rightD = '}',
        $autoLiteral = true
    ) {
        $this->_leftDelim = $leftD;
        $this->_rightDelim = $rightD;
        $this->_eolChar = $eolChar;
        $skeleton = array();

        while ($tag = $this->findTag($string)) {
            if ($tag['index']) {
                $text = substr($string, 0, $tag['index']);
                $skeleton[] = array(
                    'type' => 'text',
                    'data' => $text,
                );
            }
            $string = substr($string, ($tag['index'] + strlen($tag['content'])));
            $skeleton[] = array(
                'type' => 'smarty',
                'data' => $tag['tag'],
                'extraData' => $tag['content'],
            );
        }

        if ($string) {
            $skeleton[] = array(
                'type' => 'text',
                'data' => $string,
            );
        }
        // Skeleton creates which breaks smarty and other text apart.
        // Now get them converted to tokens which can be understood by Rule files.
        $tokens = $this->convertToToken($skeleton);
        return $tokens;
    }

    /**
     * Convert skeleton data to Tokens.
     *
     * @param array $skeleton Skeleton to convert.
     *
     * @return array
     */
    protected function convertToToken($skeleton)
    {
        // Tokens to be stored here.
        $tokens = array();
        // Current cursor/pointer to the $tokens.
        $pointer = 0;
        // Current line of the begins with 1.
        $currentLine = 1;
        // Total no of comments used to find Document comment.
        $commentCount = 0;
        if (strlen($this->_eolChar) > 1) {
            // If eol character is \r\n (windows).
            $eolChar = "\r";
        } else {
            $eolChar = $this->_eolChar;
        }

        // Iterate skeleton.
        foreach ($skeleton as $node) {
            if ($node['type'] === 'text') {
                $data = $node['data'];
                for ($i = 0; $i < strlen($data); $i++) {
                    $char = $data[$i];
                    $j = $i + 1;
                    $next = null;
                    if (isset($data[$j])) {
                        $next = $data[$j];
                    }

                    switch ($char) {
                        case $eolChar:
                            $content = $eolChar;
                            if ($char === "\r" && $next === "\n") {
                                $i++;
                                $content = $char . $next;
                            }
                            $tokens[$pointer] = array(
                                'type' => 'NEW_LINE',
                                'content' => $content,
                                'line' => $currentLine,
                            );
                            $pointer++;
                            $currentLine++;
                            break;

                        case "\t":
                            $tokens[$pointer] = array(
                                'type' => 'TAB',
                                'content' => "\t",
                                'line' => $currentLine,
                            );
                            $pointer++;
                            break;

                        case '<':
                            $k = isset($data[($j + 1)]) ? $data[($j + 1)] : null;
                            $l = isset($data[($k + 1)]) ? $data[($k + 1)] : null;
                            $multi = false;
                            if ($next === '!' && $k === $l && $l === '-') {
                                // HTML comment found. Keep adding to content until it ends.
                                $token = array('type' => 'HTML_COMMENT', 'line' => $currentLine);
                                $content = '';
                                while ($char !== '-' && $next !== '-' && $k !== '>') {
                                    $content .= $char;
                                    if ($char === $eolChar) {
                                        $multi = true;
                                        $currentLine++;
                                        if ($char === "\r" && $next === "\n") {
                                            $content .= $next;
                                            $i++;
                                        }
                                    }
                                    $i++;
                                    $char = $data[$i];
                                    $next = isset($data[($i + 1)]) ?: $data[($i + 1)];
                                    $k = isset($data[($i + 2)]) ?: $data[($i + 2)];
                                }
                                $content .= $char . $next . $k;
                                $i = $iPointer;
                                $token['content'] = $content;
                                if ($multi) {
                                    $token['multi'] = true;
                                    $token['end'] = $currrentLine;
                                }
                                $tokens[$pointer] = $token;
                                $pointer++;
                            } else {
                                // It may be an html tag.
                                goto dodefault;
                            }
                            break;

                        default:
                            dodefault:
                            // Chars which are unkown to SmartyLint yet.
                            $lastToken = null;
                            $lastPointer = ($pointer - 1);
                            if (isset($tokens[$lastPointer])) {
                                $lastToken = $tokens[$lastPointer];
                            }
                            if (isset($lastToken['type']) && $lastToken['type'] == 'HTML_TEXT') {
                                $tokens[$lastPointer]['content'] = $lastToken['content'] . $char;
                            } else {
                                $tokens[$pointer] = array(
                                    'type' => 'HTML_TEXT',
                                    'content' => $char,
                                    'line' => $currentLine,
                                );
                                $pointer++;
                            }
                            break;
                    }
                }
            } else {
                $tokens[] = $this->parseSmarty($node, $currentLine);
                if (isset($tokens[$pointer]['multi']) && $tokens[$pointer]['multi']) {
                    // Multiline smarty tag get the end line as current line.
                    $currentLine = $tokens[$pointer]['end'];
                } else {
                    // Otherwise it is single line smarty.
                    $currentLine = $tokens[$pointer]['line'];
                }
                if ($tokens[$pointer]['type'] === 'SMARTY_COMMENT' && $commentCount === 0) {
                    $tokens[$pointer]['type'] = 'SMARTY_DOC_COMMENT';
                    $commentCount++;
                }
                $pointer++;
            }
        }
        return $tokens;
    }

    /**
     * Read the node and return the tokens.
     *
     * @param array   $node        Node which contains smarty tag.
     * @param integer $currentLine Current line no on which smarty tag is present.
     *
     * @return array
     */
    protected function parseSmarty($node, $currentLine)
    {
        $token = array(
            'type' => 'SMARTY',
            'content' => $node['extraData'],
            'line' => $currentLine,
        );
        if ($node['data'][0] === '*') {
            $token['type'] = 'SMARTY_COMMENT';
        }
        $multi = false;
        $data = $node['data'];
        for ($i = 0; $i < strlen($data); $i++) {
            $char = $data[$i];
            $j = $i + 1;
            $next = null;
            if (isset($data[$j])) {
                $next = $data[$j];
            }
            if ($char === $this->_eolChar) {
                $multi = true;
                $currentLine++;

                if ($char === "\r" && $next === "\n") {
                    $i++;
                }
            }
        }
        if ($multi) {
            $token['multi'] = true;
            $token['end'] = $currentLine;
        }
        return $token;
    }

    /**
     * Find the next tag and return the details of the tag.
     *
     * @param string $string String in which to find tag.
     *
     * @return array|null Return format ['index' => position of start tag, 'content' => tag match with delim,
     *                    'tag' => found part inside delim];
     */
    protected function findTag($string)
    {
        $sDelim = $this->_leftDelim;
        $eDelim = $this->_rightDelim;
        $autoLiteral = $this->_autoLiteral;
        $openCount = 0;
        $offset = 0;
        for ($i = 0; $i < strlen($string); $i++) {
            if (substr($string, $i, strlen($sDelim)) === $sDelim) {
                preg_match('/\s/', substr($string, ($i+1), 1), $whiteSpaces);
                if ($autoLiteral && (($i + 1) < strlen($string)) && $whiteSpaces) {
                    continue;
                }
                if (!$openCount) {
                    $string = substr($string, $i);
                    $offset += $i;
                    $i = 0;
                }
                $openCount++;
            } elseif (substr($string, $i, strlen($eDelim)) === $eDelim) {
                preg_match('/\s/', substr($string, ($i-1), 1), $whiteSpaces);
                if ($autoLiteral && (($i - 1) >= 0) && $whiteSpaces) {
                    continue;
                }
                $l = --$openCount;
                if (!$l) {
                    $found = substr($string, strlen($sDelim), ($i - strlen($eDelim)));
                    if ($found) {
                        return array(
                            'index' => $offset,
                            'tag' => $found,
                            'content' => substr($string, 0, ($i + strlen($eDelim))),
                        );
                    }
                }
                if ($openCount < 0) {
                    // Throw exception later, for now ignore.
                    $openCount = 0;
                }
            }
        }
        return null;
    }
}
