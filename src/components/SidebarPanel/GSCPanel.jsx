import { PanelBody, PanelRow, Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useCallback } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

export default function GSCPanel() {
	const [ gscData, setGscData ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ fetched, setFetched ] = useState( false );

	const { postId, gscConnected } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		const config = window.aiSeoAssistant || {};
		return {
			postId: editor?.getCurrentPostId(),
			gscConnected: config.gscConnected || false,
		};
	}, [] );

	const fetchData = useCallback(
		async ( refresh = false ) => {
			if ( ! postId ) return;
			setLoading( true );
			try {
				const result = await apiFetch( {
					path: `ai-seo-assistant/v1/gsc/${ parseInt( postId, 10 ) }?refresh=${ refresh ? '1' : '0' }`,
				} );
				setGscData( result?.data || null );
				setFetched( true );
			} catch {
				setGscData( null );
			} finally {
				setLoading( false );
			}
		},
		[ postId ]
	);

	if ( ! gscConnected ) {
		return (
			<PanelBody
				title={ __( 'Search Console', 'ai-seo-assistant' ) }
				initialOpen={ false }
			>
				<PanelRow>
					<em>
						{ __(
							'Connect Google Search Console in settings to see search data here.',
							'ai-seo-assistant'
						) }
					</em>
				</PanelRow>
			</PanelBody>
		);
	}

	return (
		<PanelBody
			title={ __( 'Search Console', 'ai-seo-assistant' ) }
			initialOpen={ false }
			onToggle={ ( isOpen ) => {
				if ( isOpen && ! fetched ) {
					fetchData();
				}
			} }
		>
			{ loading && (
				<PanelRow>
					<Spinner />
					<span>
						{ __( 'Loading GSC data...', 'ai-seo-assistant' ) }
					</span>
				</PanelRow>
			) }

			{ ! loading && ! gscData && fetched && (
				<PanelRow>
					<em>
						{ __(
							'No search data available for this post yet.',
							'ai-seo-assistant'
						) }
					</em>
				</PanelRow>
			) }

			{ ! loading && gscData && (
				<>
					{ gscData.top_queries?.length > 0 && (
						<PanelRow>
							<div className="aisa-sidebar__field aisa-gsc-queries">
								<strong>
									{ __( 'Top Queries (28d)', 'ai-seo-assistant' ) }
								</strong>
								<table className="aisa-gsc-table">
									<thead>
										<tr>
											<th>
												{ __( 'Query', 'ai-seo-assistant' ) }
											</th>
											<th>
												{ __( 'Pos', 'ai-seo-assistant' ) }
											</th>
											<th>
												{ __( 'CTR', 'ai-seo-assistant' ) }
											</th>
										</tr>
									</thead>
									<tbody>
										{ gscData.top_queries
											.slice( 0, 5 )
											.map( ( q, i ) => (
												<tr key={ i }>
													<td
														title={ q.query }
														className="aisa-gsc-query-text"
													>
														{ q.query }
													</td>
													<td>{ q.position }</td>
													<td>{ q.ctr }%</td>
												</tr>
											) ) }
									</tbody>
								</table>
							</div>
						</PanelRow>
					) }

					{ gscData.position_trend !== null && (
						<PanelRow>
							<div className="aisa-sidebar__field">
								<strong>
									{ __( 'Position Trend', 'ai-seo-assistant' ) }
								</strong>
								<p
									style={ {
										color:
											gscData.position_trend > 0
												? '#d63638'
												: '#00a32a',
									} }
								>
									{ gscData.position_trend > 0 ? '\u2193' : '\u2191' }{ ' ' }
									{ Math.abs( gscData.position_trend ) }{ ' ' }
									{ __(
										'positions in 28 days',
										'ai-seo-assistant'
									) }
								</p>
							</div>
						</PanelRow>
					) }

					<PanelRow>
						<Button
							variant="link"
							size="small"
							onClick={ () => fetchData( true ) }
							disabled={ loading }
						>
							{ __( 'Refresh data', 'ai-seo-assistant' ) }
						</Button>
					</PanelRow>
				</>
			) }
		</PanelBody>
	);
}
