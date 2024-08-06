<?php

namespace MediaWiki\Extension\DiscordRCFeed\Tests\Integration;

use MediaWiki\Extension\DiscordRCFeed\DiscordRCFeedFormatter;
use MediaWiki\Extension\DiscordRCFeed\FeedSanitizer;
use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;
use MessageCache;
use RecentChange;
use Title;
use Wikimedia\TestingAccessWrapper;

/**
 * @group DiscordRCFeed
 * @group Database
 *
 * @covers \MediaWiki\Extension\DiscordRCFeed\DiscordRCFeedFormatter
 */
class DiscordRCFeedFormatterTest extends MediaWikiIntegrationTestCase {

	/** @var DiscordRCFeedFormatter */
	private $formatter;

	/** @var TestingAccessWrapper */
	private $wrapper;

	protected function setUp(): void {
		parent::setUp();
		$this->formatter = new DiscordRCFeedFormatter();
		$this->wrapper = TestingAccessWrapper::newFromObject( $this->formatter );
	}

	private static function makeRecentChange( array $attribs ) {
		$rc = new RecentChange;
		$rc->setAttribs( $attribs );
		return $rc;
	}

	public static function providerEmbed(): array {
		return [
			'should make plain post data' => [
				[
					'embeds' => [
						[
							'color' => 255,
							'description' => 'message (comment)'
						]
					],
					'username' => 'FooWiki',
				],
				'FooWiki',
				[],
			],
			'should replace the username' => [
				[
					'embeds' => [
						[
							'color' => 255,
							'description' => 'message (comment)'
						]
					],
					'username' => 'DummyBot',
				],
				'',
				[ 'request_replace' => [ 'username' => 'DummyBot' ] ],
			],
			'should make post data in the structure style when tools are empy' => [
				[
					'embeds' => [
						[
							'color' => 255,
							'description' => 'message',
							'fields' => [
								[
									'name' => 'Summary:',
									'value' => 'comment',
								]
							],
						]
					],
					'username' => 'DummyBot',
				],
				'',
				[
					'style' => DiscordRCFeedFormatter::STYLE_STRUCTURE,
					'request_replace' => [ 'username' => 'DummyBot' ],
					'user_tools' => [],
					'page_tools' => [],
				],
			],
			'should make post data when user tools are given' => [
				[
					'embeds' => [
						[
							'color' => 255,
							'description' => 'message',
							'fields' => [
								[
									'name' => 'Dummy',
									'value' => '[User page](https://foo.bar/index.php/User:Dummy)',
									'inline' => true
								],
								[
									'name' => 'Summary:',
									'value' => 'comment',
								],
							],
						]
					],
					'username' => 'DummyBot',
				],
				'',
				[
					'style' => DiscordRCFeedFormatter::STYLE_STRUCTURE,
					'request_replace' => [ 'username' => 'DummyBot' ],
					'user_tools' => [
						[
							'target' => 'user_page',
							'msg' => 'nstab-user'
						],
					],
					'page_tools' => [],
				],
			],
		];
	}

	/**
	 * @covers \MediaWiki\Extension\DiscordRCFeed\DiscordRCFeedFormatter::makePostData
	 * @dataProvider providerEmbed
	 */
	public function testMakePostData(
		array $expected,
		string $sitename,
		array $feed
	) {
		if ( $sitename ) {
			$this->overrideConfigValue( MainConfigNames::Sitename, $sitename );
		}
		$this->overrideConfigValues( [
			MainConfigNames::Server => 'https://foo.bar',
			MainConfigNames::ArticlePath => '/index.php/$1',
			MainConfigNames::Script => '/index.php',
		] );
		$defaultParams = [
			'style' => DiscordRCFeedFormatter::STYLE_EMBED,
		];
		FeedSanitizer::initializeParameters( $feed, $defaultParams );
		$user = $this->getTestSysop()->getUser();
		$user->setName( 'Dummy' );

		$formatter = new DiscordRCFeedFormatter( $feed, $user, Title::newFromText( 'Dummy' ) );
		$wrapper = TestingAccessWrapper::newFromObject( $formatter );
		$actual = $wrapper->makePostData(
			[ 'rc_type' => RC_EDIT ],
			0x0000ff,
			'message',
			'comment'
		);
		$this->assertEquals( $expected, (array)json_decode( $actual, true ) );
	}

	public static function providerGetDescription() {
		$cases = [
			'should describe new page' => [
				[
					'discordrcfeed-emoji-log-create-create',
					'logentry-create-create',
				],
				[],
				[
					'rc_type' => RC_NEW,
				],
			],
			'should describe edit' => [
				[
					'discordrcfeed-emoji-edit',
					'discordrcfeed-line-edit',
				],
				[],
				[
					'rc_type' => RC_EDIT,
				],
			],
			'should describe edit with the structure style' => [
				[
					'discordrcfeed-emoji-edit',
					'discordrcfeed-line-edit',
				],
				[
					'style' => DiscordRCFeedFormatter::STYLE_STRUCTURE,
				],
				[
					'rc_type' => RC_EDIT,
				],
			],
			'should describe minor edit' => [
				[
					'discordrcfeed-emoji-edit-minor',
					'discordrcfeed-line-edit-minor',
				],
				[],
				[
					'rc_type' => RC_EDIT,
					'rc_minor' => true,
				],
			],
			'should describe bot edit' => [
				[
					'discordrcfeed-emoji-edit-bot',
					'discordrcfeed-line-edit-bot',
				],
				[],
				[
					'rc_type' => RC_EDIT,
					'rc_bot' => true,
				],
			],
			'should describe minor bot edit' => [
				[
					'discordrcfeed-emoji-edit-minor-bot',
					'discordrcfeed-line-edit-minor-bot',
				],
				[],
				[
					'rc_type' => RC_EDIT,
					'rc_minor' => true,
					'rc_bot' => true,
				],
			],
			// Todo create mock for DatabaseLogEntry
			// 'should describe move' => [
			// 	[
			// 		'discordrcfeed-emoji-move',
			// 		'logentry-move-move',
			// 	],
			// 	[],
			// 	[
			// 		'rc_type' => RC_LOG,
			// 		'rc_log_type' => 'move',
			// 		'rc_log_action' => 'move',
			// 	],
			// ],
		];

		// TODO
		// if ( \ExtensionRegistry::getInstance()->isLoaded( 'Flow' ) ) {
		// 	$cases['should describe edit'] = [
		// 		[
		// 			'discordrcfeed-emoji-flow-reply',
		// 			'flow-rev-message-reply',
		// 		],
		// 		[],
		// 		[
		// 			'rc_type' => RC_FLOW,
		// 			'rc_params' => serialize( [
		// 			] ),
		// 		],
		// 	];
		// }
		return $cases;
	}

