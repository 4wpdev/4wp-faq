/**
 * FAQ admin settings (4wp-weather shell): stats, scan, SEO, reset.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Card,
	CardBody,
	CardHeader,
	Button,
	Spinner,
	Notice,
	ExternalLink,
	ToggleControl,
	Modal,
} from '@wordpress/components';

const SETTINGS_PATH = '/forwp-faq/v1/settings';
const REGISTRY_PATH = '/forwp-faq/v1/registry';
const SCAN_PATH = '/forwp-faq/v1/registry/scan';
const RESET_PATH = '/forwp-faq/v1/setup/reset';

function setupApiFetch() {
	if (
		typeof window === 'undefined' ||
		! window.forwpFaqAdmin ||
		window.forwpFaqAdmin.__middlewareApplied
	) {
		return;
	}
	const { restRoot, nonce } = window.forwpFaqAdmin;
	const root = restRoot.endsWith( '/' ) ? restRoot : `${ restRoot }/`;
	apiFetch.use( apiFetch.createRootURLMiddleware( root ) );
	apiFetch.use( apiFetch.createNonceMiddleware( nonce ) );
	window.forwpFaqAdmin.__middlewareApplied = true;
}

function StatCell( { label, value, hint } ) {
	return (
		<div className="forwp-faq-stat">
			<span className="forwp-faq-stat__value">{ value }</span>
			<span className="forwp-faq-stat__label">{ label }</span>
			{ hint ? (
				<span className="forwp-faq-stat__hint">{ hint }</span>
			) : null }
		</div>
	);
}

function StatsCard( { stats, setupComplete } ) {
	if ( ! stats ) {
		return null;
	}

	return (
		<Card className="forwp-faq-stats-card">
			<CardHeader>
				<h2>{ __( 'Overview', '4wp-faq' ) }</h2>
			</CardHeader>
			<CardBody>
				<div className="forwp-faq-stats-grid">
					<StatCell
						label={ __( 'Unique questions (registry)', '4wp-faq' ) }
						value={ setupComplete ? stats.total_questions : '—' }
						hint={
							setupComplete
								? __( 'After last scan', '4wp-faq' )
								: __( 'Enable registry setup', '4wp-faq' )
						}
					/>
					<StatCell
						label={ __( 'Reused on multiple pages', '4wp-faq' ) }
						value={ setupComplete ? stats.reused_questions : '—' }
					/>
					<StatCell
						label={ __( 'FAQ items in content', '4wp-faq' ) }
						value={ stats.faq_items_in_content }
					/>
					<StatCell
						label={ __( '4WP FAQ blocks', '4wp-faq' ) }
						value={ stats.faq_blocks }
					/>
					<StatCell
						label={ __( 'Pages with FAQ', '4wp-faq' ) }
						value={ stats.pages_with_faq }
					/>
					<StatCell
						label={ __( 'Posts with FAQ', '4wp-faq' ) }
						value={ stats.posts_with_faq }
					/>
					<StatCell
						label={ __( 'Other content types', '4wp-faq' ) }
						value={ stats.other_content_with_faq }
					/>
					<StatCell
						label={ __( 'FAQ categories', '4wp-faq' ) }
						value={ setupComplete ? stats.faq_categories : '—' }
					/>
				</div>
			</CardBody>
		</Card>
	);
}

function SettingsTab() {
	const [ loading, setLoading ] = useState( true );
	const [ scanning, setScanning ] = useState( false );
	const [ savingSeo, setSavingSeo ] = useState( false );
	const [ resetting, setResetting ] = useState( false );
	const [ registry, setRegistry ] = useState( null );
	const [ settings, setSettings ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );
	const [ resetOpen, setResetOpen ] = useState( false );

	const loadAll = useCallback( () => {
		setLoading( true );
		setError( '' );
		return Promise.all( [
			apiFetch( { path: SETTINGS_PATH } ),
			apiFetch( { path: REGISTRY_PATH } ),
		] )
			.then( ( [ settingsData, registryData ] ) => {
				setSettings( settingsData );
				setRegistry( registryData );
			} )
			.catch( ( e ) => {
				setError(
					e?.message ||
						__( 'Could not load settings.', '4wp-faq' )
				);
			} )
			.finally( () => setLoading( false ) );
	}, [] );

	useEffect( () => {
		setupApiFetch();
		loadAll();
	}, [ loadAll ] );

	const onScan = () => {
		setScanning( true );
		setNotice( '' );
		setError( '' );
		apiFetch( { path: SCAN_PATH, method: 'POST' } )
			.then( ( response ) => {
				setRegistry( response );
				setNotice( __( 'Registry scan completed.', '4wp-faq' ) );
			} )
			.catch( ( e ) => {
				setError(
					e?.message ||
						__( 'Scan failed. Please try again.', '4wp-faq' )
				);
			} )
			.finally( () => setScanning( false ) );
	};

	const onToggleJsonLd = ( enabled ) => {
		setSavingSeo( true );
		setError( '' );
		apiFetch( {
			path: SETTINGS_PATH,
			method: 'POST',
			data: { output_json_ld: enabled },
		} )
			.then( ( response ) => {
				setSettings( response );
				setNotice(
					enabled
						? __( 'JSON-LD enabled site-wide.', '4wp-faq' )
						: __( 'JSON-LD disabled site-wide.', '4wp-faq' )
				);
			} )
			.catch( ( e ) => {
				setError(
					e?.message ||
						__( 'Could not save SEO setting.', '4wp-faq' )
				);
			} )
			.finally( () => setSavingSeo( false ) );
	};

	const onConfirmReset = () => {
		setResetting( true );
		setError( '' );
		apiFetch( { path: RESET_PATH, method: 'POST' } )
			.then( ( response ) => {
				setResetOpen( false );
				if ( response?.redirect ) {
					window.location.href = response.redirect;
				}
			} )
			.catch( ( e ) => {
				setError(
					e?.message ||
						__( 'Reset failed. Please try again.', '4wp-faq' )
				);
				setResetOpen( false );
			} )
			.finally( () => setResetting( false ) );
	};

	if ( loading ) {
		return (
			<div className="forwp-faq-tab-loading">
				<Spinner />
			</div>
		);
	}

	const setupComplete = registry?.setup_complete;
	const stats = registry?.stats;
	const jsonLdOn = settings?.output_json_ld === true;

	return (
		<div className="forwp-faq-settings-layout">
			{ notice ? (
				<Notice
					status="success"
					isDismissible
					onRemove={ () => setNotice( '' ) }
				>
					{ notice }
				</Notice>
			) : null }
			{ error ? (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) : null }

			<StatsCard stats={ stats } setupComplete={ setupComplete } />

			<Card className="forwp-faq-actions-card">
				<CardHeader>
					<h2>{ __( 'Actions', '4wp-faq' ) }</h2>
				</CardHeader>
				<CardBody>
					<div className="forwp-faq-actions-row">
						<Button
							variant="primary"
							onClick={ onScan }
							isBusy={ scanning }
							disabled={ scanning || ! setupComplete }
						>
							{ scanning
								? __( 'Scanning…', '4wp-faq' )
								: __( 'Rescan registry', '4wp-faq' ) }
						</Button>
						{ ! setupComplete && registry?.setup_url ? (
							<ExternalLink href={ registry.setup_url }>
								{ __( 'Complete setup to enable scan', '4wp-faq' ) }
							</ExternalLink>
						) : null }
					</div>

					{ setupComplete && registry?.last_scan_label ? (
						<p className="forwp-faq-actions-card__meta">
							{ sprintf(
								/* translators: %s: date/time */
								__( 'Last scan: %s', '4wp-faq' ),
								registry.last_scan_label
							) }
						</p>
					) : null }

					{ setupComplete && registry?.registry_url ? (
						<p>
							<ExternalLink href={ registry.registry_url }>
								{ __( 'View all FAQ entries', '4wp-faq' ) }
							</ExternalLink>
							{ registry.post_type ? (
								<span className="forwp-faq-actions-card__meta">
									{ ' ' }
									(
									{ sprintf(
										/* translators: %s: post type slug */
										__( 'post type: %s', '4wp-faq' ),
										registry.post_type
									) }
									)
								</span>
							) : null }
						</p>
					) : null }

					<hr className="forwp-faq-actions-divider" />

					<ToggleControl
						label={ __( 'SEO: FAQPage JSON-LD', '4wp-faq' ) }
						help={
							jsonLdOn
								? __(
										'When enabled, JSON-LD is generated automatically for all 4WP FAQ blocks. You can turn it off per block in the block sidebar.',
										'4wp-faq'
								  )
								: __(
										'When disabled, JSON-LD is off by default. You can enable it for individual blocks in the block sidebar. Recommended for SEO when you want structured data site-wide.',
										'4wp-faq'
								  )
						}
						checked={ jsonLdOn }
						disabled={ savingSeo }
						onChange={ onToggleJsonLd }
					/>

					{ setupComplete ? (
						<>
							<hr className="forwp-faq-actions-divider" />
							<p className="forwp-faq-reset-intro">
								{ __(
									'Run setup again to change registry post type or taxonomy slugs.',
									'4wp-faq'
								) }
							</p>
							<Button
								variant="secondary"
								isDestructive
								onClick={ () => setResetOpen( true ) }
							>
								{ __( 'Reset setup', '4wp-faq' ) }
							</Button>
						</>
					) : null }
				</CardBody>
			</Card>

			{ resetOpen ? (
				<Modal
					title={ __( 'Reset FAQ setup?', '4wp-faq' ) }
					onRequestClose={ () => ! resetting && setResetOpen( false ) }
					shouldCloseOnClickOutside={ ! resetting }
					shouldCloseOnEsc={ ! resetting }
				>
					<p>
						{ __(
							'You can choose new registry slugs in the setup wizard, but all FAQ categories will be removed. Registry entries stay on the previous post type until you complete setup and run a new scan.',
							'4wp-faq'
						) }
					</p>
					<div className="forwp-faq-modal-actions">
						<Button
							variant="secondary"
							onClick={ () => setResetOpen( false ) }
							disabled={ resetting }
						>
							{ __( 'Cancel', '4wp-faq' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							isBusy={ resetting }
							disabled={ resetting }
							onClick={ onConfirmReset }
						>
							{ __( 'Reset setup', '4wp-faq' ) }
						</Button>
					</div>
				</Modal>
			) : null }
		</div>
	);
}

