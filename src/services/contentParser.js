import { select } from '@wordpress/data';

/**
 * Extract content from Gutenberg blocks for AI processing.
 * This mirrors the PHP ContentExtractor but on the client side,
 * used for real-time content access without a server round-trip.
 */
export function getBlockContent() {
	const blocks = select( 'core/block-editor' )?.getBlocks() || [];
	return blocksToText( blocks );
}

export function getPostTitle() {
	return select( 'core/editor' )?.getEditedPostAttribute( 'title' ) || '';
}

export function getPostStatus() {
	return select( 'core/editor' )?.getEditedPostAttribute( 'status' ) || '';
}

export function getPostType() {
	return select( 'core/editor' )?.getCurrentPostType() || 'post';
}

function blocksToText( blocks ) {
	return blocks
		.map( ( block ) => blockToText( block ) )
		.filter( Boolean )
		.join( '\n\n' );
}

function blockToText( block ) {
	const { name, attributes, innerBlocks } = block;

	let text = '';

	switch ( name ) {
		case 'core/heading': {
			const level = attributes.level || 2;
			const prefix = '#'.repeat( level );
			text = `${ prefix } ${ stripHtml( attributes.content || '' ) }`;
			break;
		}
		case 'core/paragraph':
			text = stripHtml( attributes.content || '' );
			break;
		case 'core/list':
			text = extractListItems( block );
			break;
		case 'core/quote':
			text = '> ' + stripHtml( attributes.value || attributes.citation || '' );
			break;
		case 'core/image':
			text = `[image: alt='${ attributes.alt || '' }']`;
			break;
		case 'core/embed':
			text = `[embed: ${ attributes.providerNameSlug || 'embed' }]`;
			break;
		case 'core/code':
			text = '[code block]';
			break;
		default:
			if ( attributes?.content ) {
				text = stripHtml( attributes.content );
			}
			break;
	}

	if ( innerBlocks && innerBlocks.length > 0 ) {
		const inner = blocksToText( innerBlocks );
		if ( inner ) {
			text = text ? text + '\n' + inner : inner;
		}
	}

	return text;
}

function extractListItems( block ) {
	if ( block.innerBlocks && block.innerBlocks.length > 0 ) {
		return block.innerBlocks
			.map( ( item ) => '- ' + stripHtml( item.attributes?.content || '' ) )
			.join( '\n' );
	}
	// Fallback: parse from values attribute
	const values = block.attributes?.values || '';
	const items = values.match( /<li[^>]*>(.*?)<\/li>/gs ) || [];
	return items.map( ( item ) => '- ' + stripHtml( item ) ).join( '\n' );
}

function stripHtml( html ) {
	// Use DOMParser instead of innerHTML to avoid executing scripts/event handlers
	const doc = new DOMParser().parseFromString( html, 'text/html' );
	return doc.body.textContent || '';
}
