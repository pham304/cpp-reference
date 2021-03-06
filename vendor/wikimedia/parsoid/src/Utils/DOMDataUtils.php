<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Utils;

use Composer\Semver\Semver;
use stdClass;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Core\DataParsoid;
use Wikimedia\Parsoid\Core\DomSourceRange;
use Wikimedia\Parsoid\DOM\Document;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\DOM\Node;
use Wikimedia\Parsoid\Tokens\SourceRange;

/**
 * These helpers pertain to HTML and data attributes of a node.
 */
class DOMDataUtils {
	public const DATA_OBJECT_ATTR_NAME = 'data-object-id';

	/**
	 * Return the dynamic "bag" property of a Document.
	 * @param Document $doc
	 * @return DataBag
	 */
	private static function getBag( Document $doc ): DataBag {
		// This is a dynamic property; it is not declared.
		// All references go through here so we can suppress phan's complaint.
		// @phan-suppress-next-line PhanUndeclaredProperty
		return $doc->bag;
	}

	/**
	 * @param Document $doc
	 */
	public static function prepareDoc( Document $doc ) {
		// `bag` is a deliberate dynamic property; see DOMDataUtils::getBag()
		// @phan-suppress-next-line PhanUndeclaredProperty dynamic property
		$doc->bag = new DataBag();

		// Cache the head and body.
		DOMCompat::getHead( $doc );
		DOMCompat::getBody( $doc );
	}

	/**
	 * Stash $obj in $doc and return an id for later retrieval
	 * @param Document $doc
	 * @param stdClass $obj
	 * @return int
	 */
	public static function stashObjectInDoc( Document $doc, stdClass $obj ): int {
		return self::getBag( $doc )->stashObject( $obj );
	}

	/**
	 * Does this node have any attributes?
	 * @param Element $node
	 * @return bool
	 */
	public static function noAttrs( Element $node ): bool {
		$numAttrs = count( DOMCompat::attributes( $node ) );
		return $numAttrs === 0 ||
			( $numAttrs === 1 && $node->hasAttribute( self::DATA_OBJECT_ATTR_NAME ) );
	}

	/**
	 * Get data object from a node.
	 *
	 * @param Element $node node
	 * @return stdClass
	 */
	public static function getNodeData( Element $node ): stdClass {
		if ( !$node->hasAttribute( self::DATA_OBJECT_ATTR_NAME ) ) {
			// Initialized on first request
			$dataObject = new stdClass;
			self::setNodeData( $node, $dataObject );
			return $dataObject;
		}

		$docId = $node->getAttribute( self::DATA_OBJECT_ATTR_NAME );
		if ( $docId !== '' ) {
			$dataObject = self::getBag( $node->ownerDocument )->getObject( (int)$docId );
		} else {
			$dataObject = null; // Make phan happy
		}
		Assert::invariant( isset( $dataObject ), 'Bogus docId given!' );
		'@phan-var stdClass $dataObject'; // @var stdClass $dataObject
		if ( isset( $dataObject->storedId ) ) {
			PHPUtils::unreachable(
				'Trying to fetch node data without loading!' .
				// If this node's data-object id is different from storedId,
				// it will indicate that the data-parsoid object was shared
				// between nodes without getting cloned. Useful for debugging.
				'Node id: ' . $node->getAttribute( self::DATA_OBJECT_ATTR_NAME ) .
				'Stored data: ' . PHPUtils::jsonEncode( $dataObject )
			);
		}
		return $dataObject;
	}

	/**
	 * Set node data.
	 *
	 * @param Element $node node
	 * @param stdClass $data data
	 */
	public static function setNodeData( Element $node, stdClass $data ): void {
		$docId = self::stashObjectInDoc( $node->ownerDocument, $data );
		$node->setAttribute( self::DATA_OBJECT_ATTR_NAME, (string)$docId );
	}

	/**
	 * Get data parsoid info from a node.
	 *
	 * @param Element $node node
	 * @return DataParsoid
	 */
	public static function getDataParsoid( Element $node ): stdClass {
		$data = self::getNodeData( $node );
		if ( !isset( $data->parsoid ) ) {
			$data->parsoid = new stdClass;
		}
		if ( !isset( $data->parsoid->tmp ) ) {
			$data->parsoid->tmp = new stdClass;
		}
		return $data->parsoid;
	}

