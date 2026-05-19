import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardFooter,
	CardHeader,
	ExternalLink,
	Flex,
	FlexBlock,
	FlexItem,
	Notice,
	TextControl,
	/* eslint-disable @wordpress/no-unsafe-wp-apis */
	__experimentalText as Text,
	/* eslint-enable @wordpress/no-unsafe-wp-apis */
} from '@wordpress/components';

export function App( { config } ) {
	const [ postType, setPostType ] = useState( config.suggestedPostType || 'faq' );
	const [ taxonomy, setTaxonomy ] = useState( config.defaultTaxonomy || 'faq-category' );
	const [ error, setError ] = useState( '' );
	const [ isBusy, setIsBusy ] = useState( false );

	const completeSetup = async () => {
		setIsBusy( true );
		setError( '' );

		try {
			const response = await apiFetch( {
				path: '/forwp-faq/v1/setup/complete',
				method: 'POST',
				data: { post_type: postType, taxonomy },
			} );

			window.location.href = response.redirect || config.dashboardUrl;
		} catch ( err ) {
			setError( err?.message || __( 'Setup could not be saved.', '4wp-faq' ) );
			setIsBusy( false );
		}
	};

	const skipSetup = async () => {
		setIsBusy( true );
		setError( '' );

		try {
			const response = await apiFetch( {
				path: '/forwp-faq/v1/setup/skip',
				method: 'POST',
			} );

			window.location.href = response.redirect || config.dashboardUrl;
		} catch ( err ) {
			setError( err?.message || __( 'Could not skip setup.', '4wp-faq' ) );
			setIsBusy( false );
		}
	};

	const body = (
		<>
			{ config.isSkipped && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Registry setup was skipped. JSON-LD still works on the front end. Enable the registry below when you are ready.',
						'4wp-faq'
					) }
				</Notice>
			) }

			{ config.hasLegacyPosts && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'Existing registry posts (forwp_faq) were detected. Keep that slug or choose a new one.',
						'4wp-faq'
					) }
				</Notice>
			) }

			{ error && (
				<Notice status="error" isDismissible={ false } onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }

			<Text>
				{ __(
					'Optional. FAQ content stays in your pages and blocks. This step only creates an admin post type that lists questions found across the site. Schema JSON-LD already works without this step.',
					'4wp-faq'
				) }
			</Text>

			<TextControl
				label={ __( 'Post type slug', '4wp-faq' ) }
				help={ __( 'Default: faq', '4wp-faq' ) }
				value={ postType }
				onChange={ setPostType }
				disabled={ isBusy }
				__nextHasNoMarginBottom
			/>

			<TextControl
				label={ __( 'Category taxonomy slug', '4wp-faq' ) }
				help={ __( 'Default: faq-category', '4wp-faq' ) }
				value={ taxonomy }
				onChange={ setTaxonomy }
				disabled={ isBusy }
				__nextHasNoMarginBottom
			/>
		</>
	);

	const actions = (
		<Flex align="center" gap={ 2 } wrap>
			<Button
				variant="primary"
				onClick={ completeSetup }
				disabled={ isBusy }
				isBusy={ isBusy }
			>
				{ __( 'Enable FAQ registry', '4wp-faq' ) }
			</Button>
			<Button variant="secondary" onClick={ skipSetup } disabled={ isBusy }>
				{ __( 'Skip for now', '4wp-faq' ) }
			</Button>
			<FlexItem>
				<ExternalLink href={ config.dashboardUrl }>
					{ __( 'Back to Dashboard', '4wp-faq' ) }
				</ExternalLink>
			</FlexItem>
		</Flex>
	);

	return (
		<div className="wrap">
			<Card>
				<CardHeader>
					<Flex align="center" justify="space-between">
						<FlexBlock>
							<Text variant="muted">{ __( 'Step 1 of 1', '4wp-faq' ) }</Text>
							<h1 style={ { margin: '4px 0 0', fontSize: '20px', fontWeight: 500 } }>
								{ __( 'Set up your FAQ registry', '4wp-faq' ) }
							</h1>
						</FlexBlock>
					</Flex>
				</CardHeader>
				<CardBody>{ body }</CardBody>
				<CardFooter>{ actions }</CardFooter>
			</Card>
		</div>
	);
}
