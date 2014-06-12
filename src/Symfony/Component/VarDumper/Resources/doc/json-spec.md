===========================================================
JSON convention to dump any PHP variable with high accuracy
===========================================================

Nicolas Grekas - nicolas.grekas, gmail.com  
October 4, 2011 - Last updated on April. 4, 2014

Introduction
============

`print_r()`, `var_dump()`, `var_export()`, `json_encode()`, `serialize()`.
All these functions dump a PHP variable, each representation adapted to
specific needs:

* being human readable,
* being computer readable,
* being accurate in the case of complex variables (recursive objects or
  resources e.g.)

For the purpose of debugging, the preferred representation is obviously
human readable and should remain as accurate as possible.

During development, it is common in PHP to display errors and intermediate
variables in the middle of the page on which we work. However, this practice is
not recommended because it can break the output of the application. In the case
of simple HTML pages, it is usually fine, but as soon as pages become more
complex, when PHP is used to generate other content types (JavaScript, PDF, ZIP,
etc.), this way of debugging is no more appropriate.

If a human being is always the final reader, a powerful debug system therefore
needs an intermediate representation to convey the state of a variable to the
system that will display it in a dedicated window.

PHP variable types
==================

Variables in PHP can have many different types, which must all be considered to
represent accurately any variable.

Scalars
-------

Scalars gather integers, floats, special constants `true`, `false`, `null`,
`NAN`, `INF`, `-INF` and strings.

Strings in PHP are simple sequences of bytes that may contain binary data,
although UTF-8 is used quite often those days for text.

Arrays
------

PHP arrays are actually ordered hash tables. They accept any string or integer
as a key. PHP does not distinguish between a numeric key and its string
representation in base 10.

Objects
-------

Objects use the same ordered hash table structure as arrays with one exception:
the name of an object property can not start with the NUL character ("\x00").
Each object also has a primary class and properties with a visibility, either
public, protected or private.

