# Release History

## Remex 2.3.2 (2021-08-07)
* Changed package namespace from RemexHtml to Wikimedia\RemexHtml to match
  package name.  PHP's `class_alias` has been used so that existing code
  using the old namespace will continue to work, but this is now deprecated;
  it is expected the next major release of RemexHtml will remove the aliases.
* Fix handling of <body> tag in "after head" state that would incorrectly
  result in a parse error being raised.
* Made DOMBuilder::createNode protected (rather than private) so that
  standards-compliant DOM implementations can override it.

## Remex 2.3.1 (2021-04-20)
* Don't pass null arguments to DOMImplementation::createDocument(): nulls
  are technically allowed and converted to the empty string, but this is
  deprecated legacy behavior.

## Remex 2.3.0 (2021-02-05)
* Allow use of third-party DOM implementations (like wikimedia/dodo)
  via the new `domImplementation` parameter to DOMBuilder.

## Remex 2.2.2 (2021-01-30)
* Support wikimedia/utfnormal ^3.0.1

## Remex 2.2.1 (2021-01-11)
* Various minor changes for PHP 8.0 support.
* Remove dead code about old phpunit version

## Remex 2.2.0 (2020-04-29)
* Update dependencies.
* Fix warnings emitted by PHP 7.4.
* Bug fix in TreeBuilder\ForeignAttributes::offsetGet().
* Drop PHP 7.0/7.1 and HHVM support; require PHPUnit 8.

## Remex 2.1.0 (2019-09-16)
* Call the non-standard \DOMElement::setIdAttribute() method by default.
* Add scriptingFlag option to Tokenizer, and make it true by default.
* Attributes bug fixes.
* Added RelayTreeHandler and RelayTokenHandler for subclassing convenience.
* Normalize text nodes during tree building, to match HTML parsing spec.

## Remex 2.0.3 (2019-05-10)
* Don't decode char refs if ignoreCharRefs is set, even if they are simple.
  (This fixes a regression introduced in 2.0.2.)
* Performance improvements to character entity decoding and tokenizer
  preprocessing.

## Remex 2.0.2 (2019-03-13)
* Performance improvements to tokenization and tree building.
* Provide an option to suppress namespace for HTML elements, working around
  a performance bug in PHP's dom_reconcile_ns (T217708).

## Remex 2.0.1 (2018-10-15)
* Don't double-decode HTML entities when running on PHP (not HHVM) (T207088).

## Remex 2.0.0 (2018-08-13)
* Drop support for PHP < 7.0.
* Remove descendant nodes when we get an endTag() event (T200827).
* Improved tracing.
* Added NullTreeHandler and NullTokenHandler.

## Remex 1.0.3 (2018-02-28)
* Drop support for PHP < 5.5.

## Remex 1.0.2 (2018-01-01)
* Fix linked list manipulation in CachedScopeStack (T183379).

## Remex 1.0.1 (2017-03-14)
* Fix missing breaks in switch statements.

## Remex 1.0.0 (2017-02-24)
* Initial release.
