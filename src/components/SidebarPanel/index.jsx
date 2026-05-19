import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { PanelBody, PanelRow, Button, Tooltip } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../../store';
import usePostContent from '../../hooks/usePostContent';
import GSCPanel from './GSCPanel';

export default function SidebarPanel() {
	const dispatch = useDispatch( STORE_NAME );
	const { confirmed, aeoTldr, aeoMainQuestion } = usePostContent();

	const getStatusBadge = () => {
		if ( ! confirmed || typeof confirmed !== 'object' ) {
			return {
				text: __( 'Not reviewed', 'ai-seo-assistant' ),
				className: 'aisa-status--not-reviewed',
			};
		}
		if ( confirmed.skipped ) {
			return {
				text: __( 'Skipped', 'ai-seo-assistant' ),
				className: 'aisa-status--skipped',
			};
		}
		return {
			text: __( 'Confirmed', 'ai-seo-assistant' ),
			className: 'aisa-status--confirmed',
		};
	};

	const status = getStatusBadge();

	const handleRerun = () => {
		dispatch.setModalOpen( true );
	};

	return (
		<>
			<PluginSidebarMoreMenuItem target="aisa-sidebar">
				{ __( 'AI SEO Assistant', 'ai-seo-assistant' ) }
			</PluginSidebarMoreMenuItem>

			<PluginSidebar
				name="aisa-sidebar"
				title={ __( 'AI SEO Assistant', 'ai-seo-assistant' ) }
				icon="search"
			>
				<PanelBody
					title={ __( 'SEO Status', 'ai-seo-assistant' ) }
					initialOpen
				>
					<PanelRow>
						<div className="aisa-sidebar__status">
							<span
								className={ `aisa-status-badge ${ status.className }` }
							>
								{ status.text }
							</span>
							{ confirmed && confirmed.date && (
								<Tooltip
									text={ `${ __(
										'Confirmed by user',
										'ai-seo-assistant'
									) } #${ confirmed.user_id } ${ __(
										'on',
										'ai-seo-assistant'
									) } ${ confirmed.date }` }
								>
									<span className="aisa-sidebar__date">
										{ confirmed.date }
									</span>
								</Tooltip>
							) }
						</div>
					</PanelRow>
					<PanelRow>
						<Button variant="secondary" onClick={ handleRerun }>
							{ __( 'Re-run SEO Review', 'ai-seo-assistant' ) }
						</Button>
					</PanelRow>
				</PanelBody>

				<GSCPanel />

				<PanelBody
					title={ __( 'AEO Data', 'ai-seo-assistant' ) }
					initialOpen={ false }
				>
					{ aeoTldr ? (
						<>
							<PanelRow>
								<div className="aisa-sidebar__field">
									<strong>
										{ __( 'TL;DR', 'ai-seo-assistant' ) }
									</strong>
									<p>{ aeoTldr }</p>
								</div>
							</PanelRow>
							{ aeoMainQuestion && (
								<PanelRow>
									<div className="aisa-sidebar__field">
										<strong>
											{ __(
												'Main Question',
												'ai-seo-assistant'
											) }
										</strong>
										<p>{ aeoMainQuestion }</p>
									</div>
								</PanelRow>
							) }
						</>
					) : (
						<PanelRow>
							<em>
								{ __(
									'No AEO data yet. Run SEO Review to generate.',
									'ai-seo-assistant'
								) }
							</em>
						</PanelRow>
					) }
				</PanelBody>
			</PluginSidebar>
		</>
	);
}
