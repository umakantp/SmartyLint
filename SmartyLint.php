<?php
/**
 * SmartyLint tokenizes Smarty file and detects violations of a
 * defined set of coding standards.
 *
 * @package   SmartyLint
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @author    Umakant Patil <me@umakantpatil.com>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      http://smartylint.com
 */

spl_autoload_register(array('SmartyLint', 'autoload'));

if (class_exists('SmartyLint_Exception', true) === false) {
    throw new Exception('Class SmartyLint_Exception not found');
}

if (class_exists('SmartyLint_File', true) === false) {
    throw new SmartyLint_Exception('Class SmartyLint_File not found');
}

if (class_exists('SmartyLint_Cli', true) === false) {
    throw new SmartyLint_Exception('Class SmartyLint_Cli not found');
}

if (interface_exists('SmartyLint_Rule', true) === false) {
    throw new SmartyLint_Exception('Interface SmartyLint_Rule not found');
}

if (interface_exists('SmartyLint_MultiFileRule', true) === false) {
    throw new SmartyLint_Exception('Interface SmartyLint_MultiFileRule not found');
}

/*
 * Rules are specified by classes that implement the SmartyLint_Rule
 * interface. A rule registers what token types it wishes to listen for, then
 * SmartyLint encounters that token, the rule is invoked and passed information
 * about where the token was found in the stack, and the token stack itself.
 *
 * Rule files and their containing class must be prefixed with rule, and
 * have an extension of .php.
 *
 * Multiple SmartyLint operations can be performed by re-calling the process
 * function with different parameters.
 */
class SmartyLint {

    /**
     * The file or directory that is currently being processed.
     *
     * @var string
     */
    protected $file = '';

    /**
     * The files that have been processed.
     *
     * @var array(SmartyLint_File)
     */
    protected $files = array();

    /**
     * The directory to search for rules in.
     *
     * This is declared static because it is also used in the autoloader
     * to look for rules outside the SmartyL install.
     * This way, standards designed to be installed inside SmartyL can
     * also be used from outside the SmartyL Rules directory.
     *
     * @var string
     */
    protected static $rulesDir = '';

    /**
     * The Cli object controlling the run.
     *
     * @var string
     */
    public $cli = null;

    /**
     * An array of rules that are being used to check files.
     *
     * @var array(SmartyLint_Rule)
     */
    protected $listeners = array();


    /**
     * The listeners array, indexed by token type.
     *
     * @var array
     */
    private $_tokenListeners = array(
                                'file'      => array(),
                                'multifile' => array(),
                               );

    /**
     * An array of extensions for files we will check.
     *
     * @var array
     */
    public $allowedFileExtensions = array('smarty', 'tpl');

    /**
     * End delimiter for smarty.
     *
     * @var string
     */
    public $endDelimiter = '}';

    /**
     * Start delimiter for smarty.
     *
     * @var string
     */
    public $startDelimiter = '{';

    /**
     * An array of variable types for param/var we will check.
     *
     * @var array(string)
     */
    public static $allowedTypes = array(
                                   'array',
                                   'boolean',
                                   'float',
                                   'integer',
                                   'mixed',
                                   'object',
                                   'string',
                                  );

    /**
     * Constructs a SmartyLint object.
     *
     * @see process()
     */
    public function __construct() {
        // Set default Cli object in case someone is running us
        // without using the command line script.
        $this->cli = new SmartyLint_Cli();
    }

    /**
     * Autoload static method for loading classes and interfaces.
     *
     * @param string $className The name of the class or interface.
     *
     * @return void
     */
    public static function autoload($className) {
        $path = str_replace(array('_', '\\'), '/', $className).'.php';

        if (is_file(dirname(__FILE__).'/'.$path) === true) {
            // Check standard file locations based on class name.
            include dirname(__FILE__).'/'.$path;
        } else if (is_file(dirname(__FILE__).'/SmartyLint/'.$path) === true) {
            // Check for included rules.
            include dirname(__FILE__).'/SmartyLint/'.$path;
        } else if (self::$rulesDir !== ''
            && is_file(dirname(self::$rulesDir).'/'.$path) === true
        ) {
            // Check standard file locations based on the passed standard directory.
            include dirname(self::$rulesDir).'/'.$path;
        } else {
            // Everything else.
            @include $path;
        }
    }