	/** Set data parsoid info on a node.
	 *
	 * @param Element $node node
	 * @param stdClass $dp data-parsoid
	 */
	public static function setDataParsoid( Element $node, stdClass $dp ): void {
		$data = self::getNodeData( $node );
		$data->parsoid = $dp;
	}

	/**
	 * Get data diff info from a node.
	 *
	 * @param Element $node node
	 * @return ?stdClass
	 */
	public static function getDataParsoidDiff( Element $node ): ?stdClass {
		$data = self::getNodeData( $node );
		// We won't set a default value for this property
		return $data->parsoid_diff ?? null;
	}

	/** Set data diff info on a node.
	 *
	 * @param Element $node node
	 * @param ?stdClass $diffObj data-parsoid-diff object
	 */
	public static function setDataParsoidDiff( Element $node, ?stdClass $diffObj ): void {
		$data = self::getNodeData( $node );
		$data->parsoid_diff = $diffObj;
	}

	/**
	 * Get data meta wiki info from a node.
	 *
	 * @param Element $node node
	 * @return stdClass
	 */
	public static function getDataMw( Element $node ): stdClass {
		$data = self::getNodeData( $node );
		if ( !isset( $data->mw ) ) {
			$data->mw = new stdClass;
		}
		return $data->mw;
	}

	/** Set data meta wiki info from a node.
	 *
	 * @param Element $node node
	 * @param ?stdClass $dmw data-mw
	 */
	public static function setDataMw( Element $node, ?stdClass $dmw ): void {
		$data = self::getNodeData( $node );
		$data->mw = $dmw;
	}

	/**
	 * Check if there is meta wiki info in a node.
	 *
	 * @param Element $node node
	 * @return bool
	 */
	public static function validDataMw( Element $node ): bool {
		return (array)self::getDataMw( $node ) !== [];
	}

	/**
	 * Get an object from a JSON-encoded XML attribute on a node.
	 *
	 * @param Element $node node
	 * @param string $name name
	 * @param mixed $defaultVal
	 * @return mixed
	 */
	public static function getJSONAttribute( Element $node, string $name, $defaultVal ) {
		if ( !$node->hasAttribute( $name ) ) {
			return $defaultVal;
		}
		$attVal = $node->getAttribute( $name );
		$decoded = PHPUtils::jsonDecode( $attVal, false );
		if ( $decoded !== null ) {
			return $decoded;
		} else {
			error_log( 'ERROR: Could not decode attribute-val ' . $attVal .
				' for ' . $name . ' on node ' . DOMCompat::nodeName( $node ) );
			return $defaultVal;
		}
	}

	/**
	 * Set a attribute on a node with a JSON-encoded object.
	 *
	 * @param Element $node node
	 * @param string $name Name of the attribute.
	 * @param mixed $obj value of the attribute to
	 */
	public static function setJSONAttribute( Element $node, string $name, $obj ): void {
		$val = $obj === [] ? '{}' : PHPUtils::jsonEncode( $obj );
		$node->setAttribute( $name, $val );
	}

	/**
	 * Set shadow info on a node; similar to the method on tokens.
	 * Records a key = value pair in data-parsoid['a'] property.
	 *
	 * This is effectively a call of 'setShadowInfoIfModified' except
	 * there is no original value, so by definition, $val is modified.
	 *
	 * @param Element $node node
	 * @param string $name Name of the attribute.
	 * @param mixed $val val
	 */
	public static function setShadowInfo( Element $node, string $name, $val ): void {
		$dp = self::getDataParsoid( $node );
		if ( !isset( $dp->a ) ) {
			$dp->a = [];
		}
		if ( !isset( $dp->sa ) ) {
			$dp->sa = [];
		}
		$dp->a[$name] = $val;
	}

