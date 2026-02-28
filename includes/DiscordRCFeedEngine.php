<?php

namespace MediaWiki\Extension\DiscordRCFeed;

use ConfigException;
use FormattedRCFeed;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\MediaWikiServices;
use RecentChange;

class DiscordRCFeedEngine extends FormattedRCFeed {
	/** @var array */
	private $params;

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/**
	 * @inheritDoc
	 */
	public function __construct( array $params ) {
		if ( !isset( $params['url'] ) ) {
			throw new ConfigException( "RCFeed for Discord must have a 'url' set." );
		}
		$this->httpRequestFactory = MediaWikiServices::getInstance()->getHttpRequestFactory();
		$this->params = $params;
		parent::__construct( $params );
	}

	/**
	 * @inheritDoc
	 */
	public function send( array $feed, $line ) {
		$req = $this->httpRequestFactory->create(
			$feed['url'],
			[
				'method' => 'POST',
				'postData' => $line
			],
			__METHOD__
		);
		$req->setHeader( 'Content-Type', 'application/json' );

		$status = $req->execute();
		if ( !$status->isOK() ) {
			Util::getLogger()->warning( $status->getMessage()->text() . ': '
				. print_r( json_decode( $line ), true ) );
			return false;
		}
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function notify( RecentChange $rc, $actionComment = null ) {
		$params = $this->params;
		$attribs = $rc->mAttribs;
		$logType = $attribs['rc_log_type'] ?? '';
		$logAction = $attribs['rc_log_action'] ?? '';
		$logFullName = "$logType/$logAction";
		$isTalk = MediaWikiServices::getInstance()->getNamespaceInfo()->
			isTalk( $attribs['rc_namespace'] );
		$title = Util::getTitleFromRC( $rc );
		$contentModel = $title->getContentModel();
		// unused now, for the future usage.
		// $oldLen = $attribs['rc_old_len'] ?? '';
		// $newLen = $attribs['rc_new_len'] ?? '';
		if (
			// Talk
			( $params['omit_talk'] && $isTalk ) ||
			( $params['only_talk'] && !$isTalk ) ||

			// RC source
			in_array( $attribs['rc_source'], $params['omit_sources'] ) ||
			( $params['only_sources'] && !in_array( $attribs['rc_source'], $params['only_sources'] ) ) ||

			// Namespaces
			in_array( $attribs['rc_namespace'], $params['omit_namespaces'] ) ||
			( $params['only_namespaces'] && !in_array( $attribs['rc_namespace'], $params['only_namespaces'] ) ) ||

			// Log types
			in_array( $logType, $params['omit_log_types'] ) ||
			( $params['only_log_types'] && !in_array( $logType, $params['only_log_types'] ) ) ||

			// Log actions
			in_array( $logFullName, $params['omit_log_actions'] ) ||
			( $params['only_log_actions'] && !in_array( $logFullName, $params['only_log_actions'] ) ) ||

			// Usernames
			in_array( $attribs['rc_user_text'], $params['omit_usernames'] ) ||
			( $params['only_usernames'] && !in_array( $attribs['rc_user_text'], $params['only_usernames'] ) ) ||

			// Page names
			in_array( $title->getFullText(), $params['omit_pages'] ) ||
			( $params['only_pages'] && !in_array( $title->getFullText(), $params['only_pages'] ) ) ||

			// Content Models
			in_array( $contentModel, $params['omit_content_models'] ) ||
			( $params['only_content_models'] && !in_array( $contentModel, $params['only_content_models'] ) )
		) {
			return false;
		}
		return parent::notify( $rc, $actionComment );
	}
}