    /**
     * Sets an array of file extensions that we will allow checking of.
     *
     * @param array $extensions An array of file extensions.
     *
     * @return void
     */
    public function setAllowedFileExtensions(array $extensions) {
        $this->allowedFileExtensions = $extensions;
    }

    /**
     * Sets an end delimiter for smarty. Default is {.
     *
     * @param string $delimiter Start delimiter for smarty.
     *
     * @return void
     */
    public function setStartDelimiter($delimiter) {
        $this->startDelimiter = $delimiter;
    }

    /**
     * Sets an end delimiter for smarty. Default is }.
     *
     * @param string $delimiter End delimiter for smarty.
     *
     * @return void
     */
    public function setEndDelimiter($delimiter) {
        $this->endDelimiter = $delimiter;
    }

    /**
     * Sets the internal Cli object.
     *
     * @param object $cli The Cli object controlling the run.
     *
     * @return void
     */
    public function setCli($cli) {
        $this->cli = $cli;
    }

    /**
     * Adds a file to the list of checked files.
     *
     * Checked files are used to generate error reports after the run.
     *
     * @param SmartyLint_File $smartylFile The file to add.
     *
     * @return void
     */
    public function addFile(SmartyLint_File $smartylFile) {
        $this->files[] = $smartylFile;
    }

    /**
     * Processes the files/directories that SmartyLint was constructed with.
     *
     * @param string|array $files The files and directories to process. For
     *                            directories, each sub directory will also
     *                            be traversed for source files.
     *
     * @return void
     * @throws SmartyLint_Exception If files or standard are invalid.
     */
    public function process($files) {
        if (is_array($files) === false) {
            if (is_string($files) === false || $files === null) {
                throw new SmartyLint_Exception('$file must be a string');
            }

            $files = array($files);
        }

        // Reset the members.
        $this->listeners = array();
        $this->files = array();
        $this->_tokenListeners = array(
                'file' => array(),
                'multifile' => array(),
            );

        // Ensure this option is enabled or else line endings will not always
        // be detected properly for files created on a Mac with the /r line ending.
        ini_set('auto_detect_line_endings', true);

        $this->setTokenListeners();
        $this->populateTokenListeners();

        if (empty($files) === true) {
            return;
        }

        $reporting = new SmartyLint_Reporting();
        $cliValues = $this->cli->getCommandLineValues();
        $showProgress = $cliValues['showProgress'];

        $todo = $this->getFilesToProcess($files);
        $numFiles = count($todo);

        $numProcessed = 0;
        $dots = 0;
        $maxLength = strlen($numFiles);
        $lastDir = '';
        foreach ($todo as $file) {
            $this->file = $file;
            $currDir = dirname($file);
            if ($lastDir !== $currDir) {
                $lastDir = $currDir;
            }
            $lintFile  = $this->processFile($file);
            $numProcessed++;

            // Show progress information.
            if ($lintFile === null) {
                echo 'S';
            } else {
                $errors   = $lintFile->getErrorCount();
                $warnings = $lintFile->getWarningCount();
                if ($errors > 0) {
                    echo 'E';
                } else if ($warnings > 0) {
                    echo 'W';
                } else {
                    echo '.';
                }
            }

            $dots++;
            if ($dots === 60) {
                $padding = ($maxLength - strlen($numProcessed));
                echo str_repeat(' ', $padding);
                echo " $numProcessed / $numFiles".PHP_EOL;
                $dots = 0;
            }
        }

        // Now process the multi-file rules, assuming there are
        // multiple files being checked.
        if (count($files) > 1 || is_dir($files[0]) === true) {
            $this->processMulti();
        }

        echo "\n\n";
    }

