{** 
* Example showing unclosed tags.
*}
{if  1 == 1}
    Operation is true
{else}
    Operation is false

{foreach from=[1,2,3] item=item key=key name=name}
    Item is {$item}
