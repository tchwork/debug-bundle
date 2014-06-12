debug() function
================

This bundle provides a better `debug()` function, that you can use instead of
`var_dump()`, *better* being for:

- per object and resource types specialized view: e.g. filter out Doctrine noise
  while dumping a single proxy entity, or get more insight on opened files with
  `stream_get_meta_data()`. Add your own dedicated `Dumper\Caster` and get the
  view *you* need.
- configurable output format: HTML, command line with colors or [a dedicated high
  accuracy JSON format](Resource/doc/json-spec.md).
  More to come / add your own.
- ability to dump internal references, either soft ones (objects or resources)
  or hard ones (`=&` on arrays or objects properties). Repeated occurrences of
  the same object/array/resource won't appear again and again anymore. Moreover,
  you'll be able to inspected the reference structure of your data.
- ability to operate in the context of an output buffering handler.
- full exposure of the internal mechanisms used for walking through an arbitrary
  PHP data structure.

The debug() function works in all PHP code and in Twig templates.
