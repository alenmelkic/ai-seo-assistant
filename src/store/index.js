import { createReduxStore, register } from '@wordpress/data';
import reducer from './reducer';
import * as selectors from './selectors';
import * as actions from './actions';

const STORE_NAME = 'aisa/editor';

const store = createReduxStore( STORE_NAME, {
	reducer,
	selectors,
	actions,
} );

register( store );

export { STORE_NAME };
export default store;
