SmartyLint
==========

About
------------

SmartyLint is a PHP program that finds problems in Smarty files. It is tool that can be used for helping yourself for writing clean Smarty template files.


Requirements
------------

SmartyLint requires PHP version 5.2.4 or greater.

How to use?
------------

Download SmartyLint from github and start using.

    git clone git://github.com/umakantp/SmartyLint.git
    cd SmartyLint
    git checkout v0.1.4
    php smartyl -h

Or you can install using `composer`.

Simply add a dependency on umakantp/smartylint to your project's `composer.json` file if you use Composer to manage the dependencies of your project. Here is a minimal example of a composer.json file that looks like this:

```
{
    "require-dev": {
        "umakantp/smartylint": "0.1.*"
    }
}
```

After running `composer install`, you can run `smartyl` from the `vendor\bin\` directory.

See our documentation at https://github.com/umakantp/SmartyLint/wiki for more information.

Smarty conventions
------------------

If you start using SmartyLint, it would force you to follow certain standards. Conventions for Smarty files are:-

* Should have one empty line at the bottom.
* Should not have more than 1 empty line at the bottom
* You should write file doc comment at the top in Java Doc format which contains short file description and all the variables used.
* There should be no TODO's and FixMe's in the code.
* Never use HTML comments. You should use Smarty comments {**} instead.
* No whitespace / empty spaces at the end of each line.
* Can have max only 2 empty lines in the content (i.e. 3 new lines in a row).

More conventions would be added as an when code is added for it.

Contributing and Issues
-----------------------

* If you want to report a bug reports open new issue on github.
* Have improvements? or Fixes? You can send in pull request for additional features or rules.