    /**
     * Processes multi-file rules.
     *
     * @return void
     */
    public function processMulti() {
        foreach ($this->_tokenListeners['multifile'] as $listenerData) {
            // Set the name of the listener for error messages.
            foreach ($this->files as $file) {
                $file->setActiveListener($listenerData['class']);
            }

            $listenerData['listener']->process($this->files);
        }
    }

    /**
     * Sets installed rules in the coding standard being used.
     *
     * Traverses the standard directory for classes that implement the
     * SmartyLint_Rule interface asks them to register. Each of the
     * rule's class names must be exact as the basename of the rule file.
     * If the standard is a file, will skip transversal and just load rules
     * from the file.
     *
     * @return void
     */
    public function setTokenListeners() {
        self::$rulesDir = realpath(dirname(__FILE__).'/SmartyLint/Rules');

        $files = $this->getRuleFiles(self::$rulesDir);

        $listeners = array();

        foreach ($files as $file) {
            // Work out where the position of /Rules/... is
            // so we can determine what the class will be called.
            $rulePos = strrpos($file, DIRECTORY_SEPARATOR.'Rules'.DIRECTORY_SEPARATOR);
            if ($rulePos === false) {
                continue;
            }

            $slashPos = strrpos(substr($file, 0, ($rulePos+4)), DIRECTORY_SEPARATOR);
            if ($slashPos === false) {
                continue;
            }

            $className = substr($file, ($slashPos + 1));
            $className = substr($className, 0, -4);
            $className = str_replace(DIRECTORY_SEPARATOR, '_', $className);

            include_once $file;

            // Support the use of PHP namespaces. If the class name we included
            // contains namespace seperators instead of underscores, use this as the
            // class name from now on.
            $classNameNS = str_replace('_', '\\', $className);
            if (class_exists($classNameNS, false) === true) {
                $className = $classNameNS;
            }

            $listeners[$className] = $className;
        }

        $this->listeners = $listeners;
    }

    /**
     * Return a list of rules that a coding standard has defined.
     *
     * Rules are found by recursing the standard directory and also by
     * asking the standard for included rules.
     *
     * @param string $dir      The directory where to look for the files.
     *
     * @return array
     */
    public function getRuleFiles($dir) {
        $includedRules = array();

        if (is_dir($dir) === true) {
            $di = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($di as $file) {
                $fileName = $file->getFilename();

                // Skip hidden files.
                if (substr($fileName, 0, 1) === '.') {
                    continue;
                }

                // We are only interested in PHP and rule files.
                $fileParts = explode('.', $fileName);
                if (array_pop($fileParts) !== 'php') {
                    continue;
                }

                $basename = basename($fileName, '.php');
                if (substr($basename, -4) !== 'Rule') {
                    continue;
                }


                $includedRules[] = $file->getPathname();
            }
        }

        return $includedRules;
    }

