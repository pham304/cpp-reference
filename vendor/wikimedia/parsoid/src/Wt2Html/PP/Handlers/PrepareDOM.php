<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\PP\Handlers;

use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\DOM\Node;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\Utils;
use Wikimedia\Parsoid\Utils\WTUtils;

class PrepareDOM {
	/**
	 * Migrate data-parsoid attributes into a property on each DOM node.
	 * We may migrate them back in the final DOM traversal.
	 *
	 * Various mw metas are converted to comments before the tree build to
	 * avoid fostering. Piggy-backing the reconversion here to avoid excess
	 * DOM traversals.
	 *
	 * @param array &$seenDataIds
	 * @param Node $node
	 * @param Env $env
	 * @return bool|mixed
	 */
	public static function handler( array &$seenDataIds, Node $node, Env $env ) {
		if ( $node instanceof Element ) {
			// Deduplicate docIds that come from splitting nodes because of
			// content model violations when treebuilding.
			if ( $node->hasAttribute( DOMDataUtils::DATA_OBJECT_ATTR_NAME ) ) {
				$docId = $node->getAttribute( DOMDataUtils::DATA_OBJECT_ATTR_NAME ) ?? '';
				if ( isset( $seenDataIds[$docId] ) ) {
					$data = DOMDataUtils::getNodeData( $node );
					DOMDataUtils::setNodeData( $node, Utils::clone( $data ) );
				} else {
					$seenDataIds[$docId] = true;
				}
			}
			// Set title to display when present (last one wins).
			if ( DOMCompat::nodeName( $node ) === 'meta'
				&& $node->getAttribute( 'property' ) === 'mw:PageProp/displaytitle'
			) {
				// PORT-FIXME: Meh
				// $env->getPageConfig()->meta->displayTitle = $node->getAttribute( 'content' ) ?? '';
			}
			return true;
		}
		$meta = WTUtils::reinsertFosterableContent( $env, $node );
		return $meta ?? true;
	}
}
