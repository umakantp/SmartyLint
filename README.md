SmartyLint
==========

About
------------

SmartyLint is a PHP program that finds problems in Smarty files. It is tool that can be used for helping yourself for writing quality Smarty template files.


Requirements
------------

SmartyLint requires PHP version 5.2.4 or greater.

How to use?
------------

Download SmartyLint from github and start using.

    git clone git://github.com/umakantp/SmartyLint.git
    cd SmartyLint
    php smartyl -h

Smarty conventions
------------------

If you start using SmartyLint, it would force you to follow certain standards. Conventions for Smarty files are:-

* Should have one empty line at the bottom.
* Should not have more than 1 empty line at the bottom
* You should write file doc comment at the top in Java Doc format which contains short file description and all the variables used.
* File comment and content should have one blank line in between.
* There should be no TODO's and FixMe's in the code.
* Never use HTML comments. You should use Smarty comments {**} instead.

More conventions would be added as an when code is added for it.

Contributing and Issues
-----------------------

* If you want to report a bug reports open new issue on github.
* Have improvements? or Fixes? You can send in pull request for additional features or rules. Remember that keep code clean.
