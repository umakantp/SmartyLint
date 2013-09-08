<?php
/**
 * A class to process command line smartyl scripts.
 *
 * @package   SmartyLint
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      http://smartylint.com
 */

include_once dirname(__FILE__).'/../SmartyLint.php';

class SmartyLint_CLI {

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
    public function checkRequirements() {
        // Check the PHP version.
        if (version_compare(PHP_VERSION, '5.2.4') === -1) {
            echo 'ERROR: SmartyLint requires PHP version 5.2.4 or greater.'.PHP_EOL;
            exit(2);
        }
    }

    /**
     * Get a list of default values for all possible command line arguments.
     *
     * @return array
     */
    public function getDefaults() {
        // The default values for settings.
        $defaults['files'] = array();
        $defaults['extensions'] = array('tpl', 'smarty');
        $defaults['startDelimiter'] = "{";
        $defaults['endDelimiter'] = "}";
        $defaults['showProgress'] = false;
        $defaults['ignoreRules'] = null;

        return $defaults;
    }

    /**
     * Process the command line arguments and returns the values.
     *
     * @return array
     */
    public function getCommandLineValues() {
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
    public function processShortArgument($arg, $pos, $values) {
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
                echo 'SmartyLint version 0.1.2 (alpha) '.PHP_EOL;
                echo 'by Umakant Patil (http://smartylint.com)'.PHP_EOL.PHP_EOL;
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
    public function processLongArgument($arg, $pos, $values) {

        switch ($arg) {
            case 'help':
                $this->printUsage();
                exit(0);
                break;
            case 'version':
                echo 'SmartyLint version 0.1.2 (alpha) '.PHP_EOL;
                echo 'by Umakant Patil (http://smartylint.com)'.PHP_EOL;
                exit(0);
                break;
            default:
                if (substr($arg, 0, 11) === 'extensions=') {
                    $values['extensions'] = explode(',', substr($arg, 11));
                } else if (substr($arg, 0, 6) === 'files=') {
                    $values['files'] = explode(',', substr($arg, 6));
                } else if (substr($arg, 0, 17) === 'start-delimiter=') {
                    $values['startDelimiter'] = substr($arg, 17);
                } else if (substr($arg, 0, 15) === 'end-delimiter=') {
                    $values['endDelimiter'] = substr($arg, 15);
                } else if (substr($arg, 0, 13) === 'ignore-rules=') {
                    $values['ignoreRules'] = substr($arg, 13);
                }
                break;
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
    public function process($values=array()) {
        if (empty($values) === true) {
            $values = $this->getCommandLineValues();
        }
        $lint = new SmartyLint();

        // Set file extensions if they were specified. Otherwise,
        // let SmartyLint decide on the defaults.
        if (empty($values['extensions']) === false) {
            $lint->setAllowedFileExtensions($values['extensions']);
        }

        if (empty($values['startDelimiter']) === false) {
            $lint->setStartDelimiter($values['startDelimiter']);
        }

        if (empty($values['endDelimiter']) === false) {
            $lint->setEndDelimiter($values['endDelimiter']);
        }

        $lint->setCli($this);

        $lint->process(
            $values['files'],
            $values['ignoreRules']
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
    public function printErrorReport(SmartyLint $lint) {
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
    public function printUsage() {
        echo 'Usage: smartyl --files=<files> [--extensions=<extensions>]'.PHP_EOL;
        echo '    [--start-delimiter=<delimiter>] [--end-delimiter=<delimiter>]'.PHP_EOL;
        echo '    [--ignore-rules=<rules>]'.PHP_EOL;
        echo '        -p                Show progress of the run'.PHP_EOL;
        echo '        -h                Print this help message'.PHP_EOL;
        echo '        -v                Print version information'.PHP_EOL;
        echo '        --help            Print this help message'.PHP_EOL;
        echo '        --version         Print version information'.PHP_EOL;
        echo '        <files>           One or more files and/or directories to check'.PHP_EOL;
        echo '        <extensions>      A comma separated list of file extensions to check'.PHP_EOL;
        echo '                          (only valid if checking a directory)'.PHP_EOL;
        echo '        <delimiter>       Delimiter used in smarty files.'.PHP_EOL.PHP_EOL;
        echo '        <rules>           Path to xml rule file which defines if any files are to'.PHP_EOL;
        echo '                          be excluded or any rule is to be turned off.'.PHP_EOL.PHP_EOL;
    }
}
