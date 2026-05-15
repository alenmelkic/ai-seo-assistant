import {
	SET_MODAL_OPEN,
	SET_CURRENT_STEP,
	SET_LOADING,
	SET_ANALYSIS_RESULT,
	SET_META_RESULT,
	SET_TAGS_RESULT,
	SET_AEO_RESULT,
	SET_ERROR,
	SET_FIELD_LOADING,
	UPDATE_META_FIELD,
	UPDATE_TAGS,
	UPDATE_AEO_FIELD,
	SET_CONFIRMED,
	SET_CONFIRMING,
	SET_APPLIED_FIXES,
	SET_PREVIOUS_VALUE,
	SET_DIFF_VISIBLE,
	RESET_STATE,
} from './actions';

const DEFAULT_STATE = {
	isModalOpen: false,
	currentStep: 1,

	// Loading states per ability
	loading: {
		analyze_content: false,
		generate_meta: false,
		generate_tags: false,
		generate_aeo: false,
	},

	// Per-field loading (for regeneration)
	fieldLoading: {},

	// AI results
	analysis: null,
	meta: null,
	tags: null,
	aeo: null,

	// Errors per ability
	errors: {},

	// Edited values (user modifications to AI suggestions)
	editedMeta: {
		meta_title: '',
		meta_description: '',
		focus_keyword: '',
	},
	editedTags: [],
	editedAeo: {
		tldr: '',
		main_question: '',
		faq: [],
		entities: [],
	},

	// Previous values for diff view
	previousValues: {},
	diffVisible: {},

	// Confirm state
	confirmed: null,
	isConfirming: false,

	// Track which auto-fixes were applied (to trigger re-analyze)
	appliedFixes: [],
};

export default function reducer( state = DEFAULT_STATE, action ) {
	switch ( action.type ) {
		case SET_MODAL_OPEN:
			return { ...state, isModalOpen: action.isOpen };

		case SET_CURRENT_STEP:
			return { ...state, currentStep: action.step };

		case SET_LOADING:
			return {
				...state,
				loading: { ...state.loading, [ action.key ]: action.isLoading },
			};

		case SET_ANALYSIS_RESULT:
			return { ...state, analysis: action.data };

		case SET_META_RESULT:
			return {
				...state,
				meta: action.data,
				editedMeta: {
					meta_title: action.data?.meta_title || '',
					meta_description: action.data?.meta_description || '',
					focus_keyword: action.data?.focus_keyword || '',
				},
			};

		case SET_TAGS_RESULT:
			return {
				...state,
				tags: action.data,
				editedTags: action.data?.tags || [],
			};

		case SET_AEO_RESULT:
			return {
				...state,
				aeo: action.data,
				editedAeo: {
					tldr: action.data?.tldr || '',
					main_question: action.data?.main_question || '',
					faq: action.data?.faq || [],
					entities: action.data?.entities || [],
				},
			};

		case SET_ERROR:
			return {
				...state,
				errors: { ...state.errors, [ action.key ]: action.error },
			};

		case SET_FIELD_LOADING:
			return {
				...state,
				fieldLoading: {
					...state.fieldLoading,
					[ action.field ]: action.isLoading,
				},
			};

		case UPDATE_META_FIELD:
			return {
				...state,
				editedMeta: { ...state.editedMeta, [ action.field ]: action.value },
			};

		case UPDATE_TAGS:
			return { ...state, editedTags: action.tags };

		case UPDATE_AEO_FIELD:
			return {
				...state,
				editedAeo: { ...state.editedAeo, [ action.field ]: action.value },
			};

		case SET_CONFIRMED:
			return { ...state, confirmed: action.confirmed };

		case SET_CONFIRMING:
			return { ...state, isConfirming: action.isConfirming };

		case SET_APPLIED_FIXES:
			return { ...state, appliedFixes: action.fixes };

		case SET_PREVIOUS_VALUE:
			return {
				...state,
				previousValues: {
					...state.previousValues,
					[ action.field ]: action.value,
				},
			};

		case SET_DIFF_VISIBLE:
			return {
				...state,
				diffVisible: {
					...state.diffVisible,
					[ action.field ]: action.visible,
				},
			};

		case RESET_STATE:
			return { ...DEFAULT_STATE };

		default:
			return state;
	}
}
