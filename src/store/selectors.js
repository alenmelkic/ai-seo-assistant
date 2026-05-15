export function isModalOpen( state ) {
	return state.isModalOpen;
}

export function getCurrentStep( state ) {
	return state.currentStep;
}

export function isLoading( state, key ) {
	return state.loading[ key ] || false;
}

export function isAnyLoading( state ) {
	return Object.values( state.loading ).some( Boolean );
}

export function getFieldLoading( state, field ) {
	return state.fieldLoading[ field ] || false;
}

export function getAnalysis( state ) {
	return state.analysis;
}

export function getMeta( state ) {
	return state.meta;
}

export function getTags( state ) {
	return state.tags;
}

export function getAeo( state ) {
	return state.aeo;
}

export function getError( state, key ) {
	return state.errors[ key ] || null;
}

export function getErrors( state ) {
	return state.errors;
}

export function getEditedMeta( state ) {
	return state.editedMeta;
}

export function getEditedTags( state ) {
	return state.editedTags;
}

export function getEditedAeo( state ) {
	return state.editedAeo;
}

export function getConfirmed( state ) {
	return state.confirmed;
}

export function isConfirming( state ) {
	return state.isConfirming;
}

export function getAppliedFixes( state ) {
	return state.appliedFixes;
}

export function hasAppliedFixes( state ) {
	return state.appliedFixes.length > 0;
}

export function getPreviousValue( state, field ) {
	return state.previousValues[ field ] || null;
}

export function isDiffVisible( state, field ) {
	return state.diffVisible[ field ] || false;
}
