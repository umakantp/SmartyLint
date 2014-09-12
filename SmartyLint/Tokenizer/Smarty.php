<?php
/**
 * Tokenizes Smarty code.
 *
 * @package   SmartyLint
 * @author    Umakant Patil <me@umakantpatil.com>
 * @copyright 2013 Umakant Patil
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      http://smartylint.com
 */
class SmartyLint_Tokenizer_Smarty {

    /**
     * Array of all characters of string we are tokenizing.
     *
     * @var array
     */
    private $chars = array();

    /**
     * Creates an array of tokens when given some Smarty code.
     *
     * @param string $string  The string to tokenize.
     * @param string $eolChar The EOL character to use for splitting strings.
     * @param string $sD      Start delimiter used to tokenize Smarty strings.
     * @param string $eD      End delimiter used to tokenize Smarty strings.
     *
     * @return array
     */
    public function tokenizeString($string, $eolChar = '\n', $sD = '{', $eD = '}') {
        $token = array();
        $chars = str_split($string);
        $this->chars = $chars;
        $currentLine = 1;
        $pointer = 0;

        for ($i=0; $i<count($chars); $i++) {
            $char = $chars[$i];
            switch ($char) {
                case "\r":
                    $newLine = "\r";
                    $next = $this->getNext($i);
                    if ($next === "\n") {
                        // Next char is windows new line so avoid next char.
                        $i++;
                        $newLine = "\r\n";
                    }

                case "\n":
                    $newLine = "\n";
                    $token[$pointer] = array(
                        'type' => 'NEW_LINE',
                        'content' => $newLine,
                        'line' => $currentLine
                    );
                    $currentLine++;
                    $pointer++;
                    break;

                case "\t":
                    $token[$pointer] = array(
                        'type' => 'TAB',
                        'content' => "\t",
                        'line' => $currentLine
                    );
                    $pointer++;
                    break;

                case ' ':
                    $token[$pointer] = array(
                        'type' => 'SPACE',
                        'content' => ' ',
                        'line' => $currentLine
                    );
                    $pointer++;
                    break;

                case '<':
                    if ($this->isHTMLComment($i)) {
                        $token[$pointer] = $this->getHTMLComment($i, $currentLine);
                        $i = ($i + strlen($token[$pointer]['content']) - 1);
                        $pointer++;
                    } else {
                        // It may be an HTML tag. Handle tags in next version.
                        goto dodefault;
                    }
                    break;

                case $sD:
                    // If letter after delimiter is *, then its comment.
                    if ($this->isSmartyComment($i)) {
                        $token[$pointer] = $this->getSmartyComment($i, $currentLine, $eD);
                        $i = ($i + strlen($token[$pointer]['content']) - 1);
                        $pointer++;
                    } else if ($this->isSmartyContent($i)) {
                        // It may be an Smarty variable, function, statement...etc.
                        // Capture it as TYPE smarty.
                        $token[$pointer] = $this->getSmartyContent($i, $currentLine, $eD);
                        $i = ($i + strlen($token[$pointer]['content']) - 1);
                        $pointer++;
                    } else {
                        goto dodefault;
                    }
                    break;

                default:
                dodefault:
                    // Chars, tags which are not known by tokenizer yet.
                    $last = $pointer - 1;
                    $prev = [];
                    if (isset($token[$last])) {
                        $prev = $token[$last];
                    }
                    if (isset($prev['type']) && $prev['type'] == 'UNKNOWN') {
                        $last = $pointer - 1;
                        $token[$last]['content'] = $prev['content'] . $char;
                    } else {
                        $token[$pointer] = array(
                            'type' => 'UNKNOWN',
                            'content' => $char,
                            'line' => $currentLine
                        );
                        $pointer++;
                    }
                    break;
            }
        }

        return $token;
    }

    /**
     * Get Smarty content i.e. content inside { and } delimiters.
     *
     * Smarty comments are parsed separately and are not part of this function.
     *
     * @param integer  $pointer     Pointer onwards we are finding Smarty content.
     * @param interget $currentLine Line from which we are processing.
     * @param string   $ed          Ending delimiter for smarty.
     *
     * @return array
     */
    private function getSmartyContent($pointer, &$currentLine, $eD = '}') {
        $smartyContent = '';
        $multiLine = false;
        $token['type'] = 'SMARTY';
        $contentStart = $currentLine;
        $delimitersOpen = 0;

        do {
            $char = $this->chars[$pointer];
            $smartyContent .= $char;

            if ($char === "\r" || $char === "\n") {
                $next = $this->getNext($pointer);
                $currentLine++;
                $multiLine = true;
                if ($char === "\r" && $next === "\n") {
                    // Double increment as we dont want to process next char.
                    $pointer++;
                    // \r is already added. Just add \n.
                    $smartyContent .= "\n";
                }
            } else if ($char === '{') {
                // Delimiter inside delimiter.
                $delimitersOpen++;
            } else if($char === $eD) {
                $delimitersOpen--;
            }

            $pointer++;
        } while ($delimitersOpen != 0);

        $token['content'] = $smartyContent;
        $token['line'] = $currentLine;
        if ($multiLine) {
            $token['multi'] = true;
            $token['start'] = $contentStart;
        }
        return $token;
    }