    /**
     * Populates the array of SmartyLint_Rule's for this file.
     *
     * @return void
     * @throws SmartyLint_Exception If rule registration fails.
     */
    public function populateTokenListeners() {
        // Construct a list of listeners indexed by token being listened for.
        $this->_tokenListeners = array(
                                  'file'      => array(),
                                  'multifile' => array(),
                                 );

        foreach ($this->listeners as $listenerClass) {
            // Work out the internal code for this rule. Detect usage of namespace
            // seperators instead of underscores to support PHP namespaces.
            if (strstr($listenerClass, '\\') === false) {
                $parts = explode('_', $listenerClass);
            } else {
                $parts = explode('\\', $listenerClass);
            }

            $code  = $parts[0].'.'.$parts[2];
            $code  = substr($code, 0, -5);

            $this->listeners[$listenerClass] = new $listenerClass();

            if (($this->listeners[$listenerClass] instanceof SmartyLint_Rule) === true) {
                $tokens = $this->listeners[$listenerClass]->register();

                if (is_array($tokens) === false) {
                    $msg = "Rule $listenerClass register() method must return an array";
                    throw new SmartyLint_Exception($msg);
                }

                foreach ($tokens as $token) {
                    if (isset($this->_tokenListeners['file'][$token]) === false) {
                        $this->_tokenListeners['file'][$token] = array();
                    }

                    if (in_array($this->listeners[$listenerClass], $this->_tokenListeners['file'][$token], true) === false) {
                        $this->_tokenListeners['file'][$token][] = array(
                                'listener' => $this->listeners[$listenerClass],
                                'class' => $listenerClass
                            );
                    }
                }
            } else if (($this->listeners[$listenerClass] instanceof SmartyLint_MultiFileRule) === true) {
                $this->_tokenListeners['multifile'][] = array(
                        'listener' => $this->listeners[$listenerClass],
                        'class' => $listenerClass
                    );
            }
        }
    }

    /**
     * Get a list of files that will be processed.
     *
     * If passed directories, this method will find all files within them.
     * The method will also perform file extension and ignore pattern filtering.
     *
     * @param string  $paths A list of file or directory paths to process.
     *
     * @return array
     * @throws Exception If there was an error opening a directory.
     * @see    shouldProcessFile()
     */
    public function getFilesToProcess($paths) {
        $files = array();
        foreach ($paths as $path) {
            if (is_dir($path) === true) {
                $di = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

                foreach ($di as $file) {
                    // Check if the file exists after all symlinks are reolved.
                    $filePath = realpath($file->getPathname());
                    if ($filePath === false) {
                        continue;
                    }

                    if (is_dir($filePath) === true) {
                        continue;
                    }

                    if ($this->shouldProcessFile($file->getPathname(), $path) === false) {
                        continue;
                    }

                    $files[] = $file->getPathname();
                }
            } else {
                if ($this->shouldIgnoreFile($path, dirname($path)) === true) {
                    continue;
                }

                $files[] = $path;
            }
        }
        return $files;
    }

    /**
     * Checks filtering rules to see if a file should be checked.
     *
     * Checks both file extension filters.
     *
     * @param string $path    The path to the file being checked.
     * @param string $basedir The directory to use for relative path checks.
     *
     * @return bool
     */
    public function shouldProcessFile($path, $basedir) {
        // Check that the file's extension is one we are checking.
        // We are strict about checking the extension and we don't
        // let files through with no extension or that start with a dot.
        $fileName  = basename($path);
        $fileParts = explode('.', $fileName);
        if ($fileParts[0] === $fileName || $fileParts[0] === '') {
            return false;
        }

        // Checking multi-part file extensions, so need to create a
        // complete extension list and make sure one is allowed.
        $extensions = array();
        array_shift($fileParts);
        foreach ($fileParts as $part) {
            $extensions[] = implode('.', $fileParts);
            array_shift($fileParts);
        }

        $matches = array_intersect($extensions, $this->allowedFileExtensions);
        if (empty($matches) === true) {
            return false;
        }

        // If the file's path matches one of our ignore patterns, skip it.
        if ($this->shouldIgnoreFile($path, $basedir) === true) {
            return false;
        }

        return true;
    }

    /**
     * Checks filtering rules to see if a file should be ignored.
     *
     * Right now it doesn't ignore any file. May be in next version we would fix this.
     *
     * @param string $path    The path to the file being checked.
     * @param string $basedir The directory to use for relative path checks.
     *
     * @return bool
     */
    public function shouldIgnoreFile($path, $basedir) {
        return false;
    }

