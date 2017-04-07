<?php
/**
 * A class to process command line smartyl scripts.
 *
 * @package   SmartyLint
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      https://github.com/umakantp/SmartyLint
 */

include_once dirname(__FILE__).'/../SmartyLint.php';

class SmartyLint_Cli
{

    /**
     * An array of all values specified on the command line.
     *
     * @var array
     */
    protected $values = array();

    /**
     * Exits if the minimum requirements of SmartyLint are not met.
     *
     * @return array
     */
    public function checkRequirements()
    {
        // Check the PHP version.
        if (version_compare(PHP_VERSION, '5.3.0') === -1) {
            echo 'ERROR: SmartyLint requires PHP version 5.3.0 or greater.'.PHP_EOL;
            exit(2);
        }
    }

    /**
     * Get a list of default values for all possible command line arguments.
     *
     * @return array
     */
    public function getDefaults()
    {
        // The default values for settings.
        $defaults['files'] = array();
        $defaults['extensions'] = array('tpl', 'smarty');
        $defaults['leftDelimiter'] = "{";
        $defaults['rightDelimiter'] = "}";
        $defaults['showProgress'] = false;
        $defaults['rules'] = null;
        $defaults['autoLiteral'] = true;

        return $defaults;
    }

    /**
     * Process the command line arguments and returns the values.
     *
     * @return array
     */
    public function getCommandLineValues()
    {
        if (empty($this->values) === false) {
            return $this->values;
        }

        $values = $this->getDefaults();

        for ($i = 1; $i < $_SERVER['argc']; $i++) {
            $arg = $_SERVER['argv'][$i];
            if ($arg === '') {
                continue;
            }

            if ($arg{0} === '-') {
                if ($arg === '-' || $arg === '--') {
                    // Empty argument, ignore it.
                    continue;
                }


                if ($arg{1} === '-') {
                    $values
                        = $this->processLongArgument(substr($arg, 2), $i, $values);
                } else {
                    $switches = str_split($arg);
                    foreach ($switches as $switch) {
                        if ($switch === '-') {
                            continue;
                        }

                        $values = $this->processShortArgument($switch, $i, $values);
                    }
                }
            } else {
                $values = $this->processUnknownArgument($arg, $i, $values);
            }
        }

        $this->values = $values;
        return $values;
    }

    /**
     * Processes a short (-e) command line argument.
     *
     * @param string $arg    The command line argument.
     * @param int    $pos    The position of the argument on the command line.
     * @param array  $values An array of values determined from CLI args.
     *
     * @return array The updated CLI values.
     * @see getCommandLineValues()
     */
    public function processShortArgument($arg, $pos, $values)
    {
        switch ($arg) {
            case 'h':
            case '?':
                $this->printUsage();
                exit(0);
                break;

            case 'p':
                $values['showProgress'] = true;
                break;

            case 'v':
                echo 'SmartyLint version 2.0.0 '.PHP_EOL;
                echo 'by Umakant Patil (https://github.com/umakantp/SmartyLint)'.PHP_EOL.PHP_EOL;
                exit(0);
                break;
        }

        return $values;
    }

    /**
     * Processes a long (--example) command line argument.
     *
     * @param string $arg    The command line argument.
     * @param int    $pos    The position of the argument on the command line.
     * @param array  $values An array of values determined from CLI args.
     *
     * @return array The updated CLI values.
     * @see getCommandLineValues()
     */
    public function processLongArgument($arg, $pos, $values)
    {

        switch ($arg) {
            case 'help':
                $this->printUsage();
                exit(0);
                break;
            case 'version':
                echo 'SmartyLint version 2.0.0 '.PHP_EOL;
                echo 'by Umakant Patil (https://github.com/umakantp/SmartyLint)'.PHP_EOL;
                exit(0);
                break;
            default:
                if (substr($arg, 0, 11) === 'extensions=') {
                    $values['extensions'] = explode(',', substr($arg, 11));
                } elseif (substr($arg, 0, 6) === 'files=') {
                    $values['files'] = explode(',', substr($arg, 6));
                } elseif (substr($arg, 0, 15) === 'left-delimiter=') {
                    $values['leftDelimiter'] = substr($arg, 15);
                } elseif (substr($arg, 0, 16) === 'right-delimiter=') {
                    $values['rightDelimiter'] = substr($arg, 16);
                } elseif (substr($arg, 0, 6) === 'rules=') {
                    $values['rules'] = substr($arg, 6);
                } elseif (substr($arg, 0, 13) === 'auto-literal=') {
                    $values['autoLiteral'] = substr($arg, 13);
                }
                break;
        }
        return $values;
    }