    /**
     * Get Smarty Comment present from/beyond pointer.
     *
     * When you are calling this function which means you must have already called isSmartyComment.
     * Also remember if its the first comment in the file before any content, it would be considered
     * as Doc comment. Else would be treated as normal comment.
     *
     * @param integer $pointer Pointer onwards we are finding Smarty Comment.
     *
     * @return array
     */
    private function getSmartyComment($pointer, &$currentLine, $eD = '}') {
        $comment = '';
        $multiLine = false;
        $token['type'] = 'COMMENT';
        $commentStart = $currentLine;

        // Go back find is this was first comment in the file.
        // If yes then this is DOC COMMENT.
        $rPointer = $pointer;
        $isDocComment = true;
        $lastContent = $this->getPrevious($rPointer);
        do {
            if ($lastContent !== "\n" && $lastContent !== "\r" && $lastContent !== "\t"
                && $lastContent !== " " && $lastContent !== null) {
                $isDocComment = false;
                break;
            }
            $rPointer--;
            $lastContent = $this->getPrevious($rPointer);
        } while ($lastContent !== null);


        if ($isDocComment) {
            $token['type'] = 'DOC_COMMENT';
        }

        while ($this->getPrevious($pointer) !== '*' || $this->getPrevious(($pointer+1)) !== $eD) {
            $char = $this->chars[$pointer];
            $comment .= $char;

            if ($char === "\r" || $char === "\n") {
                $next = $this->getNext($pointer);
                $currentLine++;
                $multiLine = true;
                if ($char === "\r" && $next === "\n") {
                    // Double increment as we dont want to process next char.
                    $pointer++;
                    // \r is already added. Just add \n.
                    $comment .= "\n";
                }
            }
            $pointer++;
        }
        $comment .=$this->chars[$pointer];

        $token['content'] = $comment;
        $token['line'] = $currentLine;
        if ($multiLine) {
            $token['multi'] = true;
            $token['start'] = $commentStart;
        }

        return $token;
    }

    /**
     * Tells if the content from/beyond given pointer is Smarty content.
     *
     * @param integer $pointer Pointer onwards we are finding Smarty content.
     *
     * @return boolean
     */
    private function isSmartyContent($pointer) {
        return $this->getNext($pointer) !== ' ';
    }

    /**
     * Tells if the content from/beyond given pointer is Smarty Comment.
     *
     * @param integer $pointer Pointer onwards we are finding Smarty Comment.
     *
     * @return boolean
     */
    private function isSmartyComment($pointer) {
        return $this->getNext($pointer) === '*';
    }

    /**
     * Get HTML Comment present from/beyond pointer.
     *
     * When you are calling this function which means you must have already called isHTMLComment.
     *
     * @param integer $pointer Pointer onwards we are finding HTML Comment.
     *
     * @return boolean
     */
    private function getHTMLComment($pointer, &$currentLine) {
        $comment = $this->chars[$pointer];
        $multiLine = false;
        $token['type'] = 'HTML_COMMENT';
        $commentStart = $currentLine;

        while ($this->getPrevious($pointer) !== '-' && $this->getPrevious(($pointer+1)) !== '-'
            && $this->getPrevious(($pointer+2)) !== '>') {
            $char = $this->chars[$pointer];
            $comment .= $char;

            if ($char === "\r" || $char === "\n") {
                $next = $this->getNext($pointer);
                $currentLine++;
                $multiLine = true;
                if ($char === "\r" && $next === "\n") {
                    // Double increment as we dont want to process next char.
                    $pointer++;
                    // \r is already added. Just add \n.
                    $comment .= "\n";
                }
            }
            $pointer++;
        }
        $comment .=$this->chars[$pointer];
        $comment .=$this->chars[($pointer+1)];
        $comment .=$this->chars[($pointer+2)];

        $token['content'] = $comment;
        $token['line'] = $currentLine;
        if ($multiLine) {
            $token['multi'] = true;
            $token['start'] = $commentStart;
        }

        return $token;
    }

    /**
     * Tells if the content from/beyond given pointer is HTML Comment.
     *
     * @param integer $pointer Pointer onwards we are finding HTML Comment.
     *
     * @return boolean
     */
    private function isHTMLComment($pointer) {
        if ($this->getNext($pointer) === '!' && $this->getNext(($pointer + 1)) === '-'
            && $this->getNext(($pointer + 2)) === '-'
        ) {
            return true;
        }
        return false;
    }

    /**
     * Get next character since given pointer from string to be tokenized.
     *
     * @param integer $pointer Pointer after which character we are finding.
     *
     * @return string
     */
    private function getNext($pointer) {
        return isset($this->chars[($pointer+1)]) ? $this->chars[($pointer+1)] : null;
    }

    /**
     * Get previous character since given pointer from string to be tokenized.
     *
     * @param integer $pointer Pointer before which character we are finding.
     *
     * @return string
     */
    private function getPrevious($pointer) {
        return isset($this->chars[($pointer-1)]) ? $this->chars[($pointer-1)] : null;
    }
}