	/**
	 * @dataProvider providerGetDescription
	 * @covers \MediaWiki\Extension\DiscordRCFeed\DiscordRCFeedFormatter::getDescription
	 */
	public function testGetDescription( array $expected, array $feed, array $attribs ) {
		// Provide mandatory parameters if not given
		$user = $this->getTestSysop()->getUser();
		$user->setName( 'GetDescriptionTestUser' );
		$title = Title::newFromText( 'Test page' );
		$attribs = array_replace_recursive( [
			'rc_minor' => false,
			'rc_bot' => false,
			'rc_user' => $user->getId(),
			'rc_namespace' => NS_MAIN,
			'rc_title' => 'Test page',
		], $attribs );
		FeedSanitizer::initializeParameters( $feed, [
			'style' => DiscordRCFeedFormatter::STYLE_EMBED,
		] );

		$rc = self::makeRecentChange( $attribs );
		$this->setContentLang( 'qqx' );
		$formatter = new DiscordRCFeedFormatter( $feed, $user, $title );
		$wrapper = TestingAccessWrapper::newFromObject( $formatter );
		$desc = $wrapper->getDescription( $rc, false );
		foreach ( $expected as $key ) {
			$this->assertStringContainsString( $key, $desc );
		}
		$this->assertStringContainsString( 'GetDescriptionTestUser', $desc );
		$this->assertStringContainsString( 'Test page', $desc );
	}

	public static function provideEmojiKeys(): array {
		return [
			[
				'🔓',
				'block',
				'unblock',
				'',
				'should show if exact match exists'
			],
			[
				'❌',
				'block',
				'reblock',
				'',
				'should show if exact match does not exist'
			],
			[
				'🚀',
				'random',
				'random',
				'test-emoji-rocket',
				'should use the given fallback if no match is found'
			],
			[
				'❓',
				'random',
				'random',
				'',
				'should only use prefix if no match is found and fallback is not given'
			],
		];
	}

	/**
	 * @dataProvider provideEmojiKeys
	 * @covers \MediaWiki\Extension\DiscordRCFeed\DiscordRCFeedFormatter::getEmojiForKeys
	 */
	public function testGetEmojiForKeys( $expected, $mainKey, $subKey, $fallback, $message ) {
		$msgMap = [
			'test-emoji-block-unblock' => '🔓',
			'test-emoji-block' => '❌',
			'test-emoji-rocket' => '🚀',
			'test-emoji' => '❓',
		];
		$mock = $this->createMock( MessageCache::class );
		$mock->method( 'get' )
			->willReturnCallback(
				static function ( $key, $useDB, $lang ) use ( $msgMap ) {
					return $msgMap[$key] ?? false;
				}
			);
		$mock->method( 'transform' )
			->willReturnArgument( 0 );
		$this->setService( 'MessageCache', $mock );

		$emoji = $this->wrapper->getEmojiForKeys( 'test-emoji', $mainKey, $subKey, $fallback );
		$this->assertSame( $expected, $emoji, $message );
	}

	public static function providerSizes() {
		$embed = DiscordRCFeedFormatter::STYLE_EMBED;
		$structure = DiscordRCFeedFormatter::STYLE_STRUCTURE;
		return [
			'should return size if the old length is not provided' => [
				'(30 bytes)',
				$embed,
				[ RC_NEW, 30 ],
			],
			'should return size and diff if the old length is provided' => [
				'30 bytes (+10)',
				$structure,
				[ RC_EDIT, 30, 20 ],
			],
		];
	}

	/**
	 * @dataProvider providerSizes
	 * @covers \MediaWiki\Extension\DiscordRCFeed\DiscordRCFeedFormatter::getSizeDiff
	 */
	public function testGetSizeDiff( $expected, $style, $params ) {
		$user = $this->getTestSysop()->getUser();
		$user->setName( 'Dummy' );
		$title = Title::newFromText( 'Dummy' );

		$feed = [
			'style' => $style,
		];
		$formatter = new DiscordRCFeedFormatter( $feed, $user, $title );
		$wrapper = TestingAccessWrapper::newFromObject( $formatter );
		$actual = $wrapper->getSizeDiff( [
			'rc_type' => $params[0],
			'rc_new_len' => $params[1],
			'rc_old_len' => $params[2] ?? null,
		] );
		$this->assertSame( $expected, $actual );
	}
}