    /**
     * Process as unknown command line argument.
     *
     * Assumes all unknown arguments are files and folders to check.
     *
     * @param string $arg    The command line argument.
     * @param int    $pos    The position of the argument on the command line.
     * @param array  $values An array of values determined from CLI args.
     *
     * @return array The updated CLI values.
     * @see getCommandLineValues()
     */
    public function processUnknownArgument($arg, $pos, $values)
    {
        // We don't know about any additional switches; just files.
        if ($arg{0} === '-') {
            echo 'ERROR: option "'.$arg.'" not known.'.PHP_EOL.PHP_EOL;
            $this->printUsage();
            exit(2);
        }

        $file = realpath($arg);
        if (file_exists($file) === false) {
            echo 'ERROR: The file "'.$arg.'" does not exist.'.PHP_EOL.PHP_EOL;
            $this->printUsage();
            exit(2);
        } else {
            $values['files'][] = $file;
        }
        return $values;
    }

    /**
     * Runs SmartyLint over files and directories.
     *
     * @param array $values An array of values determined from CLI args.
     *
     * @return int The number of error and warning messages shown.
     * @see getCommandLineValues()
     */
    public function process($values = array())
    {
        if (empty($values) === true) {
            $values = $this->getCommandLineValues();
        }
        $lint = new SmartyLint();

        // Set file extensions if they were specified. Otherwise,
        // let SmartyLint decide on the defaults.
        if (empty($values['extensions']) === false) {
            $lint->setAllowedFileExtensions($values['extensions']);
        }

        if (empty($values['leftDelimiter']) === false) {
            $lint->setLeftDelimiter($values['leftDelimiter']);
        }

        if (empty($values['rightDelimiter']) === false) {
            $lint->setRightDelimiter($values['rightDelimiter']);
        }

        if (empty($values['autoLiteral']) === false) {
            $lint->setAutoLiteral($values['autoLiteral']);
        }

        $lint->setCli($this);

        $lint->process(
            $values['files'],
            $values['rules']
        );

        return $this->printErrorReport($lint);
    }

    /**
     * Prints the error report for the run.
     *
     * @param SmartyLint $lint The SmartyLint object containing the errors.
     *
     * @return int The number of error and warning messages shown.
     */
    public function printErrorReport(SmartyLint $lint)
    {
        $reporting = new SmartyLint_Reporting();
        $filesViolations = $lint->getFilesErrors();
        $errors = $reporting->printReport($filesViolations);
        // They should all return the same value, so it
        // doesn't matter which return value we end up using.
        return $errors;
    }

    /**
     * Prints out the usage information for this script.
     *
     * @return void
     */
    public function printUsage()
    {
        echo 'Usage: smartyl --files=<files> [--extensions=<extensions>]'.PHP_EOL;
        echo '    [--left-delimiter=<delimiter>] [--right-delimiter=<delimiter>]'.PHP_EOL;
        echo '    [--auto-literal=<autoliteral>] [--rules=<rules>]'.PHP_EOL;
        echo '        -p                Show progress of the run'.PHP_EOL;
        echo '        -h                Print this help message'.PHP_EOL;
        echo '        -v                Print version information'.PHP_EOL;
        echo '        --help            Print this help message'.PHP_EOL;
        echo '        --version         Print version information'.PHP_EOL;
        echo '        <files>           One or more files and/or directories to check'.PHP_EOL;
        echo '        <extensions>      A comma separated list of file extensions to check'.PHP_EOL;
        echo '                          (only valid if checking a directory)'.PHP_EOL;
        echo '        <delimiter>       Delimiter used in smarty files.'.PHP_EOL.PHP_EOL;
        echo '        <rules>           Path to xml rule file which defines configuration or'.PHP_EOL;
        echo '                          if any files are to be excluded or any rule is to be'.PHP_EOL;
        echo '                          turned off.'.PHP_EOL.PHP_EOL;
        echo '        <autoliteral>     If auto literal is true or false in your smarty settings'.PHP_EOL.PHP_EOL;
    }
}
