<?php

namespace MediaWiki\Extension\DiscordRCFeed;

use ChangesList;
use Flow\Container;
use IContextSource;
use MediaWiki\MediaWikiServices;
use RecentChange;
use Sanitizer;
use Wikimedia\Message\ScalarParam;

class FlowDiscordFormatter extends \Flow\Formatter\ChangesListFormatter {

	/** @var bool */
	private $plaintext = false;

	/** @var IContextSource */
	private $context;

	/** @var array */
	private $data;

	/** @var HtmlToDiscordConverter */
	private $converter;

	/** @var string[] */
	private $i18nProperties;

	/**
	 * @inheritDoc
	 */
	protected function getHistoryType() {
		return '';
	}

	/**
	 * @param RecentChange $rc
	 * @param HtmlToDiscordConverter $converter
	 * @param bool $plaintext
	 */
	public function __construct( RecentChange $rc, HtmlToDiscordConverter $converter, $plaintext = true ) {
		$permissions = MediaWikiServices::getInstance()->getService( 'FlowPermissions' );
		$revisionFormatter = Container::get( 'formatter.revision.factory' )->create();
		parent::__construct( $permissions, $revisionFormatter );

		$this->plaintext = $plaintext;
		$this->converter = $converter;

		// Additional $rc specific initializing
		$query = Container::get( 'query.changeslist' );
		$this->context = Util::getContentLanguageContext();
		$changesList = new ChangesList( $this->context );
		$row = $query->getResult( $changesList, $rc );

		// Get data for formatting
		$this->serializer->setIncludeHistoryProperties( true );
		$this->serializer->setIncludeContent( true );
		$data = $this->serializer->formatApi( $row, $this->context, 'recentchanges' );
		if ( $data && is_array( $data ) ) {
			$this->storeI18Properties( $data['properties'] );
			$this->data = $this->modifyData( $data );
		}
	}

	/**
	 * @param array $properties
	 */
	private function storeI18Properties( $properties ) {
		$keyMap = [
			'summary' => [
				'moderated-reason',
			],
			'post-of-summary' => [
				'topic-of-post-text-from-html',
			],
		];
		foreach ( $keyMap as $target => $sources ) {
			if ( isset( $properties[$target] ) ) {
				$property = $properties[$target];
				$this->i18nProperties[$target] = $this->resolveArrayParam( $property, 'plaintext' ) ?: $property;
				unset( $properties[$target] );
			}
			foreach ( $sources as $src ) {
				if ( isset( $properties[$src] ) ) {
					$property = $properties[$src];
					$this->i18nProperties[$target] = $this->resolveArrayParam( $property, 'plaintext' ) ?: $property;
					unset( $properties[$src] );
				}
			}
		}

		foreach ( $properties as $k => $v ) {
			$this->i18nProperties[$k] = $this->resolveStringParam( $this->resolveArrayParam( $v, 'plaintext' ) ) ?: $v;
		}
	}

	/**
	 * @param array $data
	 * @return array
	 */
	private function modifyData( $data ): array {
		$properties = $data['properties'];
		// The summary should not be included in the main line, because it should be accessed by
		// self::getI18nProperty( 'summary' ).
		foreach ( [
			'summary',
			'moderated-reason',
		] as $key ) {
			if ( isset( $properties[$key] ) ) {
				if ( is_array( $properties[$key] ) && isset( $properties[$key]['plaintext'] ) ) {
					$data['properties'][$key]['plaintext'] = '';
				} elseif ( $properties[$key] instanceof ScalarParam ) {
					// Note: ScalarParam::toJsonArray() is a protected function before MediaWiki 1.44
					$jsonArray = [
						$properties[$key]->getType()->value => $properties[$key]->getValue()
					];
					if ( in_array( 'plaintext', $jsonArray ) ) {
						$jsonArray['plaintext'] = '';
						$data['properties'][$key] = ScalarParam::newFromJsonArray( $jsonArray );
					}
				}
			}
		}

		// Replace user links(user text + links) with user text. We add our own user links.
		if ( $this->plaintext ) {
			$data['properties']['user-links'] = $properties['user-text'];
		}

		return $data;
	}

	/**
	 * @param string $key
	 * @return string
	 */
	public function getI18nProperty( string $key ): string {
		$property = $this->resolveArrayParam( $this->i18nProperties, $key );
		return $this->resolveStringParam( $property ) ?: '';
	}

	/**
	 * @param array|ScalarParam|null $obj
	 * @param string $key
	 */
	private function resolveArrayParam( mixed $obj, string $key ): mixed {
		if ( is_array( $obj ) && in_array( $key, $obj ) ) {
			return $obj[$key];
		} elseif ( $obj instanceof ScalarParam ) {
			return $this->resolveArrayParam( $obj->getValue(), $key );
		}
		return null;
	}

	/**
	 * @param string|ScalarParam|null $obj
	 */
	private function resolveStringParam( mixed $obj ): ?string {
		if ( is_string( $obj ) ) {
			return $obj;
		} elseif ( $obj instanceof ScalarParam ) {
			$value = $obj->getValue();
			if ( is_string( $value ) ) {
				return $value;
			}
		}
		return null;
	}

	/**
	 * @return string
	 */
	public function getDiscordDescription(): string {
		$data = $this->data;
		if ( !$data ) {
			return '';
		}

		$changeType = $data['changeType'];
		$actions = $this->permissions->getActions();

		$key = $actions->getValue( $changeType, 'history', 'i18n-message' );
		$msg = $this->context->msg( $key );

		// Fetch message
		$desc = $msg->params( $this->getDescriptionParams( $data, $actions, $changeType ) )->parse();

		// Remove tags
		if ( $this->plaintext ) {
			$desc = Sanitizer::stripAllTags( $desc );
		} else {
			$desc = $this->converter->convert( $desc );
		}

		// Remove empty parentheses which wrapped the removed summary.
		$desc = str_replace( '()', '', $desc );

		return $desc;
	}
}
