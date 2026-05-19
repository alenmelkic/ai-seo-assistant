import apiFetch from '@wordpress/api-fetch';

const getConfig = () => window.aiSeoAssistant || {};

export async function generateAbility( ability, postId, regenField = null ) {
	const body = { ability, postId };
	if ( regenField ) {
		body.regenField = regenField;
	}

	return apiFetch( {
		path: 'ai-seo-assistant/v1/generate',
		method: 'POST',
		data: body,
	} );
}

export async function confirmSEO( data ) {
	return apiFetch( {
		path: 'ai-seo-assistant/v1/confirm',
		method: 'POST',
		data,
	} );
}

export async function getAEOData( postId ) {
	return apiFetch( {
		path: `ai-seo-assistant/v1/aeo/${ parseInt( postId, 10 ) }`,
	} );
}

export async function triggerRevalidation( postId ) {
	return apiFetch( {
		path: 'ai-seo-assistant/v1/webhook/revalidate',
		method: 'POST',
		data: { postId },
	} );
}