	/**
	 * Set shadow info on a node; similar to the method on tokens.
	 *
	 * If the new value ($val) for the key ($name) is different from the
	 * original value ($origVal):
	 * - the new value is recorded in data-parsoid->a and
	 * - the original value is recorded in data-parsoid->sa
	 *
	 * @param Element $node node
	 * @param string $name Name of the attribute.
	 * @param mixed $val val
	 * @param mixed $origVal original value (null is a valid value)
	 * @param bool $skipOrig
	 */
	public static function setShadowInfoIfModified(
		Element $node, string $name, $val, $origVal, bool $skipOrig = false
	): void {
		if ( !$skipOrig && ( $val === $origVal || $origVal === null ) ) {
			return;
		}
		$dp = self::getDataParsoid( $node );
		if ( !isset( $dp->a ) ) {
			$dp->a = [];
		}
		if ( !isset( $dp->sa ) ) {
			$dp->sa = [];
		}
		// FIXME: This is a hack to not overwrite already shadowed info.
		// We should either fix the call site that depends on this
		// behaviour to do an explicit check, or double down on this
		// by porting it to the token method as well.
		if ( !$skipOrig && !array_key_exists( $name, $dp->a ) ) {
			$dp->sa[$name] = $origVal;
		}
		$dp->a[$name] = $val;
	}

	/**
	 * Set an attribute and shadow info to a node.
	 * Similar to the method on tokens
	 *
	 * @param Element $node node
	 * @param string $name Name of the attribute.
	 * @param mixed $val value
	 * @param mixed $origVal original value
	 * @param bool $skipOrig
	 */
	public static function addNormalizedAttribute(
		Element $node, string $name, $val, $origVal, bool $skipOrig = false
	): void {
		if ( $name === 'id' ) {
			DOMCompat::setIdAttribute( $node, $val );
		} else {
			$node->setAttribute( $name, $val );
		}
		self::setShadowInfoIfModified( $node, $name, $val, $origVal, $skipOrig );
	}

	/**
	 * Get this document's pagebundle object
	 * @param Document $doc
	 * @return stdClass
	 */
	public static function getPageBundle( Document $doc ): stdClass {
		return self::getBag( $doc )->getPageBundle();
	}

	/**
	 * Removes the `data-*` attribute from a node, and migrates the data to the
	 * document's JSON store. Generates a unique id with the following format:
	 * ```
	 * mw<base64-encoded counter>
	 * ```
	 * but attempts to keep user defined ids.
	 *
	 * @param Element $node node
	 * @param Env $env environment
	 * @param stdClass $data data
	 * @param array $idIndex Index of used id attributes in the DOM
	 */
	public static function storeInPageBundle(
		Element $node, Env $env, stdClass $data, array $idIndex
	): void {
		$uid = $node->getAttribute( 'id' ) ?? '';
		$document = $node->ownerDocument;
		$pb = self::getPageBundle( $document );
		$docDp = $pb->parsoid;
		$origId = $uid ?: null;
		if ( array_key_exists( $uid, $docDp->ids ) ) {
			$uid = null;
			// FIXME: Protect mw ids while tokenizing to avoid false positives.
			$env->log( 'info', 'Wikitext for this page has duplicate ids: ' . $origId );
		}
		if ( !$uid ) {
			do {
				$docDp->counter += 1;
				// PORT-FIXME: NOTE that we aren't updating the idIndex here because
				// we are generating unique ids that will not conflict. In any case,
				// the idIndex is a workaround for the PHP DOM's issues and we might
				// switch out of this in the future anyway.
				$uid = 'mw' . PHPUtils::counterToBase64( $docDp->counter );
			} while ( isset( $idIndex[$uid] ) );
			self::addNormalizedAttribute( $node, 'id', $uid, $origId );
		}
		$docDp->ids[$uid] = $data->parsoid;
		if ( isset( $data->mw ) ) {
			$pb->mw->ids[$uid] = $data->mw;
		}
	}

	/**
	 * @param Document $doc doc
	 * @param stdClass $obj object
	 */
	public static function injectPageBundle( Document $doc, stdClass $obj ): void {
		$pb = PHPUtils::jsonEncode( $obj );
		$script = $doc->createElement( 'script' );
		DOMCompat::setIdAttribute( $script, 'mw-pagebundle' );
		$script->setAttribute( 'type', 'application/x-mw-pagebundle' );
		$script->appendChild( $doc->createTextNode( $pb ) );
		DOMCompat::getHead( $doc )->appendChild( $script );
	}

