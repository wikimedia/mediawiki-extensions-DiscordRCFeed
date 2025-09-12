<?php

namespace MediaWiki\Extension\DiscordRCFeed\Tests\Integration;

use MediaWiki\Extension\DiscordRCFeed\Constants;
use MediaWiki\Extension\DiscordRCFeed\DiscordRCFeedEngine;
use MediaWiki\Extension\DiscordRCFeed\FeedSanitizer;
use MediaWikiIntegrationTestCase;
use RecentChange;

/**
 * @group DiscordRCFeed
 * @group Database
 *
 * @covers \MediaWiki\Extension\DiscordRCFeed\DiscordRCFeedEngine
 */
class DiscordRCFeedEngineTest extends MediaWikiIntegrationTestCase {

	private static function makeRecentChange( array $attribs ) {
		$rc = new RecentChange;
		$rc->setAttribs( $attribs );
		return $rc;
	}

	public static function providerNotifyOmittance() {
		return [
			'should omit the given namespaces' => [
				[
					'omit_namespaces' => [ NS_TALK ],
				],
				[
					'rc_namespace' => NS_TALK,
				],
			],
			'should omit SRC_CATEGORIZE change always' => [
				[],
				[
					'rc_source' => RecentChange::SRC_CATEGORIZE,
				],
			],
			'should omit the given log types' => [
				[
					'omit_log_types' => 'patrol',
				],
				[
					'rc_source' => RecentChange::SRC_LOG,
					'rc_log_type' => 'patrol',
					'rc_log_action' => 'patrol',
				],
			],
			'should omit the given log action' => [
				[
					'omit_log_actions' => 'patrol/patrol-auto',
				],
				[
					'rc_user_text' => 'Dummy',
					'rc_source' => RecentChange::SRC_LOG,
					'rc_log_type' => 'patrol',
					'rc_log_action' => 'patrol-auto',
				],
			],
			'should omit the given page' => [
				[
					'omit_pages' => 'Main page',
				],
				[
					'rc_user_text' => 'Dummy',
					'rc_source' => RecentChange::SRC_EDIT,
					'rc_namespace' => NS_MAIN,
					'rc_title' => 'Main_page',
				],
			],
		];
	}

	/**
	 * @dataProvider providerNotifyOmittance
	 * @covers \MediaWiki\Extension\DiscordRCFeed\DiscordRCFeedEngine::notify
	 */
	public function testOmitChanges( array $feed, array $attribs ) {
		// Provide mandatory parameters if not given
		$attribs = array_replace_recursive( [
			'rc_namespace' => NS_MAIN,
			'rc_title' => 'Test page',
			'rc_source' => RecentChange::SRC_EDIT,
		], $attribs );
		FeedSanitizer::initializeParameters(
			$feed,
			Constants::DEFAULT_RC_FEED_PARAMS + [
				'url' => 'https://example.webhook/',
			],
			[ 'omit_sources' => [ RecentChange::SRC_CATEGORIZE ] ],
			Constants::RC_FEED_MUST_BE_ARRAY_PARAMS
		);

		$rc = self::makeRecentChange( $attribs );
		$engine = new DiscordRCFeedEngine( $feed );
		$sent = $engine->notify( $rc, '' );
		$this->assertFalse( $sent );
	}
}
