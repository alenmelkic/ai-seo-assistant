import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

export default class ErrorBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = { hasError: false, error: null };
	}

	static getDerivedStateFromError( error ) {
		return { hasError: true, error };
	}

	componentDidCatch( error, errorInfo ) {
		console.error( 'AI SEO Assistant Error:', error, errorInfo );
	}

	render() {
		if ( this.state.hasError ) {
			return (
				<div className="aisa-error-boundary">
					<p>
						{ __(
							'Something went wrong with AI SEO Assistant.',
							'ai-seo-assistant'
						) }
					</p>
					<p className="aisa-error-boundary__detail">
						{ this.state.error?.message }
					</p>
					<Button
						variant="secondary"
						onClick={ () =>
							this.setState( { hasError: false, error: null } )
						}
					>
						{ __( 'Try Again', 'ai-seo-assistant' ) }
					</Button>
				</div>
			);
		}

		return this.props.children;
	}
}
