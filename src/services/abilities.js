import { generateAbility } from './api';

/**
 * Abstraction over WP Abilities API (7.0+) with REST fallback.
 * If WP Abilities API is available, use it. Otherwise fall back to REST.
 */

const hasAbilitiesApi = () => {
	const config = window.aiSeoAssistant || {};
	return config.hasAbilitiesApi && typeof wp !== 'undefined' && wp.abilities;
};

const ABILITY_MAP = {
	analyze_content: 'ai-seo-assistant/analyze-content',
	generate_meta: 'ai-seo-assistant/generate-meta',
	generate_tags: 'ai-seo-assistant/generate-tags',
	generate_aeo: 'ai-seo-assistant/generate-aeo',
};

export async function executeAbility( abilityKey, postId, regenField = null ) {
	if ( hasAbilitiesApi() ) {
		try {
			const abilityName = ABILITY_MAP[ abilityKey ];
			if ( ! abilityName ) {
				throw new Error( `Unknown ability: ${ abilityKey }` );
			}

			const input = { postId };
			if ( regenField ) {
				input.regenField = regenField;
			}

			const result = await wp.abilities.execute( abilityName, input );
			return { success: true, data: result };
		} catch ( err ) {
			// Fall back to REST if Abilities API fails
			console.warn(
				'Abilities API failed, falling back to REST:',
				err.message
			);
		}
	}

	// REST API fallback
	return generateAbility( abilityKey, postId, regenField );
}

export async function executeAllAbilities( postId ) {
	const abilities = [
		'analyze_content',
		'generate_meta',
		'generate_tags',
		'generate_aeo',
	];

	const results = await Promise.allSettled(
		abilities.map( ( key ) => executeAbility( key, postId ) )
	);

	const mapped = {};
	abilities.forEach( ( key, index ) => {
		const result = results[ index ];
		if ( result.status === 'fulfilled' && result.value?.success ) {
			mapped[ key ] = { success: true, data: result.value.data };
		} else {
			const error =
				result.status === 'rejected'
					? result.reason?.message || 'Unknown error'
					: result.value?.error || 'AI generation failed';
			mapped[ key ] = { success: false, error };
		}
	} );

	return mapped;
}