    /**
     * Run the code rules over a single given file.
     *
     * Processes the file and runs the SmartyLint rules to verify that it
     * conforms with the standard. Returns the processed file object, or NULL
     * if no file was processed due to error.
     *
     * @param string $file     The file to process.
     * @param string $contents The contents to parse. If NULL, the content
     *                         is taken from the file system.
     *
     * @return SmartyLint_File
     * @throws SmartyLint_Exception If the file could not be processed.
     * @see    _processFile()
     */
    public function processFile($file, $contents=null) {
        if ($contents === null && file_exists($file) === false) {
            throw new SmartyLint_Exception("Source file $file does not exist");
        }

        $filePath = realpath($file);
        if ($filePath === false) {
            $filePath = $file;
        }

        // Before we go and spend time tokenizing this file, just check
        // to see if there is a tag up top to indicate that the whole
        // file should be ignored. It must be on one of the first two lines.
        $firstContent = $contents;
        if ($contents === null && is_readable($filePath) === true) {
            $handle = fopen($filePath, 'r');
            if ($handle !== false) {
                $firstContent  = fgets($handle);
                $firstContent .= fgets($handle);
                fclose($handle);
            }
        }

        if (strpos($firstContent, '@noSmartyLint') !== false) {
            return null;
        }

        try {
            $lintFile = $this->_processFile($file, $contents);
        } catch (Exception $e) {
            $trace = $e->getTrace();

            $filename = $trace[0]['args'][0];
            if (is_object($filename) === true
                && get_class($filename) === 'SmartyLint_File'
            ) {
                $filename = $filename->getFilename();
            } else if (is_numeric($filename) === true) {
                // See if we can find the SmartyLint_File object.
                foreach ($trace as $data) {
                    if (isset($data['args'][0]) === true
                        && ($data['args'][0] instanceof SmartyLint_File) === true
                    ) {
                        $filename = $data['args'][0]->getFilename();
                    }
                }
            } else if (is_string($filename) === false) {
                $filename = (string) $filename;
            }

            $error = 'An error occurred during processing; checking has been aborted. The error message was: '.$e->getMessage();

            $lintFile = new SmartyLint_File(
                $filename,
                $this->_tokenListeners['file'],
                $this
            );

            $this->addFile($lintFile);
            $lintFile->addError($error, null);
        }

        return $lintFile;
    }

    /**
     * Process the rules for a single file.
     *
     * @param string $file     The file to process.
     * @param string $contents The contents to parse. If NULL, the content
     *                         is taken from the file system.
     *
     * @return SmartyLint_File
     * @see    processFile()
     */
    private function _processFile($file, $contents) {
        $lintFile = new SmartyLint_File(
            $file,
            $this->_tokenListeners['file'],
            $this
        );
        $this->addFile($lintFile);
        $lintFile->start($contents);

        // Clean up the test if we can to save memory. This can't be done if
        // we need to leave the files around for multi-file rules.
        if (empty($this->_tokenListeners['multifile']) === true) {
            $lintFile->cleanUp();
        }

        return $lintFile;
    }

    /**
     * Gives collected violations for reports.
     *
     * @return array
     */
    public function getFilesErrors() {
        $files = array();
        foreach ($this->files as $file) {
            $files[$file->getFilename()] = array(
                    'warnings' => $file->getWarnings(),
                    'errors' => $file->getErrors(),
                    'numWarnings' => $file->getWarningCount(),
                    'numErrors' => $file->getErrorCount(),
                );
        }

        return $files;
    }

    /**
     * Returns the SmartyLint file objects.
     *
     * @return array(SmartyLint_File)
     */
    public function getFiles() {
        return $this->files;

    }

    /**
     * Gets the array of SmartyLint_Rule's.
     *
     * @return array(SmartyLint_Rule)
     */
    public function getRules() {
        return $this->listeners;

    }

    /**
     * Gets the array of SmartyLint_Rule's indexed by token type.
     *
     * @return array()
     */
    public function getTokenRules() {
        return $this->_tokenListeners;

    }
}
