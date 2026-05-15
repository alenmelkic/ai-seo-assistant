export const SET_MODAL_OPEN = 'SET_MODAL_OPEN';
export const SET_CURRENT_STEP = 'SET_CURRENT_STEP';
export const SET_LOADING = 'SET_LOADING';
export const SET_ANALYSIS_RESULT = 'SET_ANALYSIS_RESULT';
export const SET_META_RESULT = 'SET_META_RESULT';
export const SET_TAGS_RESULT = 'SET_TAGS_RESULT';
export const SET_AEO_RESULT = 'SET_AEO_RESULT';
export const SET_ERROR = 'SET_ERROR';
export const SET_FIELD_LOADING = 'SET_FIELD_LOADING';
export const UPDATE_META_FIELD = 'UPDATE_META_FIELD';
export const UPDATE_TAGS = 'UPDATE_TAGS';
export const UPDATE_AEO_FIELD = 'UPDATE_AEO_FIELD';
export const SET_CONFIRMED = 'SET_CONFIRMED';
export const SET_CONFIRMING = 'SET_CONFIRMING';
export const SET_APPLIED_FIXES = 'SET_APPLIED_FIXES';
export const SET_PREVIOUS_VALUE = 'SET_PREVIOUS_VALUE';
export const SET_DIFF_VISIBLE = 'SET_DIFF_VISIBLE';
export const RESET_STATE = 'RESET_STATE';

export function setModalOpen( isOpen ) {
	return { type: SET_MODAL_OPEN, isOpen };
}

export function setCurrentStep( step ) {
	return { type: SET_CURRENT_STEP, step };
}

export function setLoading( key, isLoading ) {
	return { type: SET_LOADING, key, isLoading };
}

export function setAnalysisResult( data ) {
	return { type: SET_ANALYSIS_RESULT, data };
}

export function setMetaResult( data ) {
	return { type: SET_META_RESULT, data };
}

export function setTagsResult( data ) {
	return { type: SET_TAGS_RESULT, data };
}

export function setAeoResult( data ) {
	return { type: SET_AEO_RESULT, data };
}

export function setError( key, error ) {
	return { type: SET_ERROR, key, error };
}

export function setFieldLoading( field, isLoading ) {
	return { type: SET_FIELD_LOADING, field, isLoading };
}

export function updateMetaField( field, value ) {
	return { type: UPDATE_META_FIELD, field, value };
}

export function updateTags( tags ) {
	return { type: UPDATE_TAGS, tags };
}

export function updateAeoField( field, value ) {
	return { type: UPDATE_AEO_FIELD, field, value };
}

export function setConfirmed( confirmed ) {
	return { type: SET_CONFIRMED, confirmed };
}

export function setConfirming( isConfirming ) {
	return { type: SET_CONFIRMING, isConfirming };
}

export function setAppliedFixes( fixes ) {
	return { type: SET_APPLIED_FIXES, fixes };
}

export function setPreviousValue( field, value ) {
	return { type: SET_PREVIOUS_VALUE, field, value };
}

export function setDiffVisible( field, visible ) {
	return { type: SET_DIFF_VISIBLE, field, visible };
}

export function resetState() {
	return { type: RESET_STATE };
}