function DocSection( { title, children } ) {
	return (
		<Card className="forwp-faq-doc-card">
			<CardHeader>
				<h2>{ title }</h2>
			</CardHeader>
			<CardBody className="forwp-faq-doc-card__body">{ children }</CardBody>
		</Card>
	);
}

function DocumentationTab() {
	return (
		<div className="forwp-faq-docs-layout">
			<DocSection title={ __( 'Overview', '4wp-faq' ) }>
				<p>
					{ __(
						'4WP FAQ is a thin wrapper around WordPress core Accordion. It does not replace your blocks or theme styles. It adds an intelligence layer: FAQPage JSON-LD on the front end, optional aggregation into a FAQ registry post type, and usage statistics in the admin.',
						'4wp-faq'
					) }
				</p>
				<p>
					{ __(
						'Your questions and answers stay in the page content (Accordion items). The registry is a read-only aggregate for browsing and reuse tracking—not a second place to edit FAQ text.',
						'4wp-faq'
					) }
				</p>
			</DocSection>

			<DocSection title={ __( 'Convert to FAQ', '4wp-faq' ) }>
				<p>
					{ __(
						'You can convert an existing Accordion, Accordion Item, or Accordion Group into a 4WP FAQ block without rebuilding the layout.',
						'4wp-faq'
					) }
				</p>
				<ol className="forwp-faq-doc-list">
					<li>
						{ __(
							'Select a core Accordion, Accordion Item, or Accordion Group in the editor.',
							'4wp-faq'
						) }
					</li>
					<li>
						{ __(
							'In the block toolbar, click Convert to FAQ (help icon).',
							'4wp-faq'
						) }
					</li>
					<li>
						{ __(
							'The plugin wraps your markup in forwp/faq. Inner Accordion blocks and styling stay the same on the front end.',
							'4wp-faq'
						) }
					</li>
				</ol>
				<p>
					{ __(
						'Each Accordion Item (or legacy Details block) inside the wrapper counts as one FAQ question for JSON-LD and for the registry scan.',
						'4wp-faq'
					) }
				</p>
			</DocSection>

			<DocSection title={ __( 'Block structure', '4wp-faq' ) }>
				<p>
					{ __( 'Recommended pattern (WordPress 6.x core Accordion):', '4wp-faq' ) }
				</p>
				<pre className="forwp-faq-doc-pre">
					<code>{ `forwp/faq                    ← 4WP FAQ wrapper
└── core/accordion           ← container
    └── core/accordion-item  ← one Q&A
        ├── accordion heading
        └── accordion panel` }</code>
				</pre>
				<p>
					{ __(
						'Legacy core/details blocks inside forwp/faq are still supported. Prefer Accordion + Accordion Item for new pages.',
						'4wp-faq'
					) }
				</p>
				<p>
					<strong>{ __( 'Question & answer', '4wp-faq' ) }</strong>
					{ ' — ' }
					{ __(
						'For Accordion Item, the heading (or title attribute) is the question; panel inner blocks are the answer. For Details, summary is the question and inner content is the answer.',
						'4wp-faq'
					) }
				</p>
			</DocSection>

			<DocSection title={ __( 'JSON-LD (SEO)', '4wp-faq' ) }>
				<p>
					{ __(
						'On singular posts and pages, the plugin can output FAQPage structured data (JSON-LD) in the footer when FAQ blocks are present.',
						'4wp-faq'
					) }
				</p>
				<ul className="forwp-faq-doc-list">
					<li>
						{ __(
							'Site-wide default: Settings tab → SEO: FAQPage JSON-LD (off by default; enabling is recommended for SEO).',
							'4wp-faq'
						) }
					</li>
					<li>
						{ __(
							'When enabled site-wide: every forwp/faq block outputs schema unless you set JSON-LD on front end → Off for this block in the block sidebar.',
							'4wp-faq'
						) }
					</li>
					<li>
						{ __(
							'When disabled site-wide: no schema by default; enable per block with On for this block in the sidebar.',
							'4wp-faq'
						) }
					</li>
				</ul>
				<p className="forwp-faq-doc-muted">
					{ __(
						'JSON-LD does not require the FAQ registry. Blocks and schema work even if you never complete registry setup.',
						'4wp-faq'
					) }
				</p>
			</DocSection>

			<DocSection title={ __( 'FAQ registry (optional)', '4wp-faq' ) }>
				<p>
					{ __(
						'After you complete setup (Settings → 4WP FAQ Setup), the plugin registers a custom post type and taxonomy (default slugs: faq and faq-category).',
						'4wp-faq'
					) }
				</p>
				<p>
					{ __(
						'A background scan walks published content, finds all forwp/faq blocks, and creates or updates registry posts—one per unique question—with metadata:',
						'4wp-faq'
					) }
				</p>
				<ul className="forwp-faq-doc-list">
					<li>
						{ __( 'Where the question is used (pages, posts, other types)', '4wp-faq' ) }
					</li>
					<li>{ __( 'How many times it appears (reuse count)', '4wp-faq' ) }</li>
					<li>{ __( 'Collected answer text and HTML snapshots', '4wp-faq' ) }</li>
				</ul>
				<p>
					{ __(
						'Run Rescan registry on the Settings tab after you add or change FAQ content. Scans also schedule automatically when relevant posts are saved.',
						'4wp-faq'
					) }
				</p>
				<p>
					{ __(
						'Reset setup (Settings tab) lets you change registry slugs in the wizard again but removes all FAQ categories; existing registry entries stay on the previous post type until you complete setup and scan again.',
						'4wp-faq'
					) }
				</p>
			</DocSection>

			<DocSection title={ __( 'Typical workflow', '4wp-faq' ) }>
				<ol className="forwp-faq-doc-list">
					<li>
						{ __(
							'Add or convert Accordion blocks to forwp/faq on your pages.',
							'4wp-faq'
						) }
					</li>
					<li>
						{ __(
							'Enable JSON-LD on the Settings tab if you want structured data site-wide.',
							'4wp-faq'
						) }
					</li>
					<li>
						{ __(
							'Optional: complete FAQ registry setup and run a scan.',
							'4wp-faq'
						) }
					</li>
					<li>
						{ __(
							'Review Overview stats and open FAQ entries under the FAQ admin menu.',
							'4wp-faq'
						) }
					</li>
				</ol>
			</DocSection>
		</div>
	);
}

