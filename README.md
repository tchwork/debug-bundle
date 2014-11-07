The DebugBundle of Symfony 2.6 backported for 2.3+
==================================================

This bundle provides a better `dump()` function, that you can use instead of
`var_dump()`, *better* meaning:

- per object and resource types specialized view: e.g. filter out Doctrine internals
  while dumping a single proxy entity, or get more insight on opened files with
  `stream_get_meta_data()`;
- ability to dump internal references, either soft ones (objects or resources)
  or hard ones (`=&` on arrays or objects properties). Repeated occurrences of
  the same object/array/resource won't appear again and again anymore. Moreover,
  you'll be able to inspect the reference structure of your data.
- ability to operate in the context of an output buffering handler.
- full exposure of the internal mechanisms used for walking through an arbitrary
  PHP data structure.

Calling `dump($myVvar)` works in all PHP code and `{% dump myVar %}` or
`{{ dump(myVar) }}` in Twig templates.

Usage
-----

The recommended way to use this package is [through composer](http://getcomposer.org).
Just create a `composer.json` file and run the `php composer.phar install`
command to install it:

    {
        "require": {
            "tchwork/debug-bundle": "~1.4"
        }
    }

Then, enable the bundle in your `app/AppKernel.php`, preferably only for the *dev*
and *test* environments:

```php
public function registerBundles()
{
    $bundles = array(
        // ...
        new \Symfony\Bundle\DebugBundle\DebugBundle(),
    );
}
```
