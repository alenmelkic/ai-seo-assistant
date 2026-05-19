import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { dispatch as wpDispatch } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { STORE_NAME } from '../../store';

export default function AutoFixButton( { autofix, onApplied } ) {
	const storeDispatch = useDispatch( STORE_NAME );

	if ( ! autofix?.available ) {
		return null;
	}

	const handleApply = () => {
		const blockEditor = wpDispatch( 'core/block-editor' );
		if ( ! blockEditor ) return;

		const ALLOWED_BLOCK_TYPES = [ 'heading', 'paragraph', 'list', 'quote' ];

		try {
			switch ( autofix.action ) {
				case 'insert_block': {
					const { block_type, content, position } = autofix.data || {};
					if ( ! block_type || ! content ) break;

					// Only allow safe block types — reject arbitrary types like 'html'
					if ( ! ALLOWED_BLOCK_TYPES.includes( block_type ) ) {
						console.warn( 'Auto-fix: blocked disallowed block type:', block_type );
						break;
					}

					let newBlock;
					if ( block_type === 'heading' ) {
						newBlock = createBlock( 'core/heading', {
							content,
							level: autofix.data.level || 2,
						} );
					} else {
						newBlock = createBlock( `core/${ block_type }`, {
							content,
						} );
					}

					// Parse position like "before_paragraph_3"
					const match = position?.match( /(\d+)/ );
					const index = match ? parseInt( match[ 1 ], 10 ) : 0;

					blockEditor.insertBlock( newBlock, index );
					break;
				}
				case 'modify_text': {
					const { original, replacement } = autofix.data || {};
					if ( ! original || ! replacement ) break;

					const blocks =
						wp.data
							.select( 'core/block-editor' )
							.getBlocks() || [];

					// Escape $ metacharacters in replacement to prevent String.replace() special patterns
					const safeReplacement = replacement.replace( /\$/g, '$$$$' );

					for ( const block of blocks ) {
						if (
							block.attributes?.content &&
							block.attributes.content.includes( original )
						) {
							blockEditor.updateBlockAttributes( block.clientId, {
								content: block.attributes.content.replace(
									original,
									safeReplacement
								),
							} );
							break;
						}
					}
					break;
				}
				case 'reorder': {
					// Reorder is complex — skip for now, log
					console.info(
						'Auto-fix reorder not yet implemented',
						autofix.data
					);
					break;
				}
			}

			if ( onApplied ) {
				onApplied( autofix );
			}
		} catch ( err ) {
			console.error( 'Auto-fix failed:', err );
		}
	};

	return (
		<Button
			variant="secondary"
			size="small"
			onClick={ handleApply }
			className="aisa-autofix-btn"
		>
			{ __( 'Apply Auto-fix', 'ai-seo-assistant' ) }
		</Button>
	);
}