export default function App() {
	const [ activeTab, setActiveTab ] = useState( 'settings' );

	return (
		<div className="forwp-faq-admin-app">
			<div className="forwp-faq-tab-panel components-tab-panel">
				<div
					className="components-tab-panel__tabs"
					role="tablist"
					aria-label={ __( '4WP FAQ', '4wp-faq' ) }
				>
					<button
						type="button"
						role="tab"
						id="forwp-faq-tab-settings"
						className={
							'components-button components-tab-panel__tabs-item forwp-faq-tab-settings' +
							( activeTab === 'settings' ? ' is-active' : '' )
						}
						aria-selected={ activeTab === 'settings' }
						aria-controls="forwp-faq-panel-settings"
						tabIndex={ activeTab === 'settings' ? 0 : -1 }
						onClick={ () => setActiveTab( 'settings' ) }
					>
						{ __( 'Settings', '4wp-faq' ) }
					</button>
					<button
						type="button"
						role="tab"
						id="forwp-faq-tab-documentation"
						className={
							'components-button components-tab-panel__tabs-item forwp-faq-tab-docs' +
							( activeTab === 'documentation' ? ' is-active' : '' )
						}
						aria-selected={ activeTab === 'documentation' }
						aria-controls="forwp-faq-panel-documentation"
						tabIndex={ activeTab === 'documentation' ? 0 : -1 }
						onClick={ () => setActiveTab( 'documentation' ) }
					>
						{ __( 'Documentation', '4wp-faq' ) }
					</button>
				</div>
				<div
					id="forwp-faq-panel-settings"
					role="tabpanel"
					aria-labelledby="forwp-faq-tab-settings"
					className="components-tab-panel__tab-content"
					hidden={ activeTab !== 'settings' }
				>
					<SettingsTab />
				</div>
				<div
					id="forwp-faq-panel-documentation"
					role="tabpanel"
					aria-labelledby="forwp-faq-tab-documentation"
					className="components-tab-panel__tab-content"
					hidden={ activeTab !== 'documentation' }
				>
					<DocumentationTab />
				</div>
			</div>
		</div>
	);
}
