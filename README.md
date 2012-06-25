Patchwork Logger: Advanced PHP error handling and high accuracy JSON logging
============================================================================

Here are five PHP classes under Apache 2 and GPLv2 licenses, focused on
particular aspects of errors handling in PHP. Together, they offer unprecedented
accuracy for logging what's going on with the internal state of your
applications.

For interoperability and readability, errors and variables' states are logged to
JSON.

Error handling
--------------

### Patchwork\PHP\ErrorHandler

is a flexible error and exception handler.

Its default behavior is to log errors to the same file where fatal errors are
written. That way, the same debug stream contains both uncatchable fatal errors
and catchable ones in an easy to parse format.

Each error type is handled according to four bit fields:

- *scream*: controls which errors are never @-silenced - silenced fatal errors
  that can be detected at shutdown time are logged when the bit field allows so,
- *thrownErrors*: controls which errors are turned to exceptions
  (defaults to *E_RECOVERABLE_ERROR | E_USER_ERROR*),
- *scopedErrors*: controls which errors are logged along with their local scope,
- *tracedErrors*: controls which errors are logged along with their trace (but
  only once for repeated errors).

Since errors, even silenced ones, always have a performance cost, repeated
errors are all logged, so that the developper can see them and weight them as
more important to fix than others of the same type.

High accuracy logging
---------------------

Did you try to dump a variable inside an output buffering handler? Any error
handling or variable logging code out there using either *ob_start()*,
*print_r()* or *var_dump()* fails in this situation. Neither *serialize()* is
usable, because some objects throw an exception when serialized. If your current
dumper uses *json_encode()* internally (or *var_export()* since PHP 5.3.3) then
you may be safe. But even then, you won't be able to log intra-references in
your arrays/objects, nor details for resources and so on.

Because errors always happen in unexpected situations, a robust logger must work
whatever the running context is, for any variable type.

In order to allow a higher level of accuracy, variables are logged following the
[JSON convention to dump any PHP variable with high accuracy](https://github.com/nicolas-grekas/Patchwork-Doc/blob/master/Dumping-PHP-Data-en.md).

To achieve this, several classes are involved:

### Patchwork\PHP\Logger

logs any message to an output stream.

Error messages are handled specifically in order to make them more friendly,
especially for traces and exceptions.

Logged messages just have to have a type and some associated data. They are sent
to a *JsonDumper* object who writes to your debug stream (but that can be any
other destination).

### Patchwork\PHP\JsonDumper

implements the [JSON convention to dump any PHP variable with high accuracy](https://github.com/nicolas-grekas/Patchwork-Doc/blob/master/Dumping-PHP-Data-en.md).

It extends the *Dumper* class.

### Patchwork\PHP\Dumper

Handles a callback mechanism for getting detailed information about dumped
objects and resources, alongside with managing depth and length limits.

It extends the *Walker* class.

### Patchwork\PHP\Walker

implements a mechanism to generically traverse any PHP variable.

It takes internal references into account, recursive or non-recursive, without
preempting any special use of the discovered data. It exposes only one public
method *->walk()*, which triggers the traversal. It also has a public property
*->checkInternalRefs* set to true by default, to disable the check for internal
references if the mechanism is considered too expensive. Checking recursive
references and object/resource can not be disabled but is much lighter.

Usage
-----

Including the `bootup.logger.php` file is the easiest way to start with these
features. By defaults, errors are written to *php://stderr*, but the file is
here to be tuned by you.

This code is extracted from the [Patchwork](http://pa.tchwork.com/) framework
where it serves as the foundation for the debugging system. It is released here
standalone in the hope that it can be used in a different context successfully!
