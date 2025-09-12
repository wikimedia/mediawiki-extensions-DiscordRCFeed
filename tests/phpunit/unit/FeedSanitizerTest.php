<?php

namespace MediaWiki\Extension\DiscordRCFeed\Tests\Unit;

use MediaWiki\Extension\DiscordRCFeed\FeedSanitizer;
use MediaWiki\RecentChanges\RecentChange;
use MediaWikiUnitTestCase;

/**
 * @group DiscordRCFeed
 *
 * @covers \MediaWiki\Extension\DiscordRCFeed\FeedSanitizer
 */
class FeedSanitizerTest extends MediaWikiUnitTestCase {

	public static function providerConfigs(): array {
		return [
			'should provide must-be-array parameters' => [
				[
					'omit_namespaces' => [],
					'omit_sources' => [],
				],
				[
					[],
					null,
					null,
					[
						'omit_namespaces',
						'omit_sources',
					],
				],
			],
			'should merge when the input is empty' => [
				[
					'omit_namespaces' => [],
					'omit_sources' => [ RecentChange::SRC_CATEGORIZE ],
				],
				[
					[],
					null,
					[
						'omit_sources' => [ RecentChange::SRC_CATEGORIZE ],
					],
					[
						'omit_namespaces',
						'omit_sources',
					],
				],
			],
			'should merge when the input is not empty' => [
				[
					'omit_namespaces' => [],
					'omit_sources' => [ RecentChange::SRC_EDIT, RecentChange::SRC_CATEGORIZE ],
				],
				[
					[
						'omit_sources' => [ RecentChange::SRC_EDIT ],
					],
					null,
					[
						'omit_sources' => [ RecentChange::SRC_CATEGORIZE ],
					],
					[
						'omit_namespaces',
						'omit_sources',
					],
				],
			],
			'should provide default parameters' => [
				[
					'omit_log_types' => [ 'patrol' ],
					'omit_log_actions' => [],
				],
				[
					[],
					[
						'omit_log_types' => [ 'patrol' ],
					],
					null,
					[
						'omit_log_types',
						'omit_log_actions',
					],
				],
			],
			'default is ignored if the parameter is given' => [
				[
					'omit_log_types' => [ 'protect' ],
					'omit_log_actions' => [],
				],
				[
					[
						'omit_log_types' => [ 'protect' ],
					],
					[
						'omit_log_types' => [ 'patrol' ],
					],
					null,
					[
						'omit_log_types',
						'omit_log_actions',
					]
				],
			],
			'replacement and merging should be done in the same time' => [
				[
					'omit_namespaces' => [],
					'omit_sources' => [ RecentChange::SRC_LOG, RecentChange::SRC_CATEGORIZE ],
					'omit_log_types' => [ 'protect' ],
					'omit_log_actions' => [],
				],
				[
					[
						'omit_sources' => [ RecentChange::SRC_LOG ],
						'omit_log_types' => [ 'protect' ],
					],
					[
						'omit_log_types' => [ 'patrol' ],
					],
					[
						'omit_sources' => [ RecentChange::SRC_CATEGORIZE ],
					],
					[
						'omit_namespaces',
						'omit_sources',
						'omit_log_types',
						'omit_log_actions',
					],
				],
			],
			// https://github.com/femiwiki/DiscordRCFeed/issues/32
			'should not mix the default array and the given array' => [
				[
					'omit_sources' => [ RecentChange::SRC_CATEGORIZE ],
					'page_tools' => [
						[
							'query' => 'action=edit',
							'msg' => 'edit'
						],
						[
							'query' => 'action=delete',
							'msg' => 'delete'
						],
						[
							'query' => 'action=history',
							'msg' => 'hist'
						],
					],
				],
				[
					[
						'page_tools' => [
							[
								'query' => 'action=edit',
								'msg' => 'edit'
							],
							[
								'query' => 'action=delete',
								'msg' => 'delete'
							],
							[
								'query' => 'action=history',
								'msg' => 'hist'
							],
						],
					],
					[
						'page_tools' => [
							[
								'target' => 'view',
								'msg' => 'view'
							],
							[
								'target' => 'diff',
								'msg' => 'diff'
							],
							[
								'query' => 'action=history',
								'msg' => 'hist'
							],
						],
					],
					[
						'omit_sources' => [ RecentChange::SRC_CATEGORIZE ],
					],
				]
			]
		];
	}

	/**
	 * @dataProvider providerConfigs
	 * @covers \MediaWiki\Extension\DiscordRCFeed\FeedSanitizer::initializeParameters
	 */
	public function testInitializeParameters( $expected, $params ) {
		FeedSanitizer::initializeParameters( ...$params );
		ksort( $expected );
		ksort( $params[0] );
		$this->assertSame(
			$expected,
			$params[0]
		);
	}
}