	/**
	 * @param Document $doc doc
	 * @return stdClass|null
	 */
	public static function extractPageBundle( Document $doc ): ?stdClass {
		$pb = null;
		$dpScriptElt = DOMCompat::getElementById( $doc, 'mw-pagebundle' );
		if ( $dpScriptElt ) {
			$dpScriptElt->parentNode->removeChild( $dpScriptElt );
			$pb = PHPUtils::jsonDecode( $dpScriptElt->textContent, false );
		}
		return $pb;
	}

	/**
	 * Walk DOM from node downward calling loadDataAttribs
	 *
	 * @param Node $node node
	 * @param array $options options
	 */
	public static function visitAndLoadDataAttribs( Node $node, array $options = [] ): void {
		DOMUtils::visitDOM( $node, [ self::class, 'loadDataAttribs' ], $options );
	}

	/**
	 * Massage the data parsoid object loaded from a node attribute
	 * into expected shape. When we create a first-class object for
	 * data-parsoid, this will move into the constructor.
	 *
	 * @param stdClass $dp
	 * @param array $options
	 * @param ?Element $node
	 */
	public static function massageLoadedDataParsoid(
		stdClass $dp, array $options = [], ?Element $node = null
	): void {
		if ( isset( $dp->sa ) ) {
			$dp->sa = (array)$dp->sa;
		}
		if ( isset( $dp->a ) ) {
			$dp->a = (array)$dp->a;
		}
		if ( isset( $dp->dsr ) ) {
			$dp->dsr = DomSourceRange::fromArray( $dp->dsr );
		}
		if ( isset( $dp->tsr ) ) {
			// tsr is generally for tokens, not DOM trees.
			$dp->tsr = SourceRange::fromArray( $dp->tsr );
		}
		if ( isset( $dp->extTagOffsets ) ) {
			$dp->extTagOffsets = DomSourceRange::fromArray( $dp->extTagOffsets );
		}
		if ( isset( $dp->extLinkContentOffsets ) ) {
			$dp->extLinkContentOffsets =
				SourceRange::fromArray( $dp->extLinkContentOffsets );
		}
		if ( !empty( $options['markNew'] ) ) {
			$dp->tmp = PHPUtils::arrayToObject( $dp->tmp ?? [] );
			$dp->tmp->isNew = !$node->hasAttribute( 'data-parsoid' );
		}
		if ( isset( $dp->optList ) ) {
			foreach ( $dp->optList as &$item ) {
				$item = (array)$item;
			}
		}
	}

	/**
	 * These are intended be used on a document after post-processing, so that
	 * the underlying .dataobject is transparently applied (in the store case)
	 * and reloaded (in the load case), rather than worrying about keeping
	 * the attributes up-to-date throughout that phase.  For the most part,
	 * using this.ppTo* should be sufficient and using these directly should be
	 * avoided.
	 *
	 * @param Node $node node
	 * @param array $options options
	 */
	public static function loadDataAttribs( Node $node, array $options ): void {
		if ( !( $node instanceof Element ) ) {
			return;
		}
		// Reset the node data object's stored state, since we're reloading it
		self::setNodeData( $node, new stdClass );
		$dp = self::getJSONAttribute( $node, 'data-parsoid', new stdClass );
		self::massageLoadedDataParsoid( $dp, $options, $node );
		self::setDataParsoid( $node, $dp );
		$node->removeAttribute( 'data-parsoid' );
		$dmw = self::getJSONAttribute( $node, 'data-mw', null );
		self::setDataMw( $node, $dmw );
		$node->removeAttribute( 'data-mw' );
		$dpd = self::getJSONAttribute( $node, 'data-parsoid-diff', null );
		self::setDataParsoidDiff( $node, $dpd );
		$node->removeAttribute( 'data-parsoid-diff' );
	}

	/**
	 * Builds an index of id attributes seen in the DOM
	 * @param Node $node
	 * @return array
	 */
	public static function usedIdIndex( Node $node ): array {
		$index = [];
		DOMUtils::visitDOM( DOMCompat::getBody( $node->ownerDocument ),
			static function ( Node $n, ?array $options = null ) use ( &$index ) {
				if ( $n instanceof Element && $n->hasAttribute( 'id' ) ) {
					$index[$n->getAttribute( 'id' )] = true;
				}
			},
			[]
		);
		return $index;
	}