Unlike scalars and arrays, objects are passed "by soft reference"
(see [PHP manual](http://php.net/language.oop5.references.php) for more
details).

Resources
---------

Resources in PHP have a type, returned by `get_resource_type()`. Some resource
types have internal properties that can be read with appropriate functions. For
example, it is possible to get more information about resources of type `stream`
by calling `stream_get_meta_data()`, and with `proc_get_status()` for the
`process` type. Resources also have an internal identifier which is accessed by
turning them into string.
For example: `echo (string) opendir('.');` may display `Resource id #2`, where
`2` is the internal identifier of the resource returned by `opendir()`.

Resources are therefore very similar to PHP objects: they are passed "by
soft reference", have a type and properties.

Warning: in the general case, the `is_resource()` function is not reliable for
detecting variables of type `resource`.
See [this comment](http://php.net/is_resource#103942) in the PHP doc.

References
----------

PHP has two mechanisms for managing references:

* one is to bind two variables by aliasing them, as in `$b =& $a;`
* the other is used for the transmission of objects and resources

Alias type references can create infinite recursive structures,
such as in `$a = array(); $a[0] =& $a;`.

Internal aliases can also be made at positions that do not necessarily led to
recursion, such as in this code where $b[0] and $b[1] are bound by reference
`$a = 123; $b = array(&$a, &$a)`.

References used for the transmission of objects/resources allows the same
object/resource to exist at many places in a nested structure.

The desired representation must reflect the presence of these two types of
references, otherwise it would be impossible to dump a recursive structure
without falling into the trap of infinite recursion. Moreover, this allows
examination of internal links on a nested structure.

Search for the ideal representation
===================================

The intermediate representation of a variable must:

* be as accurate as possible to allow an effective debugging
* be interoperable, especially with the program in charge of its displaying
* if possible, remain human readable, to facilitate debugging of the debugging
  system itself

In addition, the code that generates the dump must be as neutral as possible
for the application in which it runs:

* it must be operating regardless of the execution context and dumped variable
* it must be fast and have a minimum memory footprint

Analysis of existing functions
------------------------------

The only function that is not disqualified due to failure under some execution
contexts is `json_encode`:

* `print_r` and until PHP 5.3.3 `var_export` generate a fatal error when used in
  the context of an output buffering handler
* `var_dump` does not work in the context of an output buffering handler
* `serialize` does not work with some objects that generate an exception when
  serialized

Considering interoperability:

* `print_r` and `var_dump` output is intended to be read by a human, not really
  by a computer
* `var_export` generates PHP code, which is readable for a human but is easily
  read only by PHP itself
* the output of `serialize` is intended to be read by `unserialize`, native to
  PHP and virtually unreadable to a human being
* `json_encode` generates interoperable output, possibly human readable,
  although encoded characters bother reading

In terms of speed and memory usage, all these functions are equivalent.

On other criteria:

* for recursive structures with internal references, `serialize` is perfect,
  `var_export` generates a fatal error, `print_r` and `var_dump` show a terse
  `*RECURSION*`, `json_encode` issues a warning and dumps a `null` in place of
  each recursive reference
* for resources, only `print_r` and `var_dump` give useful information
* `json_encode` only handles strings encoded in UTF-8, where PHP strings don't
  have any special encoding and can contain binary data
* Xdebug significantly improves `var_dump` but does nothing for output buffering
  handlers nor interoperability

Thus, no native function combine the fundamental qualities we look for.

Detailed JSON convention
========================

For the criterion of clarity and especially interoperability, JSON seems most
appropriate.

On its own, JSON is not enough to represent the range of types that PHP has.

To control the speed and memory, it is also desirable to be able to restrict the
completeness of the representation, limiting structures to their first elements,
strings to their first characters and nested structures to a maximum depth.

The dump format described below defines a set of conventions that enables JSON
for all these possibilities. It is designed both to allow the greatest possible
accuracy to any PHP variable, and to stay as close as possible to a native JSON,
thus facilitating its raw usage by computers or humans.

Strings
-------

JSON does only UTF-8 strings. An arbitrary PHP string is prepared as follows:

If the considered `$str` string is invalid UTF-8, then it is converted to UTF-8
with ``"b`".iconv('CP1252', 'UTF-8, '$str)``. Any already UTF-8 valid string is
kept identical, unless it contains a backtick, in which case it is prefixed by
`` u` ``. If a length limit is applicable, the string is truncated accordingly.
The initial length counted in UTF-8 units is then prefixed to the string. The
`` u` `` prefix is then mandatory even if the original string does not contain a
backtick. The resulting string is encoded to native JSON string.

For example: `"\xA9"` becomes ``"b`©"``, ``"a`b"`` becomes ``"u`a`b"`` and
`"©"` remains as is. The UTF-8 string of length 4 ``"déjà"`` cut to two
characters is represented as ``"4u`dé"``. The empty string is always represented
as `""`.

This convention leaves room for other prefixes. `` r` ``, `` R` `` and `` n` ``
are used to dump special values (see below).

Numbers and other scalars
-------------------------

Integers, floats or `true`, `false` and `null` values are represented in JSON
natively.

As integers and floats are subject to overflow or precision limit they can also
be represented casted as string and prefixed by `` n` ``. This is mandatory for
integers greater than 2^53, which is the upper limit for JavaScript integers.
On 64-bit systems for example, `PHP_INT_MAX` is represented as
``"n`9223372036854775807"``.

The special constants `NAN`, `INF` and `-INF` are represented by JSON strings,
respectively: ``"n`NAN"``, ``"n`INF"`` and ``"n`-INF"``.

As JSON only accepts strings as keys, integer keys in PHP arrays are represented
as JSON strings, with the `` n` `` prefix added. Since PHP does no distinction
between a numeric key accessed as a string or as an integer,
this has no accuracy implication.

Associative structures: arrays, objects and resources
-----------------------------------------------------

PHP arrays, objects and resources are represented by JSON objects, with these
added rules:

* keys `"_"`, `"__cutBy"`, `"__refs"` and `"__proto__"` are reserved
* keys for protected properties of objects are prefixed by `*:`
* keys for private properties of objects are prefixed by the class name they are
  bind to followed by a `:`
* keys for public properties of objects are prefixed by a `:` when they collide
  with a reserved key or when they contain a `:`
* keys for meta-data of objects/resources are prefixed by `~:`. Meta-data can be
  anything that is relevant to the understanding of one object: static property,
  special state for internal classes (e.g. a closure's start and end line), etc.

Reserved keys have semantics defined as follows:

* `"_"` contains the position number in the main structure, followed by a `:`,
  then:
  * for objects by the name of their class
  * for arrays by the `array` keyword followed by `:` then by their length as
    returned by `count($array)`
  * for resources by the `resource` keyword followed by `:` and then by the type
    returned by `get_resource_type($resource)`
* `"__cutBy"` contains the number of truncated elements when the local structure
  has been cut by a depth or a length limit e.g.
* `"__refs"` contains a map of internal references of the main structure (see
  below). It should be present only at the last position at the lowest depth
* `"__proto__"` has no special semantics but is reserved for compatibility with
  some browsers

Empty and numerically indexed arrays can be represented as JSON ``[]`` arrays.

Internal references
-------------------

Inside nested structures, positions are identified by a number corresponding to
their discovery order, in a depth-first traversal algorithm taking internal
references into account.

When a reference to a previous position is encountered, it is possible to avoid
repeating its value a second time by inserting a ``"R`"`` if the two positions
are aliases of each other, and a ``"r`"`` if both contain the same object or
resource. The substitution by ``"R`"`` is only required for recursive references
because it is sometimes more interesting to dump the local value instead. This
is for example the case when the first occurrence of an object was cut due to a
depth limit: if the same object is found later but at a lower level, it is
possible to dump it not-truncated there.

`` R` `` and `` r` `` may optionally be concatenated to the current position
number, followed by a `:` and again optionally by the position of the previous
occurrence associated to the current position.

When internal references are collected in this way, a special key `"__refs"`
must be inserted at the last position at the lowest depth in the main structure.
It must contain a JSON object whose keys are target position numbers and values
are JSON arrays containing all positions associated with each target. To
differentiate between alias type references and object/resource, negative
numbers are used for aliases.

Self-synchronization and other considerations
---------------------------------------------

Inserting the position number at the beginning of the `"_"` special key is not
strictly necessary in terms of accuracy of the representation: counting
positions again when interpreting the JSON is enough to retrieve it.

However, these numbers make the interpretation of a subtree of the JSON possible
without losing references: the numbers are then used to initialize the position
counter and thus to maintain synchronization with the numbers present in the
`"__refs"` special key.

Position numbers in ``"R`"`` or ``"r`"`` reference markers are optional to give
implementations complying with this description the freedom not to populate them
when the computational cost is not worth it.

Conversely where possible, the presence of these numbers can help interpreting
the JSON, even though the special `"__refs"` key is the only place that always
contains all the information available.

Examples
========

```php
<?php

// PHP variable         // JSON representation

   123                     123
   1e-9                    1.0E-9
   true                    true
   false                   false
   null                    null
   NAN                     "n`NAN"
   INF                     "n`INF"
   -INF                    "n`-INF"
   PHP_INT_MAX             "n`9223372036854775807" // On 64-bit systems, PHP_INT_MAX > 2^53
   "utf8: déjà vu \x01"    "utf8: déjà vu \u0001"
   "bin: \xA9"             "b`bin: ©"
   "with`backtick"         "u`with`backtick"
   "utf8 cut: déjà vu"     "17u`utf8 cut" // 17 is UTF-8 length of the original string
   "bin cut: \xA9"         "10b`bin cut"  // 10 is the length of the original string

   array(                  { "_": "1:array:3", // "1" is the position number of the array, "3" its length
     -1,                     "n`0": -1,
     'a',                    "n`1": "a",
      "\xA9" => 3,           "b`©": 3
   )                       }

   (object) array(         { "_": "1:stdClass", // stdClass object
      'key' => 1,            "key": 1,
      'colon:' => 2,         ":colon:": 2,      // property name containing a ":"
      '_' => 3,              ":_": 3            // reserved property name
   )                       }

   new foo                 { "_": "1:foo",      // foo class declaring 3 properties
                             "pub": "pub",      // ->pub is public
                             "*:prot": "prot",  // ->prot protected
                             "foo:priv": "priv" // and ->priv private
                           }

   new déjà                {"_":"1:déjà"}   // class declared in a UTF-8 encoded file
   new déjà                {"_":"b`1:déjà"} // class declared in a ISO-8859-1 encoded file

   $a = opendir('.')       { "_": "1:resource:stream",    // "stream" type resource
                             "wrapper_type": "plainfile", // as detailed by stream_get_meta_data()
                             "stream_type": "dir",
                             "mode": "r",
                             "unread_bytes": 0,
                             "seekable": true,
                             "timed_out": false,
                             "blocked": true,
                             "eof": false
                           }
   closedir($a);
   $a;                     {"_":"1:resource:Unknown"} // this is the best we can get for closed resources

   $a = array();           []                     // empty array, then

   $a[0] =& $a;            { "_": "1:array:1",    // at position 1, array of length 1
                             "n`0": "R`2:1",      // whose key "0" at position 2 is an alias of position 1.
                             "__refs": {"1":[-2]} // position 1 alias of position 2
                           }

   $a = (object) array();
   $a->foo =& $a;
   $a->bar = $a;
   $a = array($a, 123);
   $a[2] =& $a[1];
   $a;                     { "_": "1:array:3",    // more de fun with references :)
                             "n`0": {"_":"2:stdClass",
                               "foo": "R`3:1",
                               "bar": "r`4:2"
                             },
                             "n`1": 123,
                             "n`2": "R`6:", // this is position 6, alias of 5 as noted the line below
                             "__refs": {"5":[-6],"1":[-3],"2":[4]}
                           }

   $b = (object) array();
   $a = array($b, $b);
   $a[2] =& $a[1];
   $a;                     { "_": "1:array:3",
                             "n`0": {"_":"2:stdClass"},
                             "n`1": "r`3:2",
                             "n`2": "R`4:",
                             "__refs": {"3":[-4],"2":[3]}
                           }

   $b = (object) array(
     'foo' => 'bar'
   );
   $a = array(             { "_": "1:array:5",
     array($b),              "n`0": [
     1,                        {"_":"3:stdClass",
     $b,                         "__cutBy": 1    // object truncated by a depth limit
     3,                        }
     4                       ],
   );                        "n`1": 1,
                             "n`2": {"_":"5:stdClass",
                               "foo": "bar"      // same objet, at a lower depth
                             },
                             "__cutBy": 2,       // main array cut by 2 items
                             "__refs": {"3":[5]} // objects at positions 3 and 5 are the same
                           }

```