	/**
	 * Walk DOM from node downward calling storeDataAttribs
	 *
	 * @param Node $node node
	 * @param array $options options
	 */
	public static function visitAndStoreDataAttribs( Node $node, array $options = [] ): void {
		// PORT-FIXME: storeDataAttribs calls storeInPageBundle which calls getElementById.
		// PHP's `getElementById` implementation is broken, and we work around that by
		// using Zest which uses XPath. So, getElementById call can be O(n) and calling it
		// on on every element of the DOM via vistDOM here makes it O(n^2) instead of O(n).
		// So, we work around that by building an index and avoiding getElementById entirely
		// in storeInPageBundle.
		if ( !empty( $options['storeInPageBundle'] ) ) {
			$options['idIndex'] = self::usedIdIndex( $node );
		}
		DOMUtils::visitDOM( $node, [ self::class, 'storeDataAttribs' ], $options );
	}

	/**
	 * PORT_FIXME This function needs an accurate description
	 *
	 * @param Node $node node
	 * @param ?array $options options
	 */
	public static function storeDataAttribs( Node $node, ?array $options = null ): void {
		$options = $options ?? [];
		if ( !( $node instanceof Element ) ) {
			return;
		}
		Assert::invariant( empty( $options['discardDataParsoid'] ) || empty( $options['keepTmp'] ),
			'Conflicting options: discardDataParsoid and keepTmp are both enabled.' );
		$dp = self::getDataParsoid( $node );
		// $dp will be a DataParsoid object once but currently it is an stdClass
		// with a fake type hint. Unfake it to prevent phan complaining about unset().
		'@phan-var stdClass $dp';
		// @phan-suppress-next-line PhanRedundantCondition
		$discardDataParsoid = !empty( $options['discardDataParsoid'] );
		if ( !empty( $dp->tmp->isNew ) ) {
			// Only necessary to support the cite extension's getById,
			// that's already been loaded once.
			//
			// This is basically a hack to ensure that DOMUtils.isNewElt
			// continues to work since we effectively rely on the absence
			// of data-parsoid to identify new elements. But, loadDataAttribs
			// creates an empty {} if one doesn't exist. So, this hack
			// ensures that a loadDataAttribs + storeDataAttribs pair don't
			// dirty the node by introducing an empty data-parsoid attribute
			// where one didn't exist before.
			//
			// Ideally, we'll find a better solution for this edge case later.
			$discardDataParsoid = true;
		}
		$data = null;
		if ( !$discardDataParsoid ) {
			// @phan-suppress-next-line PhanRedundantCondition
			if ( !empty( $options['keepTmp'] ) ) {
				if ( isset( $dp->tmp->tplRanges ) ) {
					unset( $dp->tmp->tplRanges );
				}
			} else {
				unset( $dp->tmp );
			}

			if ( !empty( $options['storeInPageBundle'] ) ) {
				$data = (object)[ 'parsoid' => $dp ];
			} else {
				self::setJSONAttribute( $node, 'data-parsoid', $dp );
			}
		}
		// We need to serialize diffs only under special circumstances.
		// So, do it on demand.
		if ( !empty( $options['storeDiffMark'] ) ) {
			$dpDiff = self::getDataParsoidDiff( $node );
			if ( $dpDiff ) {
				self::setJSONAttribute( $node, 'data-parsoid-diff', $dpDiff );
			}
		}
		// Strip invalid data-mw attributes
		if ( self::validDataMw( $node ) ) {
			if (
				!empty( $options['storeInPageBundle'] ) && isset( $options['env'] ) &&
				// The pagebundle didn't have data-mw before 999.x
				Semver::satisfies( $options['env']->getOutputContentVersion(), '^999.0.0' )
			) {
				$data = $data ?: new stdClass;
				$data->mw = self::getDataMw( $node );
			} else {
				self::setJSONAttribute( $node, 'data-mw', self::getDataMw( $node ) );
			}
		}
		// Store pagebundle
		if ( $data !== null ) {
			self::storeInPageBundle( $node, $options['env'], $data, $options['idIndex'] );
		}

		// Indicate that this node's data has been stored so that if we try
		// to access it after the fact we're aware and remove the attribute
		// since it's no longer needed.
		$nd = self::getNodeData( $node );
		$nd->storedId = $node->getAttribute( self::DATA_OBJECT_ATTR_NAME );
		$node->removeAttribute( self::DATA_OBJECT_ATTR_NAME );
	}
}
